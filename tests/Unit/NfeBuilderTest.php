<?php

declare(strict_types=1);

namespace FiscalLib\Tests\Unit;

use FiscalLib\Common\Enums\Ambiente;
use FiscalLib\Common\Enums\FormaPagamento;
use FiscalLib\Common\Enums\IndicadorConsumidorFinal;
use FiscalLib\Common\Enums\IndicadorIntermediador;
use FiscalLib\Common\ValueObjects\Cnpj;
use FiscalLib\Documento\Destinatario;
use FiscalLib\Documento\ItemFiscal;
use FiscalLib\Exceptions\ValidationException;
use FiscalLib\Nfe\NfeBuilder;
use FiscalLib\Tax\Contextos\NfeTaxContext;
use FiscalLib\Tax\Resultados\ImpostoTrioResultado;
use FiscalLib\Tax\Resultados\IcmsResultado;
use FiscalLib\Tax\Resultados\NfeTaxResultado;
use PHPUnit\Framework\TestCase;

final class NfeBuilderTest extends TestCase
{
    private function item(float $quantidade = 2, float $unitario = 50, ?NfeTaxResultado $tributos = null, ?string $cfop = '5102'): ItemFiscal
    {
        return new ItemFiscal(
            codigo: 'SKU1',
            descricao: 'Produto de teste',
            quantidade: number_format($quantidade, 4, '.', ''),
            valorUnitario: number_format($unitario, 2, '.', ''),
            valorTotal: number_format($quantidade * $unitario, 2, '.', ''),
            tributos: $tributos,
            cfop: $cfop,
        );
    }

    private function icms00(): NfeTaxResultado
    {
        return new NfeTaxResultado(
            icms: new IcmsResultado(origem: 0, cst: '00', modBc: '3', baseCalculo: '100.00', aliquota: '18.0000', valor: '18.00')
        );
    }

    public function testBuildCompleto(): void
    {
        $doc = NfeBuilder::nfe()
            ->ambiente(Ambiente::Homologacao)
            ->serie(1)
            ->naturezaOperacao('Venda de mercadoria')
            ->destinatario(new Destinatario(Cnpj::criar('11444777000161'), 'Cliente Teste Ltda'))
            ->addItem($this->item(tributos: $this->icms00()))
            ->pagamento(FormaPagamento::Dinheiro, 100)
            ->build();

        self::assertSame(100.0, (float) $doc->totais->valorNota);
        self::assertSame('100.00', $doc->totais->valorProdutos);
        self::assertCount(1, $doc->itens);
        self::assertNull($doc->indicadorIntermediador);
    }

    public function testIntermediadorMarketplace(): void
    {
        $doc = NfeBuilder::nfe()
            ->ambiente(Ambiente::Homologacao)
            ->serie(1)
            ->naturezaOperacao('Venda via marketplace')
            ->intermediador(IndicadorIntermediador::PlataformaTerceiros, '45.997.418/0001-53')
            ->destinatario(new Destinatario(Cnpj::criar('11444777000161'), 'Cliente Teste Ltda'))
            ->addItem($this->item(tributos: $this->icms00()))
            ->pagamento(FormaPagamento::Dinheiro, 100)
            ->build();

        self::assertSame(IndicadorIntermediador::PlataformaTerceiros, $doc->indicadorIntermediador);
        self::assertSame('45997418000153', $doc->cnpjIntermediador);
    }

    public function testIntermediadorSemIndicadorNaoSerializa(): void
    {
        $doc = NfeBuilder::nfe()
            ->ambiente(Ambiente::Homologacao)
            ->serie(1)
            ->naturezaOperacao('Venda direta')
            ->destinatario(new Destinatario(Cnpj::criar('11444777000161'), 'Cliente Teste Ltda'))
            ->addItem($this->item(tributos: $this->icms00()))
            ->pagamento(FormaPagamento::Dinheiro, 100)
            ->build();

        $payload = (new \FiscalLib\Adapters\FiscalApi\MapeadorDocumento())->paraEmissaoRequest($doc);

        self::assertArrayNotHasKey('indicadorIntermediador', $payload);
    }

    public function testIntermediadorSemCnpjFalha(): void
    {
        $this->expectException(ValidationException::class);
        NfeBuilder::nfe()->intermediador(IndicadorIntermediador::PlataformaTerceiros);
    }

