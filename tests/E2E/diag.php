<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use FiscalLib\Adapters\FiscalApi\ClienteHttp;
use FiscalLib\Config\FiscalConfig;

$base = 'http://localhost:8080';

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
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 30,
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
    fwrite(STDERR, 'login falhou: ' . print_r($login, true) . "\n");
    exit(1);
}

[$tenants] = adminHttp('GET', "{$base}/v1/admin/tenants", null, $token);
$lista = $tenants['itens'] ?? $tenants['tenants'] ?? (is_array($tenants) && array_is_list($tenants) ? $tenants : []);
$tenantId = null;
foreach ((array) $lista as $t) {
    if (($t['cnpj'] ?? '') === '11444777000161') {
        $tenantId = $t['id'];
        break;
    }
}
if ($tenantId === null) {
    fwrite(STDERR, "tenants bruto: " . json_encode($tenants) . "\n");
    exit(1);
}

// a chave completa só é exibida na criação — criar uma nova a cada diagnóstico
[$key] = adminHttp('POST', "{$base}/v1/admin/tenants/{$tenantId}/api-keys", [
    'descricao' => 'diag', 'ambiente' => 'homologacao',
], $token);
$apiKey = $key['chave'] ?? null;
if ($apiKey === null) {
    fwrite(STDERR, "api-key bruto: " . json_encode($key) . "\n");
    exit(1);
}

echo "tenant: {$tenantId}\n";

// Últimos documentos do tenant — via endpoint público com a api-key
$cliente = new ClienteHttp(new FiscalConfig($base, $apiKey));
try {
    $docs = $cliente->get('/v1/documentos-fiscais?pagina=1&tamanhoPorPagina=8');
} catch (Throwable $e) {
    fwrite(STDERR, 'listar falhou: ' . $e->getMessage() . "\n");
    exit(1);
}
$itens = $docs['itens'] ?? $docs['documentos'] ?? (is_array($docs) && array_is_list($docs) ? $docs : []);
echo "--- documentos recentes ---\n";
foreach ((array) $itens as $d) {
    printf(
        "%s | %s | %s | %s\n",
        substr((string) ($d['id'] ?? '?'), 0, 8),
        (string) ($d['modelo'] ?? '?'),
        (string) ($d['status'] ?? '?'),
        mb_substr((string) ($d['motivoStatus'] ?? '-'), 0, 400)
    );
}
if ($itens === []) {
    echo json_encode($docs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
}
