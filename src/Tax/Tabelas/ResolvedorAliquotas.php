<?php

declare(strict_types=1);

namespace FiscalLib\Tax\Tabelas;

use FiscalLib\Common\Enums\OrigemMercadoria;
use FiscalLib\Common\Enums\UF;
use FiscalLib\Common\Matematica;
use FiscalLib\Exceptions\ValidationException;

/**
 * Resolvedor de alíquotas — dados fiscais determinísticos embutidos na lib.
 *
 * Puro (sem I/O): o ERP consulta ANTES de montar o NfeTaxContext; o TaxEngine
 * continua exigindo as alíquotas explícitas (rastro claro no cálculo).
 * Alíquotas específicas por produto (medicamentos, cesta básica, bebidas...)
 * devem ser passadas por override — os valores embutidos são a REGRA GERAL
 * de cada estado, nunca a resposta para um produto diferenciado.
 *
 * Overrides são imutáveis: comAliquotaInterna()/comFcp() devolvem nova instância
 * e vencem a tabela embutida.
 *
 * Tabelas vigentes em set/2026 — ver tabelas-aliquotas-fiscallib.md (§1, §2 e §4).
 */
final class ResolvedorAliquotas
{
    /** Res. Senado 22/1989: sul/sudeste → demais regiões = 7%; demais pares = 12%. */
    private const SUL_SUDESTE = [UF::SP, UF::MG, UF::RJ, UF::PR, UF::RS, UF::SC];

    /** Res. Senado 13/2012: importadas e conteúdo de importação > 40% = 4%. */
    private const MERCADORIAS_QUATRO_PORCENTO = [
        OrigemMercadoria::EstrangeiraImportacaoDireta,
        OrigemMercadoria::EstrangeiraAdquiridaInterno,
        OrigemMercadoria::NacionalConteudoImportacao40,
        OrigemMercadoria::EstrangeiraImportacaoDiretaSemSimilar,
        OrigemMercadoria::EstrangeiraInternoSemSimilar,
        OrigemMercadoria::NacionalConteudoImportacaoSuperior70,
    ];

    /** UF => alíquota ICMS interna geral (regra geral do estado, SEM FCP/FECP). */
    private const INTERNAS = [
        'AC' => '19.00', 'AL' => '20.50', 'AM' => '20.00', 'AP' => '18.00',
        'BA' => '20.50', 'CE' => '20.00', 'DF' => '20.00', 'ES' => '17.00',
        'GO' => '19.00', 'MA' => '23.00', 'MG' => '18.00', 'MS' => '17.00',
        'MT' => '17.00', 'PA' => '19.00', 'PB' => '20.00', 'PE' => '20.50',
        'PI' => '22.50', 'PR' => '19.50', 'RJ' => '20.00', 'RN' => '20.00',
        'RO' => '19.50', 'RR' => '20.00', 'RS' => '17.00', 'SC' => '17.00',
        'SE' => '19.00', 'SP' => '18.00', 'TO' => '20.00',
    ];

    /** UF => FCP/FECP adicional (EC 132/2023 — teto 2%). Incide só em produtos sujeitos. */
    private const FCP = [
        'AL' => '1.00', 'CE' => '2.00', 'MG' => '2.00', 'MS' => '2.00',
        'MT' => '2.00', 'PA' => '2.00', 'PE' => '2.00', 'PI' => '2.00',
        'RJ' => '2.00', 'RN' => '2.00', 'RS' => '0.50', 'SE' => '1.00',
        'SP' => '2.00', 'TO' => '2.00',
    ];

    /** Ano => alíquotas IBS/CBS definidas em lei (LC 214/2025 art. 348 — fase-teste 2026, informativas). */
    private const IBS_CBS = [
        2026 => ['cbs' => '0.90', 'ibsUf' => '0.05', 'ibsMun' => '0.05'],
    ];

    /** @var array<string, string> */
    private array $internasOverride = [];

