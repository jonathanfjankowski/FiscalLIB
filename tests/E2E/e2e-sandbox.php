<?php

declare(strict_types=1);

/**
 * E2E contra a FiscalAPI real em sandbox (ModoSandbox=true).
 *
 * Uso:
 *   FISCAL_BASE_URL=http://localhost:8080 \
 *   ADMIN_EMAIL=admin@fiscal.local ADMIN_PASSWORD=... \
 *   php tests/E2E/e2e-sandbox.php
 *
 * Faz: bootstrap (login admin → tenant → api key) e então:
 * NFC-e · NF-e · idempotência · PDF · cancelamento · CC-e · inutilização ·
 * NFS-e DPS · status-serviço · caminhos de erro (422 e 409).
 */

use FiscalLib\Adapters\FiscalApi\GestaoFiscalApi;
use FiscalLib\Common\Enums\Ambiente;
use FiscalLib\Common\Enums\CstIcms;
use FiscalLib\Common\Enums\CstIpi;
use FiscalLib\Common\Enums\CstPisCofins;
use FiscalLib\Common\Enums\Csosn;
use FiscalLib\Common\Enums\FormaPagamento;
use FiscalLib\Common\Enums\OrigemMercadoria;
use FiscalLib\Common\Enums\UF;
use FiscalLib\Common\Enums\ModeloDocumento;
use FiscalLib\Common\Enums\TipoManifestacao;
use FiscalLib\Common\ValueObjects\Cnpj;
use FiscalLib\Config\FiscalConfig;
use FiscalLib\Documento\Destinatario;
use FiscalLib\Documento\Endereco;
use FiscalLib\Documento\InutilizacaoPedido;
use FiscalLib\Documento\ItemFiscal;
use FiscalLib\Exceptions\ApiHttpException;
use FiscalLib\Exceptions\EstadoInvalidoException;
use FiscalLib\Exceptions\ValidacaoApiException;
use FiscalLib\FiscalLib;
use FiscalLib\Tax\Contextos\NfeTaxContext;
use FiscalLib\Tax\Contextos\NfseTaxContext;

require __DIR__ . '/../../vendor/autoload.php';

$base = rtrim(getenv('FISCAL_BASE_URL') ?: 'http://localhost:8080', '/');
$adminEmail = getenv('ADMIN_EMAIL') ?: 'admin@fiscal.local';
$adminSenha = getenv('ADMIN_PASSWORD') ?: throw new RuntimeException('Defina ADMIN_PASSWORD.');

$passos = 0;
function passo(string $titulo): void
{
    global $passos;
    $passos++;
    printf("\n[%02d] %s\n", $passos, $titulo);
}
function falha(string $msg): never
{
    fwrite(STDERR, "FALHOU: {$msg}\n");
    exit(1);
}
function ok(string $msg): void
{
    echo "  ✔ {$msg}\n";
}

// ---------------------------------------------------------------- bootstrap
passo('Bootstrap: login admin → tenant → api key');
$login = http_post_json("{$base}/v1/admin/auth/login", ['email' => $adminEmail, 'senha' => $adminSenha]);
$token = $login['token'] ?? ($login['accessToken'] ?? null);
$token !== null || falha('login admin sem token: ' . json_encode($login));

$tenantResp = http_post_json("{$base}/v1/admin/tenants", [
    'cnpj' => '11444777000161',
    'razaoSocial' => 'Empresa E2E LTDA',
    'uf' => 'PR',
    'codigoMunicipioIbge' => '4106902',
    'regimeTributario' => 3,
    'ambientePadrao' => 'homologacao',
    'inscricaoEstadual' => '12345678',
    'logradouro' => 'Avenida Teste',
    'numero' => '100',
    'bairro' => 'Centro',
    'cep' => '80000000',
    'nomeMunicipio' => 'Curitiba',
], $token, permitir409: true);
// A API pode devolver o objeto completo ou só o GUID (201 "id").
$tenantId = is_array($tenantResp) ? ($tenantResp['id'] ?? null) : $tenantResp;
if ($tenantId === null || isset($tenantResp['__http409'])) {
    // 409 — CNPJ já existe: localiza na lista de tenants.
    $lista = http_get_json("{$base}/v1/admin/tenants", $token);
    foreach ($lista as $t) {
        if (($t['cnpj'] ?? null) === '11444777000161') {
            $tenantId = $t['id'];
            break;
        }
    }
}
$tenantId !== null || falha('tenant não localizado após 409: ' . json_encode($tenantResp));
ok("tenant {$tenantId}");