    public function testSemNaturezaOperacaoFalha(): void
    {
        $this->expectException(ValidationException::class);
        NfeBuilder::nfe()->addItem($this->item())->build();
    }

    public function testItemQtdxUnitDivergenteFalha(): void
    {
        $item = new ItemFiscal('SKU1', 'Produto', '2.0000', '50.00', '90.00');

        $this->expectException(ValidationException::class);
        NfeBuilder::nfe()->naturezaOperacao('Venda')->addItem($item)->build();
    }

    public function testDevolucaoSemNfReferenciadaFalha(): void
    {
        $this->expectException(ValidationException::class);
        NfeBuilder::nfe()
            ->naturezaOperacao('Devolução de venda')
            ->finalidade(\FiscalLib\Common\Enums\FinalidadeNfe::Devolucao)
            ->addItem($this->item())
            ->build();
    }

    public function testCfopIncoerenteComTipoOperacaoFalha(): void
    {
        $this->expectException(ValidationException::class);
        NfeBuilder::nfe()
            ->naturezaOperacao('Venda')
            ->tipoOperacao(\FiscalLib\Common\Enums\TipoOperacao::Entrada)
            ->addItem($this->item(cfop: '5102'))
            ->build();
    }

    public function testTotaisFormulaV2ComStEIpi(): void
    {
        $tributos = new NfeTaxResultado(
            icms: new IcmsResultado(
                origem: 0, cst: '10', modBc: '3', baseCalculo: '100.00', aliquota: '18.0000', valor: '18.00',
                st: new \FiscalLib\Tax\Resultados\IcmsStResultado(modBcSt: '4', baseCalculoSt: '130.00', aliquotaSt: '18.0000', valorSt: '23.40'),
            ),
            ipi: new ImpostoTrioResultado(cst: '50', baseCalculo: '100.00', aliquota: '10.0000', valor: '10.00'),
        );

        $doc = NfeBuilder::nfe()
            ->serie(1)
            ->naturezaOperacao('Venda')
            ->addItem($this->item(tributos: $tributos))
            ->frete(20)
            ->descontoTotal(0) // presente → ativa fórmula v2
            ->build();

        // 100 − 0 + 20 + ST 23.40 + IPI 10.00 = 153.40 (fórmula exata do validador da API)
        self::assertSame('153.40', $doc->totais->valorNota);
    }

    public function testNfcaSemDestinatarioAcimaDeDezMilFalha(): void
    {
        $this->expectException(ValidationException::class);
        \FiscalLib\Nfce\NfceBuilder::nfce()
            ->serie(1)
            ->naturezaOperacao('Venda balcão')
            ->addItem($this->item(quantidade: 1, unitario: 10500))
            ->pagamento(FormaPagamento::Dinheiro, 10500)
            ->build();
    }

    public function testNfceSemPagamentoFalha(): void
    {
        $this->expectException(ValidationException::class);
        \FiscalLib\Nfce\NfceBuilder::nfce()
            ->serie(1)
            ->naturezaOperacao('Venda balcão')
            ->addItem($this->item())
            ->build();
    }

    public function testNfceComIpiFalha(): void
    {
        $tributos = new NfeTaxResultado(
            icms: $this->icms00()->icms,
            ipi: new ImpostoTrioResultado(cst: '50', baseCalculo: '100.00', aliquota: '10.0000', valor: '10.00'),
        );

        $this->expectException(ValidationException::class);
        \FiscalLib\Nfce\NfceBuilder::nfce()
            ->serie(1)
            ->naturezaOperacao('Venda balcão')
            ->addItem($this->item(tributos: $tributos))
            ->pagamento(FormaPagamento::Dinheiro, 100)
            ->build();
    }

    public function testNfcePagamentoMenorQueNotaFalha(): void
    {
        $this->expectException(ValidationException::class);
        \FiscalLib\Nfce\NfceBuilder::nfce()
            ->serie(1)
            ->naturezaOperacao('Venda balcão')
            ->addItem($this->item())
            ->pagamento(FormaPagamento::Dinheiro, 50)
            ->build();
    }

    public function testNfceConsumidorFinalObrigatorio(): void
    {
        $this->expectException(ValidationException::class);
        \FiscalLib\Nfce\NfceBuilder::nfce()
            ->consumidorFinal(IndicadorConsumidorFinal::Nao);
    }
}
