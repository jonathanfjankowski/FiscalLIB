<?php

declare(strict_types=1);

namespace FiscalLib\Tests\Integration;

use FiscalLib\Adapters\FiscalApi\MapeadorDocumento;
use FiscalLib\Common\Enums\Ambiente;
use FiscalLib\Common\Enums\ModeloDocumento;
use FiscalLib\Common\ValueObjects\Cnpj;
use FiscalLib\Config\FiscalConfig;
use FiscalLib\Contracts\OpcoesEmissao;
use FiscalLib\Documento\Destinatario;
use FiscalLib\Documento\Endereco;
use FiscalLib\Documento\InutilizacaoPedido;
use FiscalLib\Documento\IbsCbsDps;
use FiscalLib\Documento\ItemFiscal;
use FiscalLib\Documento\NfeDocumento;
use FiscalLib\Documento\NfseDocumento;
use FiscalLib\Documento\ServicoFiscal;
use FiscalLib\Documento\Tomador;
use FiscalLib\Exceptions\EstadoInvalidoException;
use FiscalLib\Exceptions\ValidacaoApiException;
use FiscalLib\FiscalLib;
use FiscalLib\Tax\Contextos\NfeTaxContext;
use FiscalLib\Tax\Contextos\NfseTaxContext;
use PHPUnit\Framework\TestCase;

/**
 * Integração ponta a ponta contra a FiscalAPI em sandbox (ModoSandbox=true).
 *
 * COMO RODAR:
 *   1. Suba a stack (tests/E2E/run-api.sh + run-worker.sh, ou docker compose)
 *   2. export ADMIN_PASSWORD=... (login do painel admin da API)
 *   3. vendor/bin/phpunit --testsuite Integration
 *
 * Sem a API no ar ou sem ADMIN_PASSWORD, a suíte se AUTO-PULA — o `composer
 * test` fica verde em qualquer máquina.
 *
 * @group integration
 */
final class SandboxE2eTest extends TestCase
{
    private const BASE_URL = 'http://localhost:8080';
    private const CNPJ_TENANT = '11444777000161';

    private static FiscalLib $lib;
    private static string $apiKey;