$keyResp = http_post_json("{$base}/v1/admin/tenants/{$tenantId}/api-keys", [
    'descricao' => 'E2E FiscalLIB', 'ambiente' => 'homologacao',
], $token);
$apiKey = $keyResp['chave'] ?? falha('api key sem chave: ' . json_encode($keyResp));
ok('api key ' . substr($apiKey, 0, 12) . '...');

// --------------------------------------------------------------------- lib
$lib = FiscalLib::comFiscalApi(FiscalConfig::criar($base, $apiKey, Ambiente::Homologacao));

function item(float $qtd, float $unit, \FiscalLib\Tax\Resultados\NfeTaxResultado $tributos, string $cfop): ItemFiscal
{
    return new ItemFiscal(
        codigo: 'SKU-E2E',
        descricao: 'Produto E2E',
        quantidade: number_format($qtd, 4, '.', ''),
        valorUnitario: number_format($unit, 2, '.', ''),
        valorTotal: number_format($qtd * $unit, 2, '.', ''),
        tributos: $tributos,
        ncm: '12345678',
        cfop: $cfop,
        gtin: 'SEM GTIN',
        unidade: 'UN',
    );
}

/** @throws ValidacaoApiException|ApiHttpException */
function aguardar(FiscalLib $lib, string $id, string $esperado): \FiscalLib\Documento\ResultadoEmissao
{
    $resultado = $lib->aguardarTerminal($id);
    $resultado->status === $esperado || falha("status {$resultado->status}, esperado {$esperado} (motivo: {$resultado->motivoStatus})");

    return $resultado;
}

// ------------------------------------------------------------------- NFC-e
passo('Emitir NFC-e (CSOSN 102, sem destinatário)');
$nfeEngine = $lib->taxEngine();
$tributosNfce = $nfeEngine->calcularNfe(
    NfeTaxContext::make()->valores(1, 25.50)->icms(OrigemMercadoria::Nacional, Csosn::TributadaSemPermissaoDeCredito)
);
$nfce = $lib->nfce()->novo()
    ->naturezaOperacao('Venda balcao E2E')
    ->addItem(item(1, 25.50, $tributosNfce, '5102'))
    ->pagamento(FormaPagamento::Dinheiro, 25.50)
    ->build();
$aceite = $lib->nfce()->emitirAsync($nfce);
ok("aceite 202: id={$aceite->documentoId} status={$aceite->status}");
$resultadoNfce = aguardar($lib, $aceite->documentoId, 'AUTORIZADA');
ok('chave 44: ' . $resultadoNfce->chaveAcesso);
strlen($resultadoNfce->chaveAcesso ?? '') === 44 || falha('chave NFC-e não tem 44 posições');

// ---------------------------------------------------- idempotência (replay)
passo('Replay de idempotência (mesma key não cria novo documento)');
$key = \FiscalLib\Contracts\OpcoesEmissao::comIdempotencia('e2e-idem-' . bin2hex(random_bytes(6)));
$aceiteA = $lib->nfce()->emitirAsync($nfce, $key);
$aceiteB = $lib->nfce()->emitirAsync($nfce, $key);
$aceiteA->documentoId === $aceiteB->documentoId || falha('replay criou segundo documento!');
ok("mesmo documento em ambas chamadas: {$aceiteA->documentoId}");

// -------------------------------------------------------------------- NF-e
passo('Emitir NF-e (CST 00 + PIS/COFINS + destinatário + frete)');
$tributosNfe = $nfeEngine->calcularNfe(
    NfeTaxContext::make()->valores(3, 40)->icms(OrigemMercadoria::Nacional, CstIcms::TributadaIntegralmente, aliquota: 18, fcp: 2)
        ->ipi(CstIpi::SaidaTributada, aliquota: 5)
        ->pis(CstPisCofins::OperacaoTributavelCumulativo, '1.65')->cofins(CstPisCofins::OperacaoTributavelCumulativo, '7.60')
);
$nfe = $lib->nfe()->novo()
    ->naturezaOperacao('Venda de mercadoria E2E')
    ->destinatario(new Destinatario(
        Cnpj::criar('45997418000153'),
        'Comprador E2E LTDA',
        inscricaoEstadual: 'ISENTO',
        endereco: new Endereco(cep: '01001000', logradouro: 'Praça da Sé', numero: '1', bairro: 'Sé', codigoMunicipioIbge: '3550308', uf: UF::SP, nomeMunicipio: 'São Paulo'),
    ))
    ->addItem(item(3, 40, $tributosNfe, '6102'))
    ->frete(15)
    ->pagamento(FormaPagamento::CartaoCredito, 135)
    ->build();
