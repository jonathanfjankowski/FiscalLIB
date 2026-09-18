<?php

declare(strict_types=1);

namespace FiscalLib\Tests\Unit;

use FiscalLib\Common\Enums\CstIcms;
use FiscalLib\Common\Enums\CstIpi;
use FiscalLib\Common\Enums\CstPisCofins;
use FiscalLib\Common\Enums\Csosn;
use FiscalLib\Common\Enums\ModoDeterminacaoBc;
use FiscalLib\Common\Enums\OrigemMercadoria;
use FiscalLib\Exceptions\MissingFieldException;
use FiscalLib\Exceptions\ValidationException;
use FiscalLib\Tax\Contextos\IbsCbsEntrada;
use FiscalLib\Tax\Contextos\IsEntrada;
use FiscalLib\Tax\Contextos\NfeTaxContext;
use FiscalLib\Tax\Contextos\NfseTaxContext;
use FiscalLib\Tax\TaxEngine;
use PHPUnit\Framework\TestCase;

/**
 * Matriz do motor tributário — os valores devem passar na aritmética do
 * ValidadorImpostosV2 da FiscalAPI (base × alíquota / 100 = valor, tol. 0,01).
 */
final class TaxEngineTest extends TestCase
{
    private TaxEngine $engine;

    protected function setUp(): void
    {
        $this->engine = new TaxEngine();
    }

    private function contexto(string|int|float $valorBruto = 1000, string|int|float $desconto = 0): NfeTaxContext
    {
        return NfeTaxContext::make()->valores(1, $valorBruto, $desconto);
    }

    public function testCst00TributadaIntegralComFcp(): void
    {
        $r = $this->engine->calcularNfe(
            $this->contexto()->icms(OrigemMercadoria::Nacional, CstIcms::TributadaIntegralmente, aliquota: 18, fcp: 2)
        );

        self::assertSame('1000.00', $r->icms->baseCalculo);
        self::assertSame('18.0000', $r->icms->aliquota);
        self::assertSame('180.00', $r->icms->valor);
        self::assertSame('2.0000', $r->icms->fcpPercentual);
        self::assertSame('20.00', $r->icms->valorFcp);
        self::assertSame(0, $r->icms->origem);
    }

    public function testCst10ComStMva(): void
    {
        $r = $this->engine->calcularNfe(
            $this->contexto()->icms(OrigemMercadoria::Nacional, CstIcms::TributadaComCobrancaIcmsPorSt, aliquota: 18)
                ->st(ModoDeterminacaoBc::PrecoTabeladoMaximo, mva: 30, aliquotaSt: 18)
        );

        // BC_ST = 1000 × 1,30 = 1300; vICMSST = 1300 × 18% = 234 (fórmula direta da API)
        self::assertSame('180.00', $r->icms->valor);
        self::assertSame('1300.00', $r->icms->st->baseCalculoSt);
        self::assertSame('234.00', $r->icms->st->valorSt);
        self::assertSame('4', $r->icms->st->modBcSt);
    }

    public function testCst20ComReducaoBc(): void
    {
        $r = $this->engine->calcularNfe(
            $this->contexto()->icms(OrigemMercadoria::Nacional, CstIcms::ComReducaoDeBaseDeCalculo, aliquota: 18, reducaoBc: 10)
        );

        self::assertSame('900.00', $r->icms->baseCalculo);
        self::assertSame('162.00', $r->icms->valor);
        self::assertSame('10.0000', $r->icms->percentualReducaoBc);
    }

    public function testCst41Isenta(): void
    {
        $r = $this->engine->calcularNfe($this->contexto()->icms(OrigemMercadoria::Nacional, CstIcms::NaoTributada));

        self::assertNull($r->icms->valor);
        self::assertNull($r->icms->baseCalculo);
    }

    public function testCst51DiferimentoOmiteValor(): void
    {
        $r = $this->engine->calcularNfe(
            $this->contexto()->icms(OrigemMercadoria::Nacional, CstIcms::Diferimento, aliquota: 12)->diferimento(50)
        );

        // valorIcmsOperacao = 120; diferido = 60; `valor` OMITIDO
        // (o validador da API exige valor = base×alíq, que só vale sem diferimento)
        self::assertSame('120.00', $r->icms->valorIcmsOperacao);
        self::assertSame('50.0000', $r->icms->percentualDiferimento);
        self::assertSame('60.00', $r->icms->valorIcmsDiferido);
        self::assertNull($r->icms->valor);
    }

