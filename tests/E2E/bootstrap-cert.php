<?php

declare(strict_types=1);

/**
 * Bootstrap do E2E: garante tenant + api-key + certificado A1 ativo.
 *
 * Uso: php tests/E2E/bootstrap-cert.php <caminho.pfx> <senha-do-pfx>
 */

$base = 'http://localhost:8080';
$adminSenha = getenv('ADMIN_PASSWORD') ?: '';
// Deve ter o MESMO CNPJ-base do certificado A1 (senão SEFAZ rejeita com 213).
$cnpjTenant = getenv('FISCAL_TENANT_CNPJ') ?: '66194301000101';

function http(string $method, string $url, ?array $corpo = null, ?string $bearer = null): array
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

[$login, $st] = http('POST', "{$base}/v1/admin/auth/login", [
    'email' => 'admin@fiscal.local', 'senha' => $adminSenha,
]);
$token = $login['token'] ?? null;
if ($token === null) {
    fwrite(STDERR, "Login admin falhou (HTTP {$st}): " . print_r($login, true) . "\n");
    exit(1);
}

[$resp, $st] = http('POST', "{$base}/v1/admin/tenants", [
    'cnpj' => $cnpjTenant,
    'razaoSocial' => 'OKTO SISTEMAS INOVA SIMPLES I',
    'uf' => 'PR',
    'codigoMunicipioIbge' => '4110706',
    'regimeTributario' => 3,
    'ambientePadrao' => 'homologacao',
    'logradouro' => 'Avenida Teste',
    'numero' => '100',
    'bairro' => 'Centro',
    'cep' => '84500000',
    'nomeMunicipio' => 'Irati',
], $token);

if ($st === 409) {
    [$lista] = http('GET', "{$base}/v1/admin/tenants", null, $token);
    foreach ($lista as $t) {
        if (($t['cnpj'] ?? null) === $cnpjTenant) {
            $resp = $t['id'];
            break;
        }
    }
}
$tenantId = is_array($resp) ? ($resp['id'] ?? null) : $resp;
if ($tenantId === null) {
    fwrite(STDERR, "Tenant não resolvido (HTTP {$st}): " . print_r($resp, true) . "\n");
    exit(1);
}
echo "tenant: {$tenantId}\n";

// Certificado
$pfx = $argv[1] ?? '';
$senha = $argv[2] ?? '';
if ($pfx === '' || $senha === '') {
    fwrite(STDERR, "Uso: php bootstrap-cert.php <caminho.pfx> <senha>\n");
    exit(1);
}
if (! is_file($pfx)) {
    fwrite(STDERR, "PFX não encontrado: {$pfx}\n");
    exit(1);
}

require __DIR__ . '/../../vendor/autoload.php';
use FiscalLib\Adapters\FiscalApi\GestaoFiscalApi;
use FiscalLib\Config\FiscalConfig;

// api-key para autenticar o resource de gestão (mesma usada pelos testes)
[$keys] = http('GET', "{$base}/v1/admin/tenants/{$tenantId}/api-keys", null, $token);
$apiKey = null;
foreach ((is_array($keys) ? $keys : []) as $k) {
    if (($k['ambiente'] ?? '') === 'homologacao' && ($k['ativa'] ?? true)) {
        $apiKey = $k['chave'] ?? null;
        break;
    }
}
if ($apiKey === null) {
    [$key, $st] = http('POST', "{$base}/v1/admin/tenants/{$tenantId}/api-keys", [
        'descricao' => 'PHPUnit FiscalLIB', 'ambiente' => 'homologacao',
    ], $token);
    $apiKey = $key['chave'] ?? null;
}
if ($apiKey === null) {
    fwrite(STDERR, "api-key não obtida: " . print_r($key ?? $keys, true) . "\n");
    exit(1);
}

$gestao = new GestaoFiscalApi(new FiscalConfig($base, $apiKey));
try {
    $certs = $gestao->listarCertificados();
    $ativo = false;
    foreach ($certs as $c) {
        if ($c['ativo'] ?? false) {
            $ativo = true;
            echo "certificado já ativo: " . ($c['nomeArquivo'] ?? '?') . " validoAte=" . ($c['validoAte'] ?? '?') . "\n";
        }
    }
    if (! $ativo) {
        $r = $gestao->enviarCertificado($pfx, $senha);
        echo "certificado enviado: " . json_encode($r, JSON_UNESCAPED_UNICODE) . "\n";
    }
} catch (Throwable $e) {
    fwrite(STDERR, 'Erro gestão: ' . $e->getMessage() . "\n");
    exit(1);
}

echo "ADMIN_PASSWORD ok, tenant={$tenantId}\n";
