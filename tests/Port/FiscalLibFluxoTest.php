<?php

declare(strict_types=1);

namespace FiscalLib\Tests\Port;

use FiscalLib\Common\Enums\Ambiente;
use FiscalLib\Common\Enums\FormaPagamento;
use FiscalLib\Config\FiscalConfig;
use FiscalLib\Contracts\OpcoesEmissao;
use FiscalLib\Documento\ItemFiscal;
use FiscalLib\Documento\NfeDocumento;
use FiscalLib\Documento\ResultadoEmissao;
use FiscalLib\Exceptions\ApiIndisponivelException;
use FiscalLib\Exceptions\RejeicaoSefazException;
use FiscalLib\FiscalLib;
use FiscalLib\Nfe\NfeBuilder;
use FiscalLib\Servicos\AguardadorTerminal;
use PHPUnit\Framework\TestCase;

/**
 * O núcleo (FiscalLib + builders + serviços) funciona contra QUALQUER
 * EmissorInterface — aqui provado com o EmissorFake, sem HTTP.
 */
final class FiscalLibFluxoTest extends TestCase
{
    private function documento(): NfeDocumento
    {
        return NfeBuilder::nfe()
            ->ambiente(Ambiente::Homologacao)
            ->serie(1)
            ->naturezaOperacao('Venda de mercadoria')
            ->addItem(new ItemFiscal('SKU1', 'Produto', '1.0000', '100.00', '100.00'))
            ->pagamento(FormaPagamento::Dinheiro, 100)
            ->build();
    }

    /** Config sem espera real (intervalos 0) para polling instantâneo nos testes. */
    private function configRapida(): FiscalConfig
    {
        return new FiscalConfig('http://fake', 'fk_test_fake', null, 5, 1, [0, 0, 0], 60);
    }

    public function testEmissaoCompletaAteTerminal(): void
    {
        $fake = new EmissorFake();
        $fake->filaConsultas = [
            new ResultadoEmissao('doc-1', 'PENDENTE'),
            new ResultadoEmissao('doc-1', 'PROCESSANDO'),
            new ResultadoEmissao('doc-1', 'AUTORIZADA', null, 'NFE', 'homologacao', 1, 42, '41260912345678000199550010000000421012345678'),
        ];

        $lib = new FiscalLib($fake, $this->configRapida());
        $resultado = $lib->nfe()->emitir($this->documento());

        self::assertSame('AUTORIZADA', $resultado->status);
        self::assertTrue($resultado->isAutorizada());
        self::assertTrue($resultado->isTerminal());
        self::assertSame('41260912345678000199550010000000421012345678', $resultado->chaveAcesso);
        self::assertSame([], $fake->filaConsultas, 'deve consultar até esgotar a fila (3 estados)');
        self::assertCount(1, $fake->emissoes);
    }

    public function testEmissaoRejeitadaNaoLancaMasExigirAutorizadaSim(): void
    {
        $fake = new EmissorFake();
        $fake->filaConsultas = [
            new ResultadoEmissao('doc-1', 'REJEITADA', null, 'NFE', 'homologacao', 1, 42, null, null, null,
                '<retEnviNFe><cStat>539</cStat></retEnviNFe>', 'Rejeição 539: Duplicidade de NF-e'),
        ];

        $lib = new FiscalLib($fake, $this->configRapida());
        $resultado = $lib->nfe()->emitir($this->documento());

        self::assertFalse($resultado->isAutorizada());
        self::assertTrue($resultado->isRejeicaoSefaz());

        try {
            $resultado->exigirAutorizada();
            self::fail('Deveria ter lançado RejeicaoSefazException.');
        } catch (RejeicaoSefazException $e) {
            self::assertSame('539', $e->codigoRejeicao);
            self::assertStringContainsString('Duplicidade', $e->getMessage());
        }
    }

    public function testIdempotencyKeyFornecidaERepassada(): void
    {
        $fake = new EmissorFake();
        $lib = new FiscalLib($fake);

        $lib->nfe()->emitirAsync($this->documento(), OpcoesEmissao::comIdempotencia('chave-do-pedido-42'));

        self::assertSame('chave-do-pedido-42', $fake->emissoes[0]['opcoes']->idempotencyKey);
    }

    public function testEventosPassamPelaPorta(): void
    {
        $fake = new EmissorFake();
        $lib = new FiscalLib($fake);

        $evento = $lib->eventos()->cancelar('doc-1', 'Pedido cancelado pelo cliente antes do faturamento');

        self::assertSame('CANCELAMENTO', $evento->tipo);
        self::assertSame(['doc-1'], $fake->cancelamentos);
    }

    public function testGestaoIndisponivelParaEmissorGenerico(): void
    {
        $lib = new FiscalLib(new EmissorFake());

        self::assertNull($lib->gestao());
    }

    public function testGestaoDisponivelParaFiscalApi(): void
    {
        $lib = FiscalLib::comFiscalApi(FiscalConfig::criar('http://localhost:8080', 'fk_test_x'));

        self::assertNotNull($lib->gestao());
    }

    public function testAguardadorTimeoutComEstadoNuncaTerminal(): void
    {
        $sempreProcessando = new class extends EmissorFake {
            public function consultar(string $documentoId): ResultadoEmissao
            {
                return new ResultadoEmissao($documentoId, 'PROCESSANDO');
            }
        };

        $aguardador = new AguardadorTerminal($sempreProcessando, [1], 1, static function (int $s): void {
            \usleep(600_000);
        });

        $this->expectException(ApiIndisponivelException::class);
        $aguardador->aguardar('doc-1');
    }
}
