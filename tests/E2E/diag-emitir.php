<?php

declare(strict_types=1);

/**
 * Diagnóstico: emite uma NF-e simples no sandbox e mostra o estado terminal
 * completo (status + motivoStatus) para revelar a causa do ERRO_INTERNO/rejeição.
 */

require __DIR__ . '/../../vendor/autoload.php';

use FiscalLib\Common\Enums\Ambiente;
use FiscalLib\Adapters\FiscalApi\ClienteHttp;
use FiscalLib\Common\ValueObjects\Cnpj;
use FiscalLib\Config\FiscalConfig;
use FiscalLib\Documento\Destinatario;
use FiscalLib\Documento\Endereco;
use FiscalLib\Documento\ItemFiscal;
use FiscalLib\FiscalLib;
use FiscalLib\Tax\Contextos\IbsCbsEntrada;
use FiscalLib\Tax\Contextos\NfeTaxContext;

$base = 'http://localhost:8080';
$cnpjTenant = getenv('FISCAL_TENANT_CNPJ') ?: '66194301000101';

function adminHttp(string $method, string $url, ?array $corpo = null, ?string $bearer = null): array
{
    $ch = curl_init($url);
    $headers = [];
    if ($corpo !== null) {
        $headers[] = 'Content-Type: application/json';
    }
    if ($bearer !== null) {
        $headers[] = "Authorization: Bearer {$bearer}";
    }
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers, CURLOPT_TIMEOUT => 30,
    ]);
    if ($corpo !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($corpo));
    }
    $resp = (string) curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    $dados = json_decode($resp, true);

    return [$dados === null ? $resp : $dados, $status];
}

[$login] = adminHttp('POST', "{$base}/v1/admin/auth/login", [
    'email' => 'admin@fiscal.local', 'senha' => getenv('ADMIN_PASSWORD') ?: '',
]);
$token = $login['token'] ?? null;
if ($token === null) {
    fwrite(STDERR, 'login falhou' . "\n");
    exit(1);
}

// resolve o tenant pelo CNPJ
[$tenants] = adminHttp('GET', "{$base}/v1/admin/tenants", null, $token);
$tenantId = null;
foreach ((array) ($tenants['itens'] ?? (is_array($tenants) && array_is_list($tenants) ? $tenants : [])) as $t) {
    if (($t['cnpj'] ?? '') === $cnpjTenant) {
        $tenantId = $t['id'];
        break;
    }
}
if ($tenantId === null) {
    fwrite(STDERR, "tenant com CNPJ {$cnpjTenant} não encontrado — rode bootstrap-cert.php antes.\n");
    exit(1);
}

[$key] = adminHttp('POST', "{$base}/v1/admin/tenants/{$tenantId}/api-keys", [
    'descricao' => 'diag-emitir', 'ambiente' => 'homologacao',
], $token);
$apiKey = $key['chave'] ?? null;
if ($apiKey === null) {
    fwrite(STDERR, 'api-key falhou: ' . json_encode($key) . "\n");
    exit(1);
}

$lib = FiscalLib::comFiscalApi(
    new FiscalConfig($base, $apiKey, Ambiente::Homologacao, 15, 2, [1, 2, 3, 5, 10], 120)
);

$tributos = $lib->taxEngine()->calcularNfe(
    NfeTaxContext::make()->valores(1, 100)->icms(0, cst: '00', aliquota: 12)->pis('01', '1.65')->cofins('01', '7.60')->ibsCbs(IbsCbsEntrada::criar('000', '000001', aliquotaIbsEstadual: 0.1, aliquotaCbs: 0.9))
);
$doc = $lib->nfe()->novo()
    ->naturezaOperacao('Venda diagnostico integracao')
    ->destinatario(new Destinatario(
        Cnpj::criar('45997418000153'),
        'NF-E EMITIDA EM AMBIENTE DE HOMOLOGACAO - SEM VALOR FISCAL',
        inscricaoEstadual: '110042490114',
        endereco: new Endereco(cep: '01001000', logradouro: 'Praça da Sé', numero: '1', bairro: 'Sé', codigoMunicipioIbge: '3550308', uf: 'SP', nomeMunicipio: 'São Paulo'),
    ))
    ->addItem(new ItemFiscal(
        codigo: 'SKU-DIAG',
        descricao: 'Produto diagnostico',
        quantidade: '1.0000',
        valorUnitario: '100.00',
        valorTotal: '100.00',
        tributos: $tributos,
        ncm: '84714900',
        cfop: '6102',
    ))
    ->pagamento('01', 100)
    ->build();

$aceite = $lib->nfe()->emitirAsync($doc);
echo "documentoId: {$aceite->documentoId} (status {$aceite->status})\n";

$final = $lib->aguardarTerminal($aceite->documentoId);
echo "status terminal: {$final->status}\n";
echo "motivoStatus: " . ($final->motivoStatus ?? '(null)') . "\n";
echo "codigoStatus: " . var_export($final->codigoStatus ?? null, true) . "\n";

// consulta bruta para ver tudo o que a API devolve
try {
    $bruto = (new ClienteHttp(new FiscalConfig($base, $apiKey)))
        ->get('/v1/documentos-fiscais/' . $aceite->documentoId);
    echo "--- resposta bruta ---\n";
    echo json_encode($bruto, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
} catch (Throwable $e) {
    echo '(consulta bruta falhou: ' . $e->getMessage() . ')' . "\n";
}
