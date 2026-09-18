<?php

declare(strict_types=1);

/**
 * Configura o perfil fiscal do emitente (IE + CSC) e reemite a NF-e de
 * diagnóstico, mostrando o estado terminal.
 */

require __DIR__ . '/../../vendor/autoload.php';

use FiscalLib\Adapters\FiscalApi\ClienteHttp;
use FiscalLib\Adapters\FiscalApi\GestaoFiscalApi;
use FiscalLib\Common\Enums\Ambiente;
use FiscalLib\Common\Enums\CstIcms;
use FiscalLib\Common\Enums\FormaPagamento;
use FiscalLib\Common\Enums\OrigemMercadoria;
use FiscalLib\Common\Enums\UF;
use FiscalLib\Common\ValueObjects\Cnpj;
use FiscalLib\Config\FiscalConfig;
use FiscalLib\Documento\Destinatario;
use FiscalLib\Documento\Endereco;
use FiscalLib\Documento\ItemFiscal;
use FiscalLib\FiscalLib;
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
    fwrite(STDERR, "login falhou\n");
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
    'descricao' => 'diag-perfil', 'ambiente' => 'homologacao',
], $token);
$apiKey = $key['chave'] ?? null;
if ($apiKey === null) {
    fwrite(STDERR, 'api-key falhou: ' . json_encode($key) . "\n");
    exit(1);
}

$gestao = new GestaoFiscalApi(new FiscalConfig($base, $apiKey));

// Perfil do emitente: IE em formato PR (12345678-50) + CSC de homologação para NFC-e
$perfil = $gestao->atualizarPerfil([
    'inscricaoEstadual' => '1234567850',
    'inscricaoMunicipal' => '1234567',
    'cscId' => '01',
    'csc' => 'CODIGO-CSC-HOMOLOGACAO-PR-0001',
]);
echo "perfil atualizado: " . json_encode($perfil) . "\n";
echo "perfil atual: " . json_encode($gestao->perfil(), JSON_UNESCAPED_UNICODE) . "\n";

// Reemite a NF-e de diagnóstico
$lib = FiscalLib::comFiscalApi(
    new FiscalConfig($base, $apiKey, Ambiente::Homologacao, 15, 2, [1, 2, 3, 5, 10], 120)
);

$tributos = $lib->taxEngine()->calcularNfe(
    NfeTaxContext::make()->valores(1, 100)->icms(OrigemMercadoria::Nacional, CstIcms::TributadaIntegralmente, aliquota: 18)
);
$doc = $lib->nfe()->novo()
    ->naturezaOperacao('Venda diagnostico integracao')
    ->destinatario(new Destinatario(
        Cnpj::criar('45997418000153'),
        'Comprador Integracao LTDA',
        inscricaoEstadual: 'ISENTO',
        endereco: new Endereco(cep: '01001000', logradouro: 'Praça da Sé', numero: '1', bairro: 'Sé', codigoMunicipioIbge: '3550308', uf: UF::SP, nomeMunicipio: 'São Paulo'),
    ))
    ->addItem(new ItemFiscal(
        codigo: 'SKU-DIAG',
        descricao: 'Produto diagnostico',
        quantidade: '1.0000',
        valorUnitario: '100.00',
        valorTotal: '100.00',
        tributos: $tributos,
        ncm: '12345678',
        cfop: '5102',
    ))
    ->pagamento(FormaPagamento::Dinheiro, 100)
    ->build();

$aceite = $lib->nfe()->emitirAsync($doc);
echo "documentoId: {$aceite->documentoId} (status {$aceite->status})\n";

$final = $lib->aguardarTerminal($aceite->documentoId);
echo "status terminal: {$final->status}\n";
echo "motivoStatus: " . ($final->motivoStatus ?? '(null)') . "\n";