    /** @var array<string, string|null> */
    private array $fcpOverride = [];

    /**
     * Override do ERP para a alíquota interna da UF (produto com alíquota
     * diferenciada, benefício estadual etc.). Vence a tabela embutida.
     */
    public function comAliquotaInterna(UF $uf, string|int|float $aliquota): self
    {
        $copia = clone $this;
        $copia->internasOverride[$uf->value] = Matematica::escalar($aliquota, 2);

        return $copia;
    }

    /**
     * Override do ERP para o FCP/FECP da UF — null desativa (produto não sujeito).
     */
    public function comFcp(UF $uf, string|int|float|null $percentual): self
    {
        $copia = clone $this;
        $copia->fcpOverride[$uf->value] = $percentual === null ? null : Matematica::escalar($percentual, 2);

        return $copia;
    }

    /**
     * Alíquota interestadual (4/7/12) por par de UFs e origem da mercadoria.
     *
     * @throws ValidationException operação interna (UFs iguais)
     */
    public function aliquotaInterestadual(UF $origem, UF $destino, OrigemMercadoria $mercadoria): int
    {
        if ($origem === $destino) {
            throw new ValidationException('Alíquota interestadual exige UFs distintas — operação interna usa a alíquota do próprio estado.');
        }

        if (in_array($mercadoria, self::MERCADORIAS_QUATRO_PORCENTO, true)) {
            return 4;
        }

        if (in_array($origem, self::SUL_SUDESTE, true)) {
            return in_array($destino, self::SUL_SUDESTE, true) ? 12 : 7;
        }

        return 12;
    }

    /**
     * DIFAL completo do destino: pronto para NfeTaxContext::difalInterestadual().
     *
     * @throws ValidationException operação interna
     */
    public function parametrosDifal(UF $origem, UF $destino, OrigemMercadoria $mercadoria): ParametrosDifal
    {
        return new ParametrosDifal(
            aliquotaInterestadual: $this->aliquotaInterestadual($origem, $destino, $mercadoria),
            aliquotaInternaUfDestino: $this->aliquotaInternaGeral($destino),
            aliquotaFcpUfDestino: $this->aliquotaFcp($destino),
        );
    }

    /**
     * Alíquota ICMS interna GERAL da UF (sem FCP). Produtos com alíquota
     * diferenciada (bebidas 25–29%, medicamentos, cesta básica...) exigem
     * override do ERP — o default do estado não serve para eles.
     */
    public function aliquotaInternaGeral(UF $uf): string
    {
        return $this->internasOverride[$uf->value] ?? self::INTERNAS[$uf->value];
    }

    /** FCP/FECP adicional da UF (null = UF sem FCP ou desativado por override). */
    public function aliquotaFcp(UF $uf): ?string
    {
        if (array_key_exists($uf->value, $this->fcpOverride)) {
            return $this->fcpOverride[$uf->value];
        }

        return self::FCP[$uf->value] ?? null;
    }

    /**
     * Alíquotas IBS/CBS de referência por ano de competência.
     * A lib embute apenas a fase-teste de 2026 (informativa, LC 214/2025);
     * para outros anos forneça as alíquotas via ERP.
     *
     * @throws ValidationException ano sem alíquotas definidas em lei
     */
    public function aliquotasIbsCbs(int $ano): AliquotasIbsCbs
    {
        $tabela = self::IBS_CBS[$ano] ?? null;
        if ($tabela === null) {
            throw new ValidationException(
                "Sem alíquotas IBS/CBS embutidas para {$ano} — a lib embute apenas a fase-teste de 2026 "
                .'(LC 214/2025 art. 348). Forneça as alíquotas do exercício via ERP.'
            );
        }

        return new AliquotasIbsCbs(
            aliquotaCbs: $tabela['cbs'],
            aliquotaIbsEstadual: $tabela['ibsUf'],
            aliquotaIbsMunicipal: $tabela['ibsMun'],
        );
    }
}