$valorNotaEsperado = 120 + 15 + 6; // brutos + frete + IPI (fórmula v2; ST/FCP-ST = 0)
abs((float) $nfe->totais->valorNota - $valorNotaEsperado) < 0.01 || falha('valorNota v2 errado: ' . $nfe->totais->valorNota);
ok("totais v2: valorNota={$nfe->totais->valorNota} (brutos 120 + frete 15 + IPI 6)");

$aceiteNfe = $lib->nfe()->emitirAsync($nfe);
$resultadoNfe = aguardar($lib, $aceiteNfe->documentoId, 'AUTORIZADA');
$resultadoNfe->protocoloAutorizacao !== null || falha('NF-e autorizada sem protocolo');
ok("protocolo {$resultadoNfe->protocoloAutorizacao}");

// ---------------------------------------------------------------------- PDF
passo('Baixar DANFE (binário)');
$pdf = $lib->nfe()->baixarPdf($resultadoNfe->documentoId);
str_starts_with($pdf->bytes(), '%PDF') || falha('PDF não começa com %PDF');
ok('PDF válido: ' . strlen($pdf->bytes()) . ' bytes');

// ------------------------------------------------------------- cancelamento
passo('Cancelar NF-e autorizada');
$evento = $lib->eventos()->cancelar($resultadoNfe->documentoId, 'E2E: cancelamento automatico da nota de teste');
ok("evento {$evento->tipo} ({$evento->status})");
aguardar($lib, $resultadoNfe->documentoId, 'CANCELADA');
ok('documento CANCELADA');

// --------------------------------------------------------------------- CC-e
passo('Carta de correção em nova NF-e');
$aceiteNfe2 = $lib->nfe()->emitirAsync($nfe);
$resultadoNfe2 = aguardar($lib, $aceiteNfe2->documentoId, 'AUTORIZADA');
$cce = $lib->eventos()->cartaCorrecao($resultadoNfe2->documentoId, 'E2E: corrige a descricao do frete destacado');
$cce->tipo === 'CCE' || falha('evento CC-e com tipo errado: ' . $cce->tipo);
ok("CC-e registrada ({$cce->eventoId})");

// ------------------------------------------------------------ inutilização
passo('Inutilizar faixa de numeração');
$inut = $lib->eventos()->inutilizar(new InutilizacaoPedido(
    Ambiente::Homologacao, ModeloDocumento::Nfe, 1, 990, 991, 'E2E: faixa de teste inutilizada'
));
$inutConsulta = $lib->eventos()->consultarInutilizacao($inut->eventoId);
ok("inutilização {$inut->eventoId} status {$inutConsulta->status}");

// ------------------------------------------------------------------ NFS-e
passo('Emitir NFS-e Nacional (DPS, ISS retido, IBSCBS)');
$tributosNfse = $lib->taxEngine()->calcularNfse(
    NfseTaxContext::make()
        ->servico(1000)
        ->iss(5, tributacao: 1, retencao: 2)
        ->pisCofins('01', 0.65, 3.0)
);
$nfse = $lib->nfse()->novo()
    ->ambiente(Ambiente::Homologacao)
    ->serie(1)
    ->competencia(date('Y-m-d'))
    ->tomador(new \FiscalLib\Documento\Tomador(
        Cnpj::criar('45997418000153'),
        'Tomador E2E LTDA',
        endereco: new Endereco(cep: '01001000', logradouro: 'Praça da Sé', numero: '1', bairro: 'Sé', codigoMunicipioIbge: '3550308'),
    ))
    ->servico(new \FiscalLib\Documento\ServicoFiscal('010701', 'Desenvolvimento de software E2E', codigoNbs: '112011000'))
    ->tributos($tributosNfse)
    ->ibsCbs(new \FiscalLib\Documento\IbsCbsDps('000001', '101', '000001'))
    ->build();
