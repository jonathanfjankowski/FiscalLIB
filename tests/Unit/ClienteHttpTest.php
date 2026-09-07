<?php

declare(strict_types=1);

namespace FiscalLib\Tests\Unit;

use FiscalLib\Adapters\FiscalApi\ClienteHttp;
use FiscalLib\Adapters\FiscalApi\GestaoFiscalApi;
use FiscalLib\Common\Enums\StatusDocumento;
use FiscalLib\Config\FiscalConfig;
use FiscalLib\Exceptions\ApiIndisponivelException;
use FiscalLib\Exceptions\AutenticacaoException;
use FiscalLib\Exceptions\EstadoInvalidoException;
use FiscalLib\Exceptions\LimiteRequisicoesException;
use FiscalLib\Exceptions\ValidacaoApiException;
use FiscalLib\Tests\Fake\Psr18Fake;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

final class ClienteHttpTest extends TestCase
{
    private Psr18Fake $fake;

    private ClienteHttp $cliente;

    protected function setUp(): void
    {
        $this->fake = new Psr18Fake();
        $this->cliente = new ClienteHttp(
            FiscalConfig::criar('http://api.test', 'fk_test_abc', null),
            $this->fake,
            null,
            null,
            static fn (int $t) => null, // sem espera real nos retries
        );
    }

    public function testPostComAuthEIdempotencia(): void
    {
        $this->fake->enviarNova(new Response(202, ['Content-Type' => 'application/json'], '{"id":"abc","status":"PENDENTE"}'));

        $dados = $this->cliente->post('/v1/documentos-fiscais/nfe', ['ambiente' => 'homologacao'], 'idem-1');

        self::assertSame(['id' => 'abc', 'status' => 'PENDENTE'], $dados);

        $req = $this->fake->requisicoes[0];
        self::assertSame('ApiKey fk_test_abc', $req->getHeaderLine('Authorization'));
        self::assertSame('idem-1', $req->getHeaderLine('Idempotency-Key'));
        self::assertSame('application/json', $req->getHeaderLine('Content-Type'));
        self::assertStringContainsString('"ambiente":"homologacao"', (string) $req->getBody());
    }

    public function test422ComCampoViraValidacaoApiException(): void
    {
        $this->fake->enviarNova(new Response(422, ['Content-Type' => 'application/problem+json'], json_encode([
            'title' => 'Inconsistência nos valores do documento',
            'status' => 422,
            'detail' => 'Item SKU1: 2 × 50,00 = 100,00, recebido 90,00.',
            'campo' => 'itens[0].valorTotal',
        ])));

        try {
            $this->cliente->post('/v1/documentos-fiscais/nfe', [], 'k');
            self::fail('Deveria lançar ValidacaoApiException.');
        } catch (ValidacaoApiException $e) {
            self::assertSame(422, $e->status);
            self::assertSame('itens[0].valorTotal', $e->campo);
            self::assertStringContainsString('recebido 90,00', $e->getMessage());
        }
    }

    public function testValidationProblemDetailsComDicionario(): void
    {
        $this->fake->enviarNova(new Response(400, ['Content-Type' => 'application/problem+json'], json_encode([
            'title' => 'One or more validation errors occurred.',
            'status' => 400,
            'errors' => ['Itens' => ['A nota deve ter ao menos um item.']],
        ])));

        try {
            $this->cliente->post('/v1/documentos-fiscais/nfe', [], 'k');
            self::fail('Deveria lançar ValidacaoApiException.');
        } catch (ValidacaoApiException $e) {
            self::assertSame(['Itens' => ['A nota deve ter ao menos um item.']], $e->errosPorCampo);
        }
    }

    public function test409ViraEstadoInvalido(): void
    {
        $this->fake->enviarNova(new Response(409, ['Content-Type' => 'application/problem+json'],
            '{"title":"Documento não está em estado válido para este evento.","status":409,"detail":"Status atual: REJEITADA."}'));

        $this->expectException(EstadoInvalidoException::class);
        $this->cliente->post('/v1/documentos-fiscais/x/cancelamento', ['justificativa' => 'justificativa qualquer'], 'k');
    }

    public function test401ViraAutenticacaoException(): void
    {
        $this->fake->enviarNova(new Response(401));

        $this->expectException(AutenticacaoException::class);
        $this->cliente->get('/v1/documentos-fiscais/x');
    }

    public function testRetry5xxComMesmaRequisicao(): void
    {
        $this->fake->enviarNova(new Response(500));
        $this->fake->enviarNova(new Response(202, [], '{"id":"ok","status":"PENDENTE"}'));

        $dados = $this->cliente->post('/v1/documentos-fiscais/nfe', [], 'idem-retry');

        self::assertSame('ok', $dados['id']);
        self::assertCount(2, $this->fake->requisicoes);
        self::assertSame('idem-retry', $this->fake->requisicoes[1]->getHeaderLine('Idempotency-Key'), 'retry usa a MESMA key');
    }

    public function test429EsgotadoViraLimiteRequisicoes(): void
    {
        $this->fake->enviarNova(new Response(429));
        $this->fake->enviarNova(new Response(429));
        $this->fake->enviarNova(new Response(429));

        $this->expectException(LimiteRequisicoesException::class);
        $this->cliente->post('/v1/documentos-fiscais/nfe', [], 'k');
    }

    public function test5xxEsgotadoViraIndisponivel(): void
    {
        $this->fake->enviarNova(new Response(503));
        $this->fake->enviarNova(new Response(503));
        $this->fake->enviarNova(new Response(503));

        $this->expectException(ApiIndisponivelException::class);
        $this->cliente->get('/v1/documentos-fiscais/x');
    }

    public function testGestaoStatusServicoMontaQuery(): void
    {
        $gestao = new GestaoFiscalApi(FiscalConfig::criar('http://api.test', 'fk_test_abc'), $this->fake);
        $this->fake->enviarNova(new Response(200, [], '{"cStat":"107","xMotivo":"Serviço em Operação"}'));

        $dados = $gestao->statusServico(\FiscalLib\Common\Enums\ModeloDocumento::Nfe, \FiscalLib\Common\Enums\Ambiente::Homologacao);

        self::assertSame('107', $dados['cStat']);
        self::assertStringContainsString('modelo=55', (string) $this->fake->requisicoes[0]->getUri());
        self::assertStringContainsString('ambiente=homologacao', (string) $this->fake->requisicoes[0]->getUri());
    }

    public function testMapeadorResultadoStatusDesconhecidoNaoExplode(): void
    {
        $mapeador = new \FiscalLib\Adapters\FiscalApi\MapeadorResultado();
        $resultado = $mapeador->paraResultadoEmissao(['id' => '1', 'status' => 'STATUS_FUTURO']);

        self::assertSame('STATUS_FUTURO', $resultado->status);
        self::assertNull($resultado->statusEnum());
        self::assertFalse($resultado->isTerminal());
        self::assertSame(StatusDocumento::Pendente->value, 'PENDENTE');
    }
}
