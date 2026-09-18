<?php

declare(strict_types=1);

namespace FiscalLib\Tests\Unit;

use FiscalLib\Common\Enums\OrigemMercadoria;
use FiscalLib\Common\Enums\UF;
use FiscalLib\Exceptions\ValidationException;
use FiscalLib\Tax\Tabelas\ResolvedorAliquotas;
use PHPUnit\Framework\TestCase;

/**
 * Tabelas embutidas do ResolvedorAliquotas — validadas contra
 * tabelas-aliquotas-fiscallib.md (§1 interestadual, §2 interna, §3 DIFAL,
 * §4 FCP, §11 IBS/CBS). Mudança de tabela = atualiza doc + teste juntos.
 */
final class ResolvedorAliquotasTest extends TestCase
{
    private ResolvedorAliquotas $resolver;

    protected function setUp(): void
    {
        $this->resolver = new ResolvedorAliquotas();
    }

    // -------------------------------------------------------- interestadual

    public function testSulSudesteParaDemaisRegioesSetePorCento(): void
    {
        self::assertSame(7, $this->resolver->aliquotaInterestadual(UF::SP, UF::BA, OrigemMercadoria::Nacional));
        self::assertSame(7, $this->resolver->aliquotaInterestadual(UF::SC, UF::AM, OrigemMercadoria::Nacional));
        self::assertSame(7, $this->resolver->aliquotaInterestadual(UF::PR, UF::PI, OrigemMercadoria::Nacional));
    }

    public function testSulSudesteParaSulSudesteDozePorCento(): void
    {
        self::assertSame(12, $this->resolver->aliquotaInterestadual(UF::SP, UF::MG, OrigemMercadoria::Nacional));
        self::assertSame(12, $this->resolver->aliquotaInterestadual(UF::PR, UF::RJ, OrigemMercadoria::Nacional));
        self::assertSame(12, $this->resolver->aliquotaInterestadual(UF::RS, UF::SC, OrigemMercadoria::Nacional));
    }

    public function testDemaisRegioesParaQualquerDestinoDozePorCento(): void
    {
        self::assertSame(12, $this->resolver->aliquotaInterestadual(UF::BA, UF::SP, OrigemMercadoria::Nacional));
        self::assertSame(12, $this->resolver->aliquotaInterestadual(UF::MA, UF::AC, OrigemMercadoria::Nacional));
        self::assertSame(12, $this->resolver->aliquotaInterestadual(UF::GO, UF::SC, OrigemMercadoria::Nacional));
    }

    public function testImportadasEConteudoImportacaoQuatroPorCentoQualquerPar(): void
    {
        $origensQuatroPorCento = [
            OrigemMercadoria::EstrangeiraImportacaoDireta,
            OrigemMercadoria::EstrangeiraAdquiridaInterno,
            OrigemMercadoria::NacionalConteudoImportacao40,
            OrigemMercadoria::EstrangeiraImportacaoDiretaSemSimilar,
            OrigemMercadoria::EstrangeiraInternoSemSimilar,
            OrigemMercadoria::NacionalConteudoImportacaoSuperior70,
        ];

        foreach ($origensQuatroPorCento as $origem) {
            self::assertSame(4, $this->resolver->aliquotaInterestadual(UF::SP, UF::BA, $origem), "orig {$origem->value} SP→BA");
            self::assertSame(4, $this->resolver->aliquotaInterestadual(UF::BA, UF::SP, $origem), "orig {$origem->value} BA→SP");
        }
    }

    public function testOrigensNacionaisNaoRecebemQuatroPorCento(): void
    {
        self::assertSame(7, $this->resolver->aliquotaInterestadual(UF::SP, UF::BA, OrigemMercadoria::Nacional));
        self::assertSame(7, $this->resolver->aliquotaInterestadual(UF::SP, UF::BA, OrigemMercadoria::NacionalProcessoProdutivoBasico));
        self::assertSame(7, $this->resolver->aliquotaInterestadual(UF::SP, UF::BA, OrigemMercadoria::NacionalConteudoImportacaoInferior40));
    }

    public function testMesmaUfFalha(): void
    {
        $this->expectException(ValidationException::class);
        $this->resolver->aliquotaInterestadual(UF::SP, UF::SP, OrigemMercadoria::Nacional);
    }

    // ---------------------------------------------------------------- DIFAL

    /**
     * Componentes do DIFAL contra a tabela §3 do doc: interna + FCP do
     * destino reproduzem as colunas (AL 21,50 = 20,50 + 1; RJ 22 = 20 + 2;
     * SE 20 = 19 + 1) e o diferencial (interna − interestadual + fcp).
     *
     * @return iterable<string, array{UF, UF, int, string, ?string, string}>
     */
    public static function difalReferencia(): iterable
    {
        // origem, destino, interestadual, interna, fcp, difal total (doc §3)
        yield 'PR→BA' => [UF::PR, UF::BA, 7, '20.50', null, '13.50'];
        yield 'SP→RJ' => [UF::SP, UF::RJ, 12, '20.00', '2.00', '10.00'];
        yield 'SP→AL' => [UF::SP, UF::AL, 7, '20.50', '1.00', '14.50'];
        yield 'SP→SE' => [UF::SP, UF::SE, 7, '19.00', '1.00', '13.00'];
        yield 'MG→SP' => [UF::MG, UF::SP, 12, '18.00', '2.00', '8.00'];
        yield 'BA→RS' => [UF::BA, UF::RS, 12, '17.00', '0.50', '5.50'];
    }

