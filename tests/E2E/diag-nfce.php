<?php

declare(strict_types=1);

/**
 * Diagnóstico NFC-e only: emite e mostra o terminal.
 * Opcional: php diag-nfce.php <documentoId> — só consulta o documento.
 */

require __DIR__ . '/../../vendor/autoload.php';

use FiscalLib\Adapters\FiscalApi\ClienteHttp;
use FiscalLib\Common\Enums\Ambiente;
use FiscalLib\Config\FiscalConfig;
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

[$tenants] = adminHttp('GET', "{$base}/v1/admin/tenants", null, $token);
$tenantId = null;
foreach ((array) ($tenants['itens'] ?? (is_array($tenants) && array_is_list($tenants) ? $tenants : [])) as $t) {
    if (($t['cnpj'] ?? '') === $cnpjTenant) {
        $tenantId = $t['id'];
        break;
    }
}
if ($tenantId === null) {
    fwrite(STDERR, "tenant não encontrado\n");
    exit(1);
}

[$key] = adminHttp('POST', "{$base}/v1/admin/tenants/{$tenantId}/api-keys", [
    'descricao' => 'diag-nfce', 'ambiente' => 'homologacao',
], $token);
$apiKey = $key['chave'] ?? null;
if ($apiKey === null) {
    fwrite(STDERR, "api-key falhou\n");
    exit(1);
}

// modo consulta: php diag-nfce.php <documentoId>
if (isset($argv[1])) {
    $cliente = new ClienteHttp(new FiscalConfig($base, $apiKey));
    $doc = $cliente->get('/v1/documentos-fiscais/' . $argv[1]);
    echo json_encode($doc, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    exit(0);
}

$lib = FiscalLib::comFiscalApi(
    new FiscalConfig($base, $apiKey, Ambiente::Homologacao, 15, 2, [2, 5, 10, 20, 30, 60], 240)
);

$tributos = $lib->taxEngine()->calcularNfe(
    NfeTaxContext::make()->valores(1, 25.50)->icms(0, cst: '00', aliquota: 18)
);
$nfce = $lib->nfce()->novo()
    ->naturezaOperacao('Venda balcao diagnostico')
    ->addItem(new ItemFiscal(
        codigo: 'SKU-DIAG65',
        descricao: 'NOTA FISCAL EMITIDA EM AMBIENTE DE HOMOLOGACAO - SEM VALOR FISCAL',
        quantidade: '1.0000',
        valorUnitario: '25.50',
        valorTotal: '25.50',
        tributos: $tributos,
        ncm: '84714900',
        cfop: '5102',
    ))
    ->pagamento('01', 25.50)
    ->build();

$aceite = $lib->nfce()->emitirAsync($nfce);
echo "documentoId: {$aceite->documentoId}\n";
try {
    $final = $lib->aguardarTerminal($aceite->documentoId);
    printf("NFC-e  %s | %s\n", $final->status, mb_substr((string) ($final->motivoStatus ?? '-'), 0, 400));
} catch (Throwable $e) {
    echo 'timeout no polling: ' . $e->getMessage() . "\n";
}