    public function testCst60StRetida(): void
    {
        $r = $this->engine->calcularNfe(
            $this->contexto()->icms(OrigemMercadoria::Nacional, CstIcms::IcmsCobradoAnteriormentePorSt)->stRetida(1000, 18)
        );

        self::assertSame('1000.00', $r->icms->st->baseCalculoStRetido);
        self::assertSame('180.00', $r->icms->st->valorStRetido);
        self::assertNull($r->icms->valor);
    }

    public function testCst70ReducaoMaisSt(): void
    {
        $r = $this->engine->calcularNfe(
            $this->contexto()->icms(OrigemMercadoria::Nacional, CstIcms::ComReducaoDeBaseECobrancaPorSt, aliquota: 18, reducaoBc: 10)
                ->st(ModoDeterminacaoBc::PrecoTabeladoMaximo, mva: 30, aliquotaSt: 18)
        );

        // Própria: base reduzida 900 → 162. ST: semente = base reduzida 900 × 1,3 = 1170 → 210,60
        self::assertSame('900.00', $r->icms->baseCalculo);
        self::assertSame('162.00', $r->icms->valor);
        self::assertSame('1170.00', $r->icms->st->baseCalculoSt);
        self::assertSame('210.60', $r->icms->st->valorSt);
    }

    public function testCst00ComReducaoFalha(): void
    {
        $this->expectException(\FiscalLib\Exceptions\TaxInconsistencyException::class);
        $this->engine->calcularNfe(
            $this->contexto()->icms(OrigemMercadoria::Nacional, CstIcms::TributadaIntegralmente, aliquota: 18, reducaoBc: 10)
        );
    }

    public function testCsosn102SimplesSemValores(): void
    {
        $r = $this->engine->calcularNfe($this->contexto()->icms(OrigemMercadoria::Nacional, Csosn::TributadaSemPermissaoDeCredito));

        self::assertSame('102', $r->icms->csosn);
        self::assertNull($r->icms->valor);
    }

    public function testCsosn101ComCredito(): void
    {
        $r = $this->engine->calcularNfe(
            $this->contexto()->icms(OrigemMercadoria::Nacional, Csosn::TributadaComPermissaoDeCredito)->creditoSimples(2.5)
        );

        self::assertSame('25.00', $r->icms->valorCreditoSimples);
    }

    public function testCsosn201ComSt(): void
    {
        $r = $this->engine->calcularNfe(
            $this->contexto()->icms(OrigemMercadoria::Nacional, Csosn::TributadaComPermissaoDeCreditoECobrancaPorSt)->creditoSimples(2.5)
                ->st(ModoDeterminacaoBc::PrecoTabeladoMaximo, mva: 30, aliquotaSt: 18)
        );

        self::assertSame('25.00', $r->icms->valorCreditoSimples);
        self::assertSame('234.00', $r->icms->st->valorSt);
    }

    public function testDifalPartilha100Destino(): void
    {
        // Cenário coerente: PR→BA, interestadual 7% (ICMS próprio remete 7% à
        // UF de origem), interna do destino 18%, FCP destino 2%.
        $r = $this->engine->calcularNfe(
            $this->contexto()->icms(OrigemMercadoria::Nacional, CstIcms::TributadaIntegralmente, aliquota: 7)
                ->difalInterestadual(7, aliquotaInternaUfDestino: 18, fcpUfDestino: 2)
        );

        // Convênio 190/2017 + MOC (rejeições 815/816): 100% do diferencial para
        // o destino — vICMSUFDest = 1000 × (18% − 7%) = 110; origem = 0.
        self::assertSame(7, $r->icms->difal->aliquotaInterestadual);
        self::assertSame('1000.00', $r->icms->difal->baseDestino);
        self::assertSame('18.0000', $r->icms->difal->aliquotaDestino);
        self::assertSame('70.00', $r->icms->valor);
        self::assertSame('110.00', $r->icms->difal->valorIcmsDestino);
        self::assertSame('0.00', $r->icms->difal->valorIcmsOrigem);
        self::assertSame('20.00', $r->icms->difal->valorFcpDestino);
    }

    public function testDifalAliquotaInvalidaFalha(): void
    {
        $this->expectException(ValidationException::class);
        $this->engine->calcularNfe(
            $this->contexto()->icms(OrigemMercadoria::Nacional, CstIcms::TributadaIntegralmente, aliquota: 12)->difalInterestadual(9, 18)
        );
    }