    /** @dataProvider difalReferencia */
    public function testParametrosDifalContraTabelaDeReferencia(
        UF $origem,
        UF $destino,
        int $interestadual,
        string $interna,
        ?string $fcp,
        string $difalTotal,
    ): void {
        $p = $this->resolver->parametrosDifal($origem, $destino, OrigemMercadoria::Nacional);

        self::assertSame($interestadual, $p->aliquotaInterestadual);
        self::assertSame($interna, $p->aliquotaInternaUfDestino);
        self::assertSame($fcp, $p->aliquotaFcpUfDestino);

        $diferencial = bcsub($interna, (string) $interestadual, 2);
        self::assertSame($difalTotal, bcadd($diferencial, $fcp ?? '0.00', 2), "DIFAL {$origem->value}→{$destino->value} deve casar com o doc");
    }

    public function testParametrosDifalMesmaUfFalha(): void
    {
        $this->expectException(ValidationException::class);
        $this->resolver->parametrosDifal(UF::MG, UF::MG, OrigemMercadoria::Nacional);
    }

    // -------------------------------------------------------- interna e FCP

    public function testInternaGeralCobreAsVinteESeteUfs(): void
    {
        self::assertCount(27, UF::cases());
        foreach (UF::cases() as $uf) {
            self::assertNotEmpty($this->resolver->aliquotaInternaGeral($uf), "UF {$uf->value} sem alíquota interna");
        }
    }

    public function testInternaGeralSpotChecks(): void
    {
        self::assertSame('18.00', $this->resolver->aliquotaInternaGeral(UF::SP));
        self::assertSame('17.00', $this->resolver->aliquotaInternaGeral(UF::SC));
        self::assertSame('22.50', $this->resolver->aliquotaInternaGeral(UF::PI));
        self::assertSame('23.00', $this->resolver->aliquotaInternaGeral(UF::MA));
        self::assertSame('19.50', $this->resolver->aliquotaInternaGeral(UF::PR));
        self::assertSame('20.00', $this->resolver->aliquotaInternaGeral(UF::RJ));
    }

    public function testFcpPorUf(): void
    {
        self::assertSame('2.00', $this->resolver->aliquotaFcp(UF::RJ));
        self::assertSame('0.50', $this->resolver->aliquotaFcp(UF::RS));
        self::assertSame('1.00', $this->resolver->aliquotaFcp(UF::SE));
        self::assertNull($this->resolver->aliquotaFcp(UF::ES));
        self::assertNull($this->resolver->aliquotaFcp(UF::BA));
    }

    // ------------------------------------------------------------- overrides

    public function testOverrideVenceTabela(): void
    {
        $comOverride = $this->resolver->comAliquotaInterna(UF::SP, '12.50');

        self::assertSame('12.50', $comOverride->aliquotaInternaGeral(UF::SP));
        self::assertSame('18.00', $this->resolver->aliquotaInternaGeral(UF::SP), 'instância original imutável');
        self::assertSame('17.00', $comOverride->aliquotaInternaGeral(UF::SC), 'override só afeta a UF alvo');
    }

    public function testOverrideFcpDesativa(): void
    {
        $semFcp = $this->resolver->comFcp(UF::RJ, null);
        $comFcpMaior = $this->resolver->comFcp(UF::ES, '1.50');

        self::assertNull($semFcp->aliquotaFcp(UF::RJ));
        self::assertSame('2.00', $this->resolver->aliquotaFcp(UF::RJ), 'instância original imutável');
        self::assertSame('1.50', $comFcpMaior->aliquotaFcp(UF::ES));
        self::assertNull($comFcpMaior->aliquotaFcp(UF::BA), 'override não vaza para outras UFs');
    }

    public function testDifalComOverrideDaInterna(): void
    {
        $resolver = $this->resolver->comAliquotaInterna(UF::BA, '27.00');

        $p = $resolver->parametrosDifal(UF::PR, UF::BA, OrigemMercadoria::Nacional);

        self::assertSame(7, $p->aliquotaInterestadual);
        self::assertSame('27.00', $p->aliquotaInternaUfDestino);
        self::assertNull($p->aliquotaFcpUfDestino);
    }

    // -------------------------------------------------------------- IBS/CBS

    public function testIbsCbsFaseTeste2026(): void
    {
        $aliquotas = $this->resolver->aliquotasIbsCbs(2026);

        self::assertSame('0.90', $aliquotas->aliquotaCbs);
        self::assertSame('0.05', $aliquotas->aliquotaIbsEstadual);
        self::assertSame('0.05', $aliquotas->aliquotaIbsMunicipal);
    }

    public function testAnoSemLeiDefinidaFalha(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('2027');
        $this->resolver->aliquotasIbsCbs(2027);
    }
}
