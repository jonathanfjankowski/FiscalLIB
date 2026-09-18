<?php

declare(strict_types=1);

/**
 * Diagnóstico por modelo: emite NFC-e e NFS-e no sandbox real (SEFAZ homologação)
 * e mostra o estado terminal de cada uma.
 */

require __DIR__ . '/../../vendor/autoload.php';

use FiscalLib\Adapters\FiscalApi\GestaoFiscalApi;
use FiscalLib\Common\Enums\Ambiente;
use FiscalLib\Common\Enums\CstIcms;
use FiscalLib\Common\Enums\FormaPagamento;
use FiscalLib\Common\Enums\OrigemMercadoria;
use FiscalLib\Common\Enums\UF;
use FiscalLib\Common\ValueObjects\Cnpj;
use FiscalLib\Config\FiscalConfig;
use FiscalLib\Documento\Endereco;
use FiscalLib\Documento\IbsCbsDps;
use FiscalLib\Documento\ItemFiscal;
use FiscalLib\Documento\ServicoFiscal;
use FiscalLib\Documento\Tomador;
use FiscalLib\FiscalLib;
use FiscalLib\Tax\Contextos\NfeTaxContext;
use FiscalLib\Tax\Contextos\NfseTaxContext;

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
    fwrite(STDERR, "tenant {$cnpjTenant} não encontrado\n");
    exit(1);
}

[$key] = adminHttp('POST', "{$base}/v1/admin/tenants/{$tenantId}/api-keys", [
    'descricao' => 'diag-modelos', 'ambiente' => 'homologacao',
], $token);
$apiKey = $key['chave'] ?? null;
if ($apiKey === null) {
    fwrite(STDERR, "api-key falhou\n");
    exit(1);
}

$lib = FiscalLib::comFiscalApi(
    new FiscalConfig($base, $apiKey, Ambiente::Homologacao, 15, 2, [3, 5, 10, 20, 30], 70)
);

// ---- NFC-e ------------------------------------------------------------
$tributos = $lib->taxEngine()->calcularNfe(
    NfeTaxContext::make()->valores(1, 25.50)->icms(OrigemMercadoria::Nacional, CstIcms::TributadaIntegralmente, aliquota: 18)
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
    ->pagamento(FormaPagamento::Dinheiro, 25.50)
    ->build();

$aceite = $lib->nfce()->emitirAsync($nfce);
$final = $lib->aguardarTerminal($aceite->documentoId);
printf("NFC-e  %s | %s\n", $final->status, mb_substr((string) ($final->motivoStatus ?? '-'), 0, 300));

// ---- NFS-e (DPS) -------------------------------------------------------
$tributosNfse = $lib->taxEngine()->calcularNfse(
    NfseTaxContext::make()->servico(1000)->iss(5, tributacao: 1, retencao: 2)->pisCofins('01', 0.65, 3.0)
);
$nfse = $lib->nfse()->novo()
    ->ambiente(Ambiente::Homologacao)
    ->serie(1)
    ->competencia(gmdate('Y-m-d'))
    ->tomador(new Tomador(
        Cnpj::criar('45997418000153'),
        'Tomador Integracao LTDA',
        endereco: new Endereco(cep: '01001000', logradouro: 'Praça da Sé', numero: '1', bairro: 'Sé', codigoMunicipioIbge: '3550308', uf: UF::SP, nomeMunicipio: 'São Paulo'),
    ))
    ->servico(new ServicoFiscal('010701', 'Desenvolvimento de software diagnostico', codigoNbs: '112011000'))
    ->tributos($tributosNfse)
    ->ibsCbs(new IbsCbsDps('000001', '101', '000001'))
    ->build();

$aceite2 = $lib->nfse()->emitirAsync($nfse);
$final2 = $lib->aguardarTerminal($aceite2->documentoId);
printf("NFS-e  %s | %s\n", $final2->status, mb_substr((string) ($final2->motivoStatus ?? '-'), 0, 300));
