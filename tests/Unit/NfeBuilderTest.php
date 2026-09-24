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
            icms: new IcmsResultado(origem: 0, cst: '00', modBc: '3', baseCalculo: '100.00', aliquota: '18.0000', valor: '18.00'),
            ibsCbs: new \FiscalLib\Tax\Resultados\IbsCbsResultado(
                cstIbsCbs: '000', cClassTrib: '000001',
                aliquotaCbs: '0.9000', valorCbs: '0.90',
                aliquotaIbsEstadual: '0.1000', valorIbsEstadual: '0.10',
            ),
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
            ibsCbs: new \FiscalLib\Tax\Resultados\IbsCbsResultado(
                cstIbsCbs: '000', cClassTrib: '000001',
                aliquotaCbs: '0.9000', valorCbs: '0.90',
                aliquotaIbsEstadual: '0.1000', valorIbsEstadual: '0.10',
            ),
        );

        $doc = NfeBuilder::nfe()
            ->serie(1)
            ->naturezaOperacao('Venda')
            ->destinatario(new Destinatario(Cnpj::criar('11444777000161'), 'Cliente Teste Ltda'))
            ->addItem($this->item(tributos: $tributos))
            ->frete(20)
            ->descontoTotal(0) // presente → ativa fórmula v2
            ->build();

        // 100 − 0 + 20 + ST 23.40 + IPI 10.00 = 153.40 (fórmula exata do validador da API).
        // IBS/CBS não entram no total (conferência à parte no contrato).
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

    // ------------------------------------------------- regras R0xx (auditoria 0.2.1)

    private function documentoBase(): NfeBuilder
    {
        return NfeBuilder::nfe()
            ->serie(1)
            ->naturezaOperacao('Venda')
            ->destinatario(new Destinatario(Cnpj::criar('11444777000161'), 'Cliente Teste Ltda'));
    }

    public function testNfeSemDestinatarioFalha(): void
    {
        try {
            NfeBuilder::nfe()
                ->serie(1)
                ->naturezaOperacao('Venda')
                ->addItem($this->item())
                ->build();
            self::fail('NF-e 55 sem destinatário deveria falhar.');
        } catch (ValidationException $e) {
            self::assertArrayHasKey('destinatario', $e->erros());
        }
    }

    public function testDescricaoAcimaDe120CaracteresFalha(): void
    {
        $item = new ItemFiscal('SKU1', str_repeat('x', 121), '1.0000', '100.00', '100.00');

        try {
            $this->documentoBase()->addItem($item)->build();
            self::fail('xProd acima de 120 caracteres deveria falhar (R015).');
        } catch (ValidationException $e) {
            self::assertArrayHasKey('itens[0].descricao', $e->erros());
        }
    }

    public function testNcmComFormatoInvalidoFalha(): void
    {
        $item = new ItemFiscal('SKU1', 'Produto', '1.0000', '100.00', '100.00', ncm: '1234');

        try {
            $this->documentoBase()->addItem($item)->build();
            self::fail('NCM sem 8 dígitos deveria falhar.');
        } catch (ValidationException $e) {
            self::assertArrayHasKey('itens[0].ncm', $e->erros());
        }
    }

    public function testQuantidadeZeroFalha(): void
    {
        $item = new ItemFiscal('SKU1', 'Produto', '0.0000', '100.00', '0.00');

        try {
            $this->documentoBase()->addItem($item)->build();
            self::fail('Quantidade zero deveria falhar.');
        } catch (ValidationException $e) {
            self::assertArrayHasKey('itens[0].quantidade', $e->erros());
        }
    }

    public function testNfceSerieReservadaContingenciaFalha(): void
    {
        $this->expectException(ValidationException::class);
        \FiscalLib\Nfce\NfceBuilder::nfce()
            ->serie(900)
            ->naturezaOperacao('Venda balcão')
            ->addItem($this->item())
            ->pagamento(FormaPagamento::Dinheiro, 100)
            ->build();
    }

    public function testNfeSerieZeroEValida(): void
    {
        $doc = $this->documentoBase()->serie(0)->addItem($this->item())->build();

        self::assertSame(0, $doc->serie);
    }

    // ------------------------------------------------- cronograma IBS/CBS (Fase 5)

    private function icmsComIbsCbs(): NfeTaxResultado
    {
        return $this->icms00();
    }

    public function testItemRegimeNormalSemIbsCbsFalhaNoCronograma(): void
    {
        // Hoje (>= 03/08/2026) item com CST exige grupo IBS/CBS.
        $semIbsCbs = new NfeTaxResultado(
            icms: new IcmsResultado(origem: 0, cst: '00', modBc: '3', baseCalculo: '100.00', aliquota: '18.0000', valor: '18.00'),
        );

        try {
            $this->documentoBase()->addItem($this->item(tributos: $semIbsCbs))->build();
            self::fail('Item regime normal sem IBS/CBS após 03/08/2026 deveria falhar.');
        } catch (ValidationException $e) {
            self::assertArrayHasKey('itens[0].impostosV2.ibsCbs', $e->erros());
            self::assertStringContainsString('2026-08-03', $e->erros()['itens[0].impostosV2.ibsCbs'][0]);
        }
    }

    public function testItemRegimeNormalComIbsCbsPassa(): void
    {
        $doc = $this->documentoBase()
            ->addItem($this->item(tributos: $this->icmsComIbsCbs()))
            ->build();

        self::assertSame('100.00', $doc->totais->valorProdutos);
    }

    public function testItemSemIcmsNaoExigeIbsCbs(): void
    {
        // Sem ICMS não há como identificar o regime — cronograma não se aplica.
        $doc = $this->documentoBase()->addItem($this->item())->build();

        self::assertCount(1, $doc->itens);
    }

    public function testItemSimplesNacionalSemIbsCbsPassaAte2027(): void
    {
        $csosn101 = new NfeTaxResultado(
            icms: new IcmsResultado(
                origem: 0,
                csosn: '101',
                percentualCreditoSimples: '2.5000',
                valorCreditoSimples: '25.00',
            ),
        );

        $doc = $this->documentoBase()->addItem($this->item(tributos: $csosn101))->build();

        self::assertCount(1, $doc->itens);
    }

    public function testItemSimplesNacionalSemIbsCbsFalhaApos2027(): void
    {
        $csosn101 = new NfeTaxResultado(
            icms: new IcmsResultado(
                origem: 0,
                csosn: '101',
                percentualCreditoSimples: '2.5000',
                valorCreditoSimples: '25.00',
            ),
        );

        $builder = new class extends NfeBuilder {
            protected function hoje(): string
            {
                return '2027-01-05';
            }
        };

        try {
            $builder->serie(1)
                ->naturezaOperacao('Venda')
                ->destinatario(new Destinatario(Cnpj::criar('11444777000161'), 'Cliente Teste Ltda'))
                ->addItem($this->item(tributos: $csosn101))
                ->build();
            self::fail('Item Simples Nacional sem IBS/CBS após 04/01/2027 deveria falhar.');
        } catch (ValidationException $e) {
            self::assertStringContainsString('2027-01-04', $e->erros()['itens[0].impostosV2.ibsCbs'][0]);
        }
    }
}