    public function testIpiETributosFederais(): void
    {
        $r = $this->engine->calcularNfe(
            $this->contexto()->icms(OrigemMercadoria::Nacional, CstIcms::TributadaIntegralmente, aliquota: 18)
                ->ipi(CstIpi::SaidaTributada, aliquota: 10)
                ->pis(CstPisCofins::OperacaoTributavelCumulativo, '1.65')
                ->cofins(CstPisCofins::OperacaoTributavelCumulativo, '7.60')
        );

        self::assertSame('100.00', $r->ipi->valor);
        self::assertSame('999', $r->ipi->cEnq);
        self::assertSame('16.50', $r->pis->valor);
        self::assertSame('76.00', $r->cofins->valor);
    }

    public function testPisCofinsIsentos(): void
    {
        $r = $this->engine->calcularNfe(
            $this->contexto()->icms(OrigemMercadoria::Nacional, CstIcms::TributadaIntegralmente, aliquota: 18)
                ->pis(CstPisCofins::OperacaoIsenta)
                ->cofins(CstPisCofins::OperacaoIsenta)
        );

        self::assertNull($r->pis->valor);
        self::assertNull($r->cofins->valor);
    }

    public function testPisCofins99ComAliquotaOpcional(): void
    {
        $r = $this->engine->calcularNfe(
            $this->contexto()->icms(OrigemMercadoria::Nacional, CstIcms::TributadaIntegralmente, aliquota: 18)
                ->pis(CstPisCofins::OutrasOperacoes, '2.00')
        );

        self::assertSame('20.00', $r->pis->valor);
    }

    public function testIbsCbsPorFora(): void
    {
        $r = $this->engine->calcularNfe(
            $this->contexto()->icms(OrigemMercadoria::Nacional, CstIcms::TributadaIntegralmente, aliquota: 18)
                ->ibsCbs(IbsCbsEntrada::criar('000', '000001', aliquotaIbsEstadual: 0.9, aliquotaCbs: 0.1))
        );

        self::assertSame('9.00', $r->ibsCbs->valorIbsEstadual);
        self::assertSame('1.00', $r->ibsCbs->valorCbs);
        self::assertSame('9.00', $r->ibsCbs->totalIbs());
        self::assertSame('0.00', $r->totalIs());
    }

    public function testIsPorQuantidade(): void
    {
        $ctx = $this->contexto()->icms(OrigemMercadoria::Nacional, CstIcms::TributadaIntegralmente, aliquota: 18)
            ->is(IsEntrada::porQuantidade('01', '000001', 1.0, baseCalculo: 500, unidadeTributavel: 'L', quantidadeTributavel: 500));

        $r = $this->engine->calcularNfe($ctx);

        self::assertSame('500.00', $r->is->baseCalculo);
        self::assertSame('5.00', $r->is->valor);
        self::assertSame('L', $r->is->unidadeTributavel);
    }

    public function testCst00SemAliquotaFalha(): void
    {
        $this->expectException(MissingFieldException::class);
        $this->engine->calcularNfe($this->contexto()->icms(OrigemMercadoria::Nacional, CstIcms::TributadaIntegralmente));
    }

    public function testArredondamentoBancarioNoCalculo(): void
    {
        // 10.00 × 0.05% = 0.005 → bancário = 0.00
        $r = $this->engine->calcularNfe(
            NfeTaxContext::make()->valores(1, 10)->icms(OrigemMercadoria::Nacional, CstIcms::TributadaIntegralmente, aliquota: 0.05)
        );

        self::assertSame('0.00', $r->icms->valor);
    }

    public function testNfseIssRetidoEFederais(): void
    {
        $r = $this->engine->calcularNfse(
            NfseTaxContext::make()
                ->servico(5000, 100)
                ->iss(3.0, tributacao: 1, retencao: 2)
                ->pisCofins('01', 0.65, 3.0)
                ->retencoes(tipoRetencaoPisCofins: 2, cpp: 0, irrf: 73.5, csll: 0)
        );

        // Base 4900: ISS 147.00 | PIS 31.85 | COFINS 147.00 (DEVIDOS, não retidos)
        self::assertSame('147.00', $r->valorIssqn);
        self::assertSame('31.85', $r->valorPis);
        self::assertSame('147.00', $r->valorCofins);
        self::assertSame('4900.00', $r->baseCalculoPisCofins);
        self::assertSame('73.50', $r->valorRetidoIrrf);
        self::assertSame(2, $r->retencaoIssqn);
    }

    public function testNfseSemIssParaImunidade(): void
    {
        $r = $this->engine->calcularNfse(
            NfseTaxContext::make()->servico(1000)->iss(5.0, tributacao: 2, retencao: 1)
        );

        self::assertNull($r->valorIssqn);
    }
}