$aceiteNfse = $lib->nfse()->emitirAsync($nfse);
$resultadoNfse = aguardar($lib, $aceiteNfse->documentoId, 'AUTORIZADA');
strlen($resultadoNfse->chaveAcesso ?? '') > 0 || falha('NFS-e sem chave');
ok("NFS-e autorizada, chave: {$resultadoNfse->chaveAcesso}");

// ------------------------------------------------------------- status-serviço
passo('Status-serviço (gestão)');
$status = $lib->gestao()->statusServico(ModeloDocumento::Nfe, Ambiente::Homologacao);
isset($status['cStat']) || falha('status-servico sem cStat: ' . json_encode($status));
ok("cStat {$status['cStat']} — {$status['xMotivo']}");

// --------------------------------------------------------------- 422 (erro)
passo('Inconsistência aritmética → 422 com campo');
try {
    $itemRuim = new ItemFiscal('SKU-BAD', 'Produto inválido', '2.0000', '50.00', '90.00');
    $docRuim = $lib->nfe()->novo()->naturezaOperacao('Venda ruim')->addItem($itemRuim)->pagamento(FormaPagamento::Dinheiro, 90)->build();
    // build() já bloquearia; força o payload diretamente com valorTotal incoerente:
    $payload = (new \FiscalLib\Adapters\FiscalApi\MapeadorDocumento())->paraEmissaoRequest($docRuim);
    falha('builder deveria ter bloqueado item incoerente');
} catch (\FiscalLib\Exceptions\ValidationException $e) {
    ok('builder bloqueou localmente: ' . array_key_first($e->erros()));
}

passo('422 do servidor com payload montado na mão (qtd×unit ≠ valorTotal)');
$payloadRuim = [
    'ambiente' => 'homologacao', 'serie' => 1,
    'itens' => [[
        'codigo' => 'SKU-BAD', 'descricao' => 'Produto inválido',
        'quantidade' => 2, 'valorUnitario' => 50, 'valorTotal' => 90,
        'totais' => null,
    ]],
    'totais' => ['valorProdutos' => 90, 'valorNota' => 90],
];
try {
    $resp = (new \FiscalLib\Adapters\FiscalApi\ClienteHttp(
        FiscalConfig::criar($base, $apiKey)
    ))->post('/v1/documentos-fiscais/nfe', $payloadRuim, 'e2e-422-' . bin2hex(random_bytes(4)));
    falha('API aceitou payload incoerente: ' . json_encode($resp));
} catch (ValidacaoApiException $e) {
    ok("422 capturado: campo={$e->campo} — {$e->getMessage()}");
}

// --------------------------------------------------------------- 409 (erro)
passo('Cancelar NF-e já cancelada → 409');
try {
    $lib->eventos()->cancelar($resultadoNfe->documentoId, 'E2E: segundo cancelamento deve falhar');
    falha('409 esperado');
} catch (EstadoInvalidoException $e) {
    ok('409 capturado: ' . $e->getMessage());
}

// ------------------------------------------------------------------ resumo
printf("\n=== E2E CONCLUÍDO: %d passos, todos OK ===\n", $passos);

// ------------------------------------------------------------------ helpers

function http_post_json(string $url, array $corpo, ?string $bearer = null, bool $permitir409 = false): mixed
{
    $ch = curl_init($url);
    $headers = ['Content-Type: application/json'];
    if ($bearer !== null) {
        $headers[] = "Authorization: Bearer {$bearer}";
    }
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($corpo),
        CURLOPT_HTTPHEADER => $headers,
    ]);
    $corpoResp = (string) curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if ($permitir409 && $status === 409) {
        return ['__http409' => true];
    }
    if ($status >= 400) {
        falha("POST {$url} → HTTP {$status}: {$corpoResp}");
    }

    // 200/201/202: objeto, lista ou até "guid" como string JSON.
    $dados = json_decode($corpoResp, true);

    return $dados === null ? $corpoResp : $dados;
}

function http_get_json(string $url, string $bearer): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ["Authorization: Bearer {$bearer}"],
    ]);
    $corpoResp = (string) curl_exec($ch);
    curl_close($ch);

    $dados = json_decode($corpoResp, true);

    return is_array($dados) ? $dados : [];
}