    public static function setUpBeforeClass(): void
    {
        $base = getenv('FISCAL_BASE_URL') ?: self::BASE_URL;
        $adminSenha = getenv('ADMIN_PASSWORD');

        $pronto = false;
        $ch = curl_init($base . '/health/ready');
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 3]);
        curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        $pronto = $status === 200;

        if (! $pronto || $adminSenha === false || $adminSenha === '') {
            self::markTestSkipped(
                'FiscalAPI sandbox indisponível em ' . $base .
                ' (ou ADMIN_PASSWORD ausente). Suba a stack e rode: vendor/bin/phpunit --testsuite Integration'
            );
        }

        [$tenantId] = self::bootstrapTenantEKey($base, trim((string) $adminSenha));
        self::$lib = FiscalLib::comFiscalApi(
            new FiscalConfig(rtrim($base, '/'), $tenantId['apiKey'], Ambiente::Homologacao, 15, 2, [1, 2, 3, 5, 10], 120)
        );
        self::$apiKey = $tenantId['apiKey'];
    }

    /** @return array{0: array{id: string, apiKey: string}} */
    private static function bootstrapTenantEKey(string $base, string $adminSenha): array
    {
        [$login] = self::httpPost($base . '/v1/admin/auth/login', ['email' => 'admin@fiscal.local', 'senha' => $adminSenha]);
        $token = $login['token'] ?? null;
        if ($token === null) {
            self::markTestSkipped('Login admin falhou — confira ADMIN_PASSWORD.');
        }

        // Tenant é idempotente por CNPJ (409 → busca na lista).
        [$resp, $status] = self::httpPost($base . '/v1/admin/tenants', [
            'cnpj' => self::CNPJ_TENANT,
            'razaoSocial' => 'Empresa Integracao PHPUnit',
            'uf' => 'PR',
            'codigoMunicipioIbge' => '4106902',
            'regimeTributario' => 3,
            'ambientePadrao' => 'homologacao',
            'logradouro' => 'Avenida Teste',
            'numero' => '100',
            'bairro' => 'Centro',
            'cep' => '80000000',
            'nomeMunicipio' => 'Curitiba',
        ], $token);

        if ($status === 409) {
            $lista = self::httpGet($base . '/v1/admin/tenants', $token);
            foreach ($lista as $t) {
                if (($t['cnpj'] ?? null) === self::CNPJ_TENANT) {
                    $resp = $t['id'];
                    break;
                }
            }
        }
        $tenantId = is_array($resp) ? ($resp['id'] ?? null) : $resp;
        if ($tenantId === null) {
            self::markTestSkipped('Não foi possível obter/criar o tenant de integração.');
        }

        [$key] = self::httpPost($base . '/v1/admin/tenants/' . $tenantId . '/api-keys', [
            'descricao' => 'PHPUnit FiscalLIB', 'ambiente' => 'homologacao',
        ], $token);

        return [['id' => (string) $tenantId, 'apiKey' => (string) ($key['chave'] ?? '')]];
    }

    // ------------------------------------------------------------------ testes

    public function testNfceAutorizadaComChave44(): void
    {
        $tributos = self::$lib->taxEngine()->calcularNfe(
            NfeTaxContext::make()->valores(1, 25.50)->icms(0, csosn: '102')
        );
        $documento = self::$lib->nfce()->novo()
            ->naturezaOperacao('Venda balcao integracao')
            ->addItem(self::item(1, 25.50, $tributos, '5102'))
            ->pagamento('01', 25.50)
            ->build();

        $resultado = self::$lib->nfce()->emitir($documento);

        self::assertSame('AUTORIZADA', $resultado->status);
        self::assertNotNull($resultado->chaveAcesso);
        self::assertSame(44, strlen($resultado->chaveAcesso));
        self::assertNotNull($resultado->protocoloAutorizacao);
    }

    public function testIdempotenciaReplayNaoCriaSegundoDocumento(): void
    {
        $documento = self::nfeSimples();
        $key = OpcoesEmissao::comIdempotencia('phpunit-idem-' . bin2hex(random_bytes(6)));

        $a = self::$lib->nfe()->emitirAsync($documento, $key);
        $b = self::$lib->nfe()->emitirAsync($documento, $key);

        self::assertSame($a->documentoId, $b->documentoId);
    }

    public function testNfeCompletaTotaisV2PdfECancelamento(): void
    {
        $tributos = self::$lib->taxEngine()->calcularNfe(
            NfeTaxContext::make()->valores(3, 40)->icms(0, cst: '00', aliquota: 18, fcp: 2)
                ->ipi('50', aliquota: 5)
                ->pis('01', '1.65')->cofins('01', '7.60')
        );
        $documento = self::$lib->nfe()->novo()
            ->naturezaOperacao('Venda de mercadoria integracao')
            ->destinatario(new Destinatario(
                Cnpj::criar('45997418000153'),
                'Comprador Integracao LTDA',
                inscricaoEstadual: 'ISENTO',
                endereco: new Endereco(cep: '01001000', logradouro: 'Praça da Sé', numero: '1', bairro: 'Sé', codigoMunicipioIbge: '3550308', uf: 'SP'),
            ))
            ->addItem(self::item(3, 40, $tributos, '6102'))
            ->frete(15)
            ->pagamento('03', 141)
            ->build();

        // Fórmula v2: 120 brutos + 15 frete + 6 IPI (5% de 120)
        self::assertSame('141.00', $documento->totais->valorNota);

        $resultado = self::$lib->nfe()->emitir($documento);

        self::assertSame('AUTORIZADA', $resultado->status);
        self::assertNotNull($resultado->protocoloAutorizacao);

        // DANFE
        $pdf = self::$lib->nfe()->baixarPdf($resultado->documentoId);
        self::assertStringStartsWith('%PDF', $pdf->bytes());

        // Cancelamento → terminal CANCELADA
        $evento = self::$lib->eventos()->cancelar($resultado->documentoId, 'Integracao: cancelamento automatico da nota');
        self::assertSame('CANCELAMENTO', $evento->tipo);
        $cancelada = self::$lib->aguardarTerminal($resultado->documentoId);
        self::assertSame('CANCELADA', $cancelada->status);
    }

    public function testCartaCorrecaoEmNfeAutorizada(): void
    {
        $resultado = self::$lib->nfe()->emitir(self::nfeSimples());
        self::assertSame('AUTORIZADA', $resultado->status);

        $cce = self::$lib->eventos()->cartaCorrecao($resultado->documentoId, 'Integracao: corrige a descricao do frete destacado');

        self::assertSame('CCE', $cce->tipo);
        self::assertNotSame('', $cce->eventoId);
    }

    public function testInutilizacaoDeFaixa(): void
    {
        $inut = self::$lib->eventos()->inutilizar(new InutilizacaoPedido(
            Ambiente::Homologacao, ModeloDocumento::Nfe, 1, random_int(900, 980), random_int(981, 999), 'Integracao: faixa de teste inutilizada'
        ));
        $consulta = self::$lib->eventos()->consultarInutilizacao($inut->eventoId);

        self::assertSame('INUTILIZACAO', $consulta->tipo);
    }

    public function testNfseDpsAutorizada(): void
    {
        $tributos = self::$lib->taxEngine()->calcularNfse(
            NfseTaxContext::make()->servico(1000)->iss(5, tributacao: 1, retencao: 2)->pisCofins('01', 0.65, 3.0)
        );
        $documento = self::$lib->nfse()->novo()
            ->ambiente(Ambiente::Homologacao)
            ->serie(1)
            ->competencia(gmdate('Y-m-d'))
            ->tomador(new Tomador(
                Cnpj::criar('45997418000153'),
                'Tomador Integracao LTDA',
                endereco: new Endereco(cep: '01001000', logradouro: 'Praça da Sé', numero: '1', bairro: 'Sé', codigoMunicipioIbge: '3550308'),
            ))
            ->servico(new ServicoFiscal('010701', 'Desenvolvimento de software integracao', codigoNbs: '112011000'))
            ->tributos($tributos)
            ->ibsCbs(new IbsCbsDps('000001', '101', '000001'))
            ->build();

        $resultado = self::$lib->nfse()->emitir($documento);

        self::assertSame('AUTORIZADA', $resultado->status);
        self::assertNotNull($resultado->chaveAcesso);
    }

    public function testErro422DoServidorExpoeCampo(): void
    {
        // Payload deliberadamente incoerente, montado na mão (o builder bloquearia antes).
        $payload = [
            'ambiente' => 'homologacao', 'serie' => 1,
            'itens' => [[
                'codigo' => 'SKU-BAD', 'descricao' => 'Produto inválido',
                'quantidade' => 2, 'valorUnitario' => 50, 'valorTotal' => 90,
            ]],
            'totais' => ['valorProdutos' => 90, 'valorNota' => 90],
        ];

        try {
            (new \FiscalLib\Adapters\FiscalApi\ClienteHttp(
                FiscalConfig::criar(getenv('FISCAL_BASE_URL') ?: self::BASE_URL, self::$apiKey)
            ))->post('/v1/documentos-fiscais/nfe', $payload, 'phpunit-422-' . bin2hex(random_bytes(4)));
            self::fail('A API aceitou payload incoerente.');
        } catch (ValidacaoApiException $e) {
            self::assertSame('itens[0].valorTotal', $e->campo);
        }
    }

    public function testErro409AoCancelarDocumentoCancelado(): void
    {
        $resultado = self::$lib->nfe()->emitir(self::nfeSimples());
        self::$lib->eventos()->cancelar($resultado->documentoId, 'Integracao: primeiro cancelamento valido');
        self::$lib->aguardarTerminal($resultado->documentoId);

        $this->expectException(EstadoInvalidoException::class);
        self::$lib->eventos()->cancelar($resultado->documentoId, 'Integracao: segundo cancelamento deve falhar');
    }

    public function testGestaoStatusServico(): void
    {
        $status = self::$lib->gestao()?->statusServico(ModeloDocumento::Nfe, Ambiente::Homologacao);

        self::assertNotNull($status);
        self::assertArrayHasKey('cStat', $status);
    }

    // ------------------------------------------------------------------ helpers

    private static function nfeSimples(): NfeDocumento
    {
        $tributos = self::$lib->taxEngine()->calcularNfe(
            NfeTaxContext::make()->valores(1, 100)->icms(0, cst: '00', aliquota: 18)
        );

        return self::$lib->nfe()->novo()
            ->naturezaOperacao('Venda de mercadoria integracao')
            ->destinatario(new Destinatario(Cnpj::criar('45997418000153'), 'Comprador Integracao LTDA'))
            ->addItem(self::item(1, 100, $tributos, '5102'))
            ->pagamento('01', 100)
            ->build();
    }

    private static function item(float $qtd, float $unit, \FiscalLib\Tax\Resultados\NfeTaxResultado $tributos, string $cfop): ItemFiscal
    {
        return new ItemFiscal(
            codigo: 'SKU-INTEGR',
            descricao: 'Produto integracao',
            quantidade: number_format($qtd, 4, '.', ''),
            valorUnitario: number_format($unit, 2, '.', ''),
            valorTotal: number_format($qtd * $unit, 2, '.', ''),
            tributos: $tributos,
            ncm: '12345678',
            cfop: $cfop,
        );
    }

    /** @return mixed */
    /** @return array{0: mixed, 1: int} [corpo decodificado, status HTTP] */
    private static function httpPost(string $url, array $corpo, ?string $bearer = null): array
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
            CURLOPT_TIMEOUT => 15,
        ]);
        $corpoResp = (string) curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        $dados = json_decode($corpoResp, true);

        return [$dados === null ? $corpoResp : $dados, $status];
    }

    /** @return list<array<string,mixed>> */
    private static function httpGet(string $url, string $bearer): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ["Authorization: Bearer {$bearer}"],
            CURLOPT_TIMEOUT => 15,
        ]);
        $corpoResp = (string) curl_exec($ch);
        curl_close($ch);
        $dados = json_decode($corpoResp, true);

        return is_array($dados) ? $dados : [];
    }
}
