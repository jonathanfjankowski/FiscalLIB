<?php

declare(strict_types=1);

namespace FiscalLib\Tax;

use FiscalLib\Common\ArredondadorBancario;
use FiscalLib\Common\Enums\CstIcms;
use FiscalLib\Common\Enums\CstPisCofins;
use FiscalLib\Common\Enums\Csosn;
use FiscalLib\Common\Matematica;
use FiscalLib\Contracts\TaxEngineInterface;
use FiscalLib\Exceptions\MissingFieldException;
use FiscalLib\Exceptions\TaxCalculationException;
use FiscalLib\Exceptions\TaxInconsistencyException;
use FiscalLib\Exceptions\ValidationException;
use FiscalLib\Tax\Contextos\NfeTaxContext;
use FiscalLib\Tax\Contextos\NfseTaxContext;
use FiscalLib\Tax\Resultados\DifalResultado;
use FiscalLib\Tax\Resultados\IbsCbsResultado;
use FiscalLib\Tax\Resultados\IcmsResultado;
use FiscalLib\Tax\Resultados\IcmsStResultado;
use FiscalLib\Tax\Resultados\ImpostoTrioResultado;
use FiscalLib\Tax\Resultados\IsResultado;
use FiscalLib\Tax\Resultados\NfeTaxResultado;
use FiscalLib\Tax\Resultados\NfseTaxResultado;

/**
 * Implementação padrão do motor tributário.
 *
 * A aritmética espelha o ValidadorImpostosV2 da FiscalAPI (fonte da verdade):
 * todo valor = base × alíquota / 100, arredondamento bancário, tolerância 0,01.
 * CST/CSOSN são enums — combinação fora do contrato não compila; o que resta
 * aqui é a validação de REGRAS (00 com redução, 20 sem redução, cst+csosn, etc.).
 */
final class TaxEngine implements TaxEngineInterface
{
    public function calcularNfe(NfeTaxContext $ctx): NfeTaxResultado
    {
        $this->validarFaixasNfe($ctx);

        return new NfeTaxResultado(
            icms: $this->calcularIcms($ctx),
            ipi: $this->calcularIpi($ctx),
            pis: $this->calcularPisCofins($ctx->cstPis, $ctx->aliquotaPis, $ctx),
            cofins: $this->calcularPisCofins($ctx->cstCofins, $ctx->aliquotaCofins, $ctx),
            ibsCbs: $ctx->ibsCbs === null ? null : $this->calcularIbsCbs($ctx),
            is: $ctx->is === null ? null : $this->calcularIs($ctx),
        );
    }

    public function calcularNfse(NfseTaxContext $ctx): NfseTaxResultado
    {
        $this->validarFaixasNfse($ctx);

        $basePisCofins = $ctx->baseTributaria();

        $valorIssqn = null;
        if ($ctx->tributacaoIssqn === 1) {
            if ($ctx->aliquotaIssqn === null) {
                throw MissingFieldException::campo('aliquotaIssqn', 'NFS-e tributável (tributacaoIssqn = 1)');
            }
            $valorIssqn = Matematica::percentualDe($ctx->baseTributaria(), $ctx->aliquotaIssqn);
        }

        $valorPis = null;
        $valorCofins = null;
        if ($ctx->cstPisCofins !== null) {
            if ($ctx->aliquotaPis === null || $ctx->aliquotaCofins === null) {
                throw MissingFieldException::campo('aliquotaPis/aliquotaCofins', 'NFS-e com cstPisCofins informado exige as duas alíquotas.');
            }
            $valorPis = Matematica::percentualDe($basePisCofins, $ctx->aliquotaPis);
            $valorCofins = Matematica::percentualDe($basePisCofins, $ctx->aliquotaCofins);
        }

        return new NfseTaxResultado(
            valorServicos: $ctx->valorServicos,
            valorRecebido: $ctx->valorRecebido,
            descontoIncondicionado: $ctx->descontoIncondicionado,
            tributacaoIssqn: $ctx->tributacaoIssqn,
            retencaoIssqn: $ctx->retencaoIssqn,
            aliquotaIssqn: $ctx->aliquotaIssqn === null ? null : ArredondadorBancario::arredondar($ctx->aliquotaIssqn, 4),
            valorIssqn: $valorIssqn,
            cstPisCofins: $ctx->cstPisCofins,
            baseCalculoPisCofins: $ctx->cstPisCofins === null ? null : $basePisCofins,
            aliquotaPis: $this->pct($ctx->aliquotaPis),
            valorPis: $valorPis,
            aliquotaCofins: $this->pct($ctx->aliquotaCofins),
            valorCofins: $valorCofins,
            tipoRetencaoPisCofins: $ctx->tipoRetencaoPisCofins,
            valorRetidoCpp: $ctx->valorRetidoCpp,
            valorRetidoIrrf: $ctx->valorRetidoIrrf,
            valorRetidoCsll: $ctx->valorRetidoCsll,
            totalTributosFederal: $ctx->totalTributosFederal,
            totalTributosEstadual: $ctx->totalTributosEstadual,
            totalTributosMunicipal: $ctx->totalTributosMunicipal,
        );
    }

    // ------------------------------------------------------------------ faixas

    /**
     * Percentual/valor fora da faixa produz base ou imposto impossível
     * (negativo, maior que a base). Falha alta na entrada — nunca valor
     * negativo silencioso esperando rejeição da SEFAZ.
     */
    private function validarFaixasNfe(NfeTaxContext $ctx): void
    {
        $this->exigirNaoNegativo('quantidade', $ctx->quantidade);
        $this->exigirNaoNegativo('valorUnitario', $ctx->valorUnitario);
        $this->exigirNaoNegativo('valorBruto', $ctx->valorBruto);
        $this->exigirNaoNegativo('valorDesconto', $ctx->valorDesconto);
        if (Matematica::comparar($ctx->valorDesconto, $ctx->valorBruto, 2) > 0) {
            throw new TaxInconsistencyException(
                "valorDesconto ({$ctx->valorDesconto}) maior que valorBruto ({$ctx->valorBruto})."
            );
        }

        $this->exigirPercentual('aliquotaIcms', $ctx->aliquotaIcms);
        $this->exigirPercentual('percentualReducaoBc', $ctx->percentualReducaoBc);
        $this->exigirPercentual('aliquotaFcp', $ctx->aliquotaFcp);
        $this->exigirPercentual('percentualCreditoSimples', $ctx->percentualCreditoSimples);
        // MVA adiciona margem à base e pode exceder 100% na legislação do ST.
        $this->exigirNaoNegativo('percentualMva', $ctx->percentualMva);
        $this->exigirPercentual('percentualReducaoBcSt', $ctx->percentualReducaoBcSt);
        $this->exigirPercentual('aliquotaIcmsSt', $ctx->aliquotaIcmsSt);
        $this->exigirPercentual('aliquotaFcpSt', $ctx->aliquotaFcpSt);
        $this->exigirPercentual('percentualDiferimento', $ctx->percentualDiferimento);

        $this->exigirNaoNegativo('baseCalculoStRetida', $ctx->baseCalculoStRetida);
        $this->exigirPercentual('aliquotaStRetida', $ctx->aliquotaStRetida);
        $this->exigirNaoNegativo('valorStRetido', $ctx->valorStRetido);
        $this->exigirNaoNegativo('valorIcmsSubstituto', $ctx->valorIcmsSubstituto);
        $this->exigirPercentual('fcpPercentualStRetido', $ctx->fcpPercentualStRetido);
        $this->exigirNaoNegativo('valorFcpStRetido', $ctx->valorFcpStRetido);

        $this->exigirPercentual('aliquotaInternaUfDestino', $ctx->aliquotaInternaUfDestino);
        $this->exigirPercentual('aliquotaFcpUfDestino', $ctx->aliquotaFcpUfDestino);
        $this->exigirPercentual('aliquotaIpi', $ctx->aliquotaIpi);
        $this->exigirPercentual('aliquotaPis', $ctx->aliquotaPis);
        $this->exigirPercentual('aliquotaCofins', $ctx->aliquotaCofins);

        if ($ctx->ibsCbs !== null) {
            $this->exigirPercentual('aliquotaCbs', $ctx->ibsCbs->aliquotaCbs);
            $this->exigirPercentual('aliquotaIbsEstadual', $ctx->ibsCbs->aliquotaIbsEstadual);
            $this->exigirPercentual('aliquotaIbsMunicipal', $ctx->ibsCbs->aliquotaIbsMunicipal);
            $this->exigirPercentual('percentualReducaoCbs', $ctx->ibsCbs->percentualReducaoCbs);
            $this->exigirPercentual('percentualReducaoIbsEstadual', $ctx->ibsCbs->percentualReducaoIbsEstadual);
            $this->exigirPercentual('percentualReducaoIbsMunicipal', $ctx->ibsCbs->percentualReducaoIbsMunicipal);
        }
        if ($ctx->is !== null) {
            $this->exigirPercentual('aliquotaIs', $ctx->is->aliquota);
            $this->exigirNaoNegativo('baseCalculoOverride', $ctx->is->baseCalculoOverride);
        }
    }

    private function validarFaixasNfse(NfseTaxContext $ctx): void
    {
        $this->exigirNaoNegativo('valorServicos', $ctx->valorServicos);
        $this->exigirNaoNegativo('descontoIncondicionado', $ctx->descontoIncondicionado);
        if (Matematica::comparar($ctx->descontoIncondicionado, $ctx->valorServicos, 2) > 0) {
            throw new TaxInconsistencyException(
                "descontoIncondicionado ({$ctx->descontoIncondicionado}) maior que valorServicos ({$ctx->valorServicos})."
            );
        }
        $this->exigirNaoNegativo('valorRecebido', $ctx->valorRecebido);
        $this->exigirPercentual('aliquotaIssqn', $ctx->aliquotaIssqn);
        $this->exigirPercentual('aliquotaPis', $ctx->aliquotaPis);
        $this->exigirPercentual('aliquotaCofins', $ctx->aliquotaCofins);
        $this->exigirNaoNegativo('valorRetidoCpp', $ctx->valorRetidoCpp);
        $this->exigirNaoNegativo('valorRetidoIrrf', $ctx->valorRetidoIrrf);
        $this->exigirNaoNegativo('valorRetidoCsll', $ctx->valorRetidoCsll);
        $this->exigirNaoNegativo('totalTributosFederal', $ctx->totalTributosFederal);
        $this->exigirNaoNegativo('totalTributosEstadual', $ctx->totalTributosEstadual);
        $this->exigirNaoNegativo('totalTributosMunicipal', $ctx->totalTributosMunicipal);

        // Domínios — no NF-e os códigos são enums (não compilam fora do contrato);
        // aqui a entrada é primitiva, então a validação é explícita.
        if (! in_array($ctx->tributacaoIssqn, [1, 2, 3, 4], true)) {
            throw new TaxInconsistencyException("tributacaoIssqn fora do domínio 1–4: '{$ctx->tributacaoIssqn}'.");
        }
        if (! in_array($ctx->retencaoIssqn, [1, 2, 3], true)) {
            throw new TaxInconsistencyException("retencaoIssqn fora do domínio 1–3: '{$ctx->retencaoIssqn}'.");
        }
        if ($ctx->tipoRetencaoPisCofins !== null
            && ! in_array($ctx->tipoRetencaoPisCofins, [1, 2, 3], true)) {
            throw new TaxInconsistencyException("tipoRetencaoPisCofins fora do domínio 1–3: '{$ctx->tipoRetencaoPisCofins}'.");
        }
        if ($ctx->cstPisCofins !== null
            && ! in_array($ctx->cstPisCofins, array_map(static fn (CstPisCofins $c): string => $c->value, CstPisCofins::cases()), true)) {
            throw new TaxInconsistencyException(
                "cstPisCofins '{$ctx->cstPisCofins}' fora do contrato (tabela CST PIS/COFINS — ex.: 03 é monofásico por quantidade)."
            );
        }
    }

    private function exigirPercentual(string $campo, ?string $valor): void
    {
        if ($valor === null) {
            return;
        }
        if (Matematica::comparar($valor, '0', 4) < 0 || Matematica::comparar($valor, '100', 4) > 0) {
            throw new TaxInconsistencyException("{$campo} fora da faixa 0–100: '{$valor}'.");
        }
    }

    private function exigirNaoNegativo(string $campo, ?string $valor): void
    {
        if ($valor !== null && Matematica::comparar($valor, '0', 4) < 0) {
            throw new TaxInconsistencyException("{$campo} negativo: '{$valor}'.");
        }
    }

    // ------------------------------------------------------------------ ICMS

    private function calcularIcms(NfeTaxContext $ctx): ?IcmsResultado
    {
        if ($ctx->cst === null && $ctx->csosn === null) {
            return null;
        }
        if ($ctx->cst !== null && $ctx->csosn !== null) {
            throw TaxInconsistencyException::combinacaoNaoSuportada('informe cst OU csosn — nunca os dois.');
        }

        // DIFAL só existe em operação tributada interestadual p/ consumidor final;
        // pCredSN só existe no Simples Nacional (CSOSN 101/201/900). Informar em
        // outro contexto é dado que seria descartado em silêncio — falha alta.
        if ($ctx->difal && $this->difalNaoAdmitido($ctx)) {
            throw TaxInconsistencyException::combinacaoNaoSuportada(
                'DIFAL não se aplica a este CST/CSOSN (somente CST 00/10/20/51/70/90 e CSOSN 900).'
            );
        }
        if ($ctx->percentualCreditoSimples !== null && $this->creditoSimplesNaoAdmitido($ctx)) {
            throw TaxInconsistencyException::combinacaoNaoSuportada(
                'percentualCreditoSimples (pCredSN) só existe no Simples Nacional — CSOSN 101/201/900.'
            );
        }

        if ($ctx->cst !== null) {
            return $this->icmsPorCst($ctx);
        }

        return $this->icmsPorCsosn($ctx);
    }

    private function icmsPorCst(NfeTaxContext $ctx): IcmsResultado
    {
        $cst = $ctx->cst;
        \assert($cst !== null);

        $basePropria = $ctx->basePropria();

        switch ($cst) {
            case CstIcms::TributadaIntegralmente:
                if ($ctx->percentualReducaoBc !== null) {
                    throw TaxInconsistencyException::combinacaoNaoSuportada('CST 00 não admite redução de BC — use CST 20.');
                }

                return $this->icmsTributado($ctx, $basePropria, null);

            case CstIcms::TributadaComCobrancaIcmsPorSt:
                if ($ctx->percentualReducaoBc !== null) {
                    throw TaxInconsistencyException::combinacaoNaoSuportada('CST 10 não admite redução de BC — use CST 70.');
                }

                return $this->icmsTributado($ctx, $basePropria, $this->stPropria($ctx, $basePropria));

            case CstIcms::ComReducaoDeBaseDeCalculo:
                if ($ctx->percentualReducaoBc === null) {
                    throw MissingFieldException::campo('percentualReducaoBc', 'CST 20');
                }

                return $this->icmsTributado($ctx, $ctx->baseComReducao(), null);

            case CstIcms::Isenta:
            case CstIcms::NaoTributada:
                return new IcmsResultado(origem: $ctx->origem->value, cst: $cst->value);

            case CstIcms::Diferimento:
                if ($ctx->aliquotaIcms === null) {
                    throw MissingFieldException::campo('aliquotaIcms', 'CST 51 (diferimento)');
                }
                $base = $ctx->baseComReducao();
                $valorOperacao = Matematica::percentualDe($base, $ctx->aliquotaIcms);
                $percentualDif = $ctx->percentualDiferimento ?? '0';
                $valorDiferido = Matematica::percentualDe($valorOperacao, $percentualDif);

                // `valor` só pode ir quando não há diferimento — o validador da
                // API exige valor = base × alíquota, que só vale sem diferimento.
                // Percentuais têm 4 casas no contrato: comparar em 2 esconderia
                // diferimentos < 0,005.
                $semDiferimento = bccomp($percentualDif, '0', 4) === 0;
                $valor = $semDiferimento ? $valorOperacao : null;

                return new IcmsResultado(
                    origem: $ctx->origem->value,
                    cst: $cst->value,
                    modBc: $ctx->modBc->value,
                    baseCalculo: $base,
                    aliquota: ArredondadorBancario::arredondar($ctx->aliquotaIcms, 4),
                    valor: $valor,
                    percentualReducaoBc: $this->pct($ctx->percentualReducaoBc),
                    fcpPercentual: $this->pct($ctx->aliquotaFcp),
                    valorFcp: $ctx->aliquotaFcp === null ? null : Matematica::percentualDe($base, $ctx->aliquotaFcp),
                    valorIcmsOperacao: $valorOperacao,
                    percentualDiferimento: $this->pct($ctx->percentualDiferimento),
                    valorIcmsDiferido: $semDiferimento ? null : $valorDiferido,
                    difal: $this->difalSeAplicavel($ctx, $base),
                );

            case CstIcms::IcmsCobradoAnteriormentePorSt:
                return new IcmsResultado(
                    origem: $ctx->origem->value,
                    cst: $cst->value,
                    st: $this->stRetida($ctx),
                );

            case CstIcms::ComReducaoDeBaseECobrancaPorSt:
                if ($ctx->percentualReducaoBc === null) {
                    throw MissingFieldException::campo('percentualReducaoBc', 'CST 70');
                }
                $base = $ctx->baseComReducao();

                return $this->icmsTributado($ctx, $base, $this->stPropria($ctx, $base));

            case CstIcms::Outros:
                $base = $ctx->percentualReducaoBc !== null ? $ctx->baseComReducao() : $basePropria;
                $st = $ctx->modBcSt !== null ? $this->stPropria($ctx, $base)
                    : ($ctx->baseCalculoStRetida !== null ? $this->stRetida($ctx) : null);
                $trio = $ctx->aliquotaIcms !== null;

                return new IcmsResultado(
                    origem: $ctx->origem->value,
                    cst: $cst->value,
                    modBc: $trio ? $ctx->modBc->value : null,
                    baseCalculo: $trio ? $base : null,
                    aliquota: $trio ? ArredondadorBancario::arredondar((string) $ctx->aliquotaIcms, 4) : null,
                    valor: $trio ? Matematica::percentualDe($base, (string) $ctx->aliquotaIcms) : null,
                    percentualReducaoBc: $this->pct($ctx->percentualReducaoBc),
                    fcpPercentual: $this->pct($ctx->aliquotaFcp),
                    valorFcp: $ctx->aliquotaFcp === null || ! $trio ? null : Matematica::percentualDe($base, $ctx->aliquotaFcp),
                    percentualCreditoSimples: $this->pct($ctx->percentualCreditoSimples),
                    valorCreditoSimples: $ctx->percentualCreditoSimples === null ? null : Matematica::percentualDe($basePropria, $ctx->percentualCreditoSimples),
                    st: $st,
                    difal: $this->difalSeAplicavel($ctx, $base),
                );

            default:
                throw new TaxCalculationException("CST {$cst->value} não tratado."); // @codeCoverageIgnore
        }
    }

    private function icmsPorCsosn(NfeTaxContext $ctx): IcmsResultado
    {
        $csosn = $ctx->csosn;
        \assert($csosn !== null);

        $basePropria = $ctx->basePropria();
        $credito = $ctx->percentualCreditoSimples === null
            ? null
            : Matematica::percentualDe($basePropria, $ctx->percentualCreditoSimples);

        switch ($csosn) {
            case Csosn::TributadaComPermissaoDeCredito:
                if ($ctx->percentualCreditoSimples === null) {
                    throw MissingFieldException::campo('percentualCreditoSimples', 'CSOSN 101');
                }

                return new IcmsResultado(
                    origem: $ctx->origem->value,
                    csosn: $csosn->value,
                    percentualCreditoSimples: $this->pct($ctx->percentualCreditoSimples),
                    valorCreditoSimples: $credito,
                );

            case Csosn::TributadaComPermissaoDeCreditoECobrancaPorSt:
                return new IcmsResultado(
                    origem: $ctx->origem->value,
                    csosn: $csosn->value,
                    percentualCreditoSimples: $this->pct($ctx->percentualCreditoSimples),
                    valorCreditoSimples: $credito,
                    st: $this->stPropria($ctx, $basePropria),
                );

            case Csosn::TributadaSemPermissaoDeCreditoECobrancaPorSt:
            case Csosn::IsencaoFaixaReceitaECobrancaPorSt:
                return new IcmsResultado(origem: $ctx->origem->value, csosn: $csosn->value, st: $this->stPropria($ctx, $basePropria));

            case Csosn::Imune:
            case Csosn::SemIncidencia:
                return new IcmsResultado(origem: $ctx->origem->value, csosn: $csosn->value);

            case Csosn::IcmsCobradoAnteriormentePorSt:
                return new IcmsResultado(origem: $ctx->origem->value, csosn: $csosn->value, st: $this->stRetida($ctx));

            case Csosn::TributadaSemPermissaoDeCredito:
            case Csosn::IsencaoIcmsParaFaixaDeReceitaBruta:
                return new IcmsResultado(origem: $ctx->origem->value, csosn: $csosn->value);

            case Csosn::Outros:
                $base = $ctx->percentualReducaoBc !== null ? $ctx->baseComReducao() : $basePropria;
                $st = $ctx->modBcSt !== null ? $this->stPropria($ctx, $base)
                    : ($ctx->baseCalculoStRetida !== null ? $this->stRetida($ctx) : null);
                $trio = $ctx->aliquotaIcms !== null;

                return new IcmsResultado(
                    origem: $ctx->origem->value,
                    csosn: $csosn->value,
                    modBc: $trio ? $ctx->modBc->value : null,
                    baseCalculo: $trio ? $base : null,
                    aliquota: $trio ? ArredondadorBancario::arredondar((string) $ctx->aliquotaIcms, 4) : null,
                    valor: $trio ? Matematica::percentualDe($base, (string) $ctx->aliquotaIcms) : null,
                    percentualReducaoBc: $this->pct($ctx->percentualReducaoBc),
                    fcpPercentual: $this->pct($ctx->aliquotaFcp),
                    valorFcp: $ctx->aliquotaFcp === null || ! $trio ? null : Matematica::percentualDe($base, $ctx->aliquotaFcp),
                    percentualCreditoSimples: $this->pct($ctx->percentualCreditoSimples),
                    valorCreditoSimples: $credito,
                    st: $st,
                    difal: $this->difalSeAplicavel($ctx, $base),
                );

            default:
                throw new TaxCalculationException("CSOSN {$csosn->value} não tratado."); // @codeCoverageIgnore
        }
    }

    /** DIFAL inadmissível: CST/CSOSN sem tributação própria interestadual. */
    private function difalNaoAdmitido(NfeTaxContext $ctx): bool
    {
        if ($ctx->cst !== null) {
            return in_array($ctx->cst, [CstIcms::Isenta, CstIcms::NaoTributada, CstIcms::IcmsCobradoAnteriormentePorSt], true);
        }

        return $ctx->csosn !== Csosn::Outros;
    }

    /** pCredSN só existe no Simples Nacional — CSOSN 101/201/900. */
    private function creditoSimplesNaoAdmitido(NfeTaxContext $ctx): bool
    {
        if ($ctx->cst !== null) {
            return true;
        }

        return ! in_array(
            $ctx->csosn,
            [Csosn::TributadaComPermissaoDeCredito, Csosn::TributadaComPermissaoDeCreditoECobrancaPorSt, Csosn::Outros],
            true,
        );
    }

    /** Normaliza percentuais em 4 casas (contrato trata percentuais como decimais). */
    private function pct(?string $valor): ?string
    {
        return $valor === null ? null : ArredondadorBancario::arredondar($valor, 4);
    }

    private function icmsTributado(NfeTaxContext $ctx, string $base, ?IcmsStResultado $st): IcmsResultado
    {
        if ($ctx->aliquotaIcms === null) {
            throw MissingFieldException::campo('aliquotaIcms', 'CST tributado');
        }

        return new IcmsResultado(
            origem: $ctx->origem->value,
            cst: $ctx->cst?->value,
            modBc: $ctx->modBc->value,
            baseCalculo: $base,
            aliquota: ArredondadorBancario::arredondar($ctx->aliquotaIcms, 4),
            valor: Matematica::percentualDe($base, $ctx->aliquotaIcms),
            percentualReducaoBc: $this->pct($ctx->percentualReducaoBc),
            fcpPercentual: $this->pct($ctx->aliquotaFcp),
            valorFcp: $ctx->aliquotaFcp === null ? null : Matematica::percentualDe($base, $ctx->aliquotaFcp),
            st: $st,
            difal: $this->difalSeAplicavel($ctx, $base),
        );
    }

    private function stPropria(NfeTaxContext $ctx, string $basePropria): IcmsStResultado
    {
        if ($ctx->modBcSt === null || $ctx->aliquotaIcmsSt === null) {
            throw MissingFieldException::campo('st (modBcSt + aliquotaSt)', 'CST/CSOSN com ST própria');
        }

        $baseSt = $basePropria;
        if ($ctx->percentualMva !== null) {
            $baseSt = Matematica::somar($baseSt, Matematica::percentualDe($baseSt, $ctx->percentualMva), 2);
        }
        if ($ctx->percentualReducaoBcSt !== null) {
            $baseSt = Matematica::subtrair($baseSt, Matematica::percentualDe($baseSt, $ctx->percentualReducaoBcSt), 2);
        }

        return new IcmsStResultado(
            modBcSt: $ctx->modBcSt->value,
            percentualMva: $this->pct($ctx->percentualMva),
            percentualReducaoBcSt: $this->pct($ctx->percentualReducaoBcSt),
            baseCalculoSt: $baseSt,
            aliquotaSt: ArredondadorBancario::arredondar($ctx->aliquotaIcmsSt, 4),
            valorSt: Matematica::percentualDe($baseSt, $ctx->aliquotaIcmsSt),
            fcpPercentualSt: $this->pct($ctx->aliquotaFcpSt),
            valorFcpSt: $ctx->aliquotaFcpSt === null ? null : Matematica::percentualDe($baseSt, $ctx->aliquotaFcpSt),
        );
    }

    private function stRetida(NfeTaxContext $ctx): IcmsStResultado
    {
        if ($ctx->baseCalculoStRetida === null || $ctx->aliquotaStRetida === null) {
            throw MissingFieldException::campo('st (baseCalculoStRetida + aliquotaStRetida)', 'CST 60 / CSOSN 500');
        }

        $valorStRetido = $ctx->valorStRetido ?? Matematica::percentualDe($ctx->baseCalculoStRetida, $ctx->aliquotaStRetida);

        return new IcmsStResultado(
            baseCalculoStRetido: Matematica::escalar($ctx->baseCalculoStRetida, 2),
            aliquotaStRetida: ArredondadorBancario::arredondar($ctx->aliquotaStRetida, 4),
            valorStRetido: $valorStRetido,
            valorIcmsSubstituto: $ctx->valorIcmsSubstituto,
            fcpPercentualStRetido: $this->pct($ctx->fcpPercentualStRetido),
            valorFcpStRetido: $ctx->fcpPercentualStRetido === null
                ? null
                : Matematica::percentualDe($ctx->baseCalculoStRetida, $ctx->fcpPercentualStRetido),
        );
    }

    private function difalSeAplicavel(NfeTaxContext $ctx, string $base): ?DifalResultado
    {
        if (! $ctx->difal) {
            return null;
        }

        if (! in_array($ctx->aliquotaInterestadual, [4, 7, 12], true) || $ctx->aliquotaInternaUfDestino === null) {
            throw new ValidationException(
                'DIFAL exige aliquotaInterestadual = 4/7/12 e aliquotaInternaUfDestino.'
            );
        }

        $interna = $ctx->aliquotaInternaUfDestino;
        $interestadual = (string) $ctx->aliquotaInterestadual;

        if (Matematica::comparar($interna, $interestadual, 4) < 0) {
            throw new TaxInconsistencyException(
                "DIFAL: alíquota interna da UF de destino ({$interna}) menor que a interestadual ({$interestadual}) — vICMSUFDest seria negativo."
            );
        }

        // MOC (rejeições 815/816): vICMSUFDest = BC × (interna − interestadual) —
        // o ICMS próprio já remete BC × interestadual à UF de origem. Partilha
        // 100% destino desde 2019 → vICMSUFRemet = 0.
        $diferenca = Matematica::subtrair($interna, $interestadual, 4);

        return new DifalResultado(
            aliquotaInterestadual: $ctx->aliquotaInterestadual,
            baseDestino: $base,
            aliquotaDestino: ArredondadorBancario::arredondar($interna, 4),
            valorIcmsDestino: Matematica::percentualDe($base, $diferenca),
            valorIcmsOrigem: '0.00',
            fcpPercentualDestino: $this->pct($ctx->aliquotaFcpUfDestino),
            valorFcpDestino: $ctx->aliquotaFcpUfDestino === null ? null : Matematica::percentualDe($base, $ctx->aliquotaFcpUfDestino),
        );
    }

    // ------------------------------------------------------------- Federais

    private function calcularIpi(NfeTaxContext $ctx): ?ImpostoTrioResultado
    {
        $cst = $ctx->cstIpi;
        if ($cst === null) {
            return null;
        }

        if ($cst->tributado()) {
            if ($ctx->aliquotaIpi === null) {
                throw MissingFieldException::campo('aliquotaIpi', "IPI CST {$cst->value}");
            }

            return new ImpostoTrioResultado(
                cst: $cst->value,
                baseCalculo: $ctx->basePropria(),
                aliquota: ArredondadorBancario::arredondar($ctx->aliquotaIpi, 4),
                valor: Matematica::percentualDe($ctx->basePropria(), $ctx->aliquotaIpi),
                cEnq: $ctx->cEnqIpi,
            );
        }

        return new ImpostoTrioResultado(cst: $cst->value, cEnq: $ctx->cEnqIpi);
    }

    private function calcularPisCofins(?CstPisCofins $cst, ?string $aliquota, NfeTaxContext $ctx): ?ImpostoTrioResultado
    {
        if ($cst === null) {
            return null;
        }

        if ($cst->exigeAliquota()) {
            if ($aliquota === null) {
                throw MissingFieldException::campo('aliquota', "PIS/COFINS CST {$cst->value}");
            }

            return new ImpostoTrioResultado(
                cst: $cst->value,
                baseCalculo: $ctx->basePropria(),
                aliquota: ArredondadorBancario::arredondar($aliquota, 4),
                valor: Matematica::percentualDe($ctx->basePropria(), $aliquota),
            );
        }

        if ($cst->admiteAliquotaOpcional()) {
            if ($aliquota === null) {
                return new ImpostoTrioResultado(cst: $cst->value);
            }

            return new ImpostoTrioResultado(
                cst: $cst->value,
                baseCalculo: $ctx->basePropria(),
                aliquota: ArredondadorBancario::arredondar($aliquota, 4),
                valor: Matematica::percentualDe($ctx->basePropria(), $aliquota),
            );
        }

        return new ImpostoTrioResultado(cst: $cst->value);
    }

    // -------------------------------------------------------------- Reforma

    private function calcularIbsCbs(NfeTaxContext $ctx): IbsCbsResultado
    {
        $entrada = $ctx->ibsCbs;
        \assert($entrada !== null);

        if (trim($entrada->cstIbsCbs) === '' || strlen($entrada->cstIbsCbs) !== 3) {
            throw new ValidationException('CST do IBS/CBS deve ter 3 dígitos (tabela SEPEC).');
        }
        if (strlen($entrada->cClassTrib) !== 6) {
            throw new ValidationException('cClassTrib do IBS/CBS é obrigatório e deve ter 6 dígitos.');
        }

        $base = $ctx->basePropria();

        return new IbsCbsResultado(
            cstIbsCbs: $entrada->cstIbsCbs,
            cClassTrib: $entrada->cClassTrib,
            baseCalculo: $base,
            aliquotaCbs: $entrada->aliquotaCbs,
            percentualReducaoCbs: $entrada->percentualReducaoCbs,
            valorCbs: $this->valorComReducao($base, $entrada->aliquotaCbs, $entrada->percentualReducaoCbs),
            aliquotaIbsEstadual: $entrada->aliquotaIbsEstadual,
            percentualReducaoIbsEstadual: $entrada->percentualReducaoIbsEstadual,
            valorIbsEstadual: $this->valorComReducao($base, $entrada->aliquotaIbsEstadual, $entrada->percentualReducaoIbsEstadual),
            aliquotaIbsMunicipal: $entrada->aliquotaIbsMunicipal,
            percentualReducaoIbsMunicipal: $entrada->percentualReducaoIbsMunicipal,
            valorIbsMunicipal: $this->valorComReducao($base, $entrada->aliquotaIbsMunicipal, $entrada->percentualReducaoIbsMunicipal),
        );
    }

    private function calcularIs(NfeTaxContext $ctx): IsResultado
    {
        $entrada = $ctx->is;
        \assert($entrada !== null);

        if (strlen($entrada->cstIs) !== 2) {
            throw new ValidationException('CST do IS deve ter 2 dígitos (tabela SEPEC).');
        }
        if (strlen($entrada->cClassTribIs) !== 6) {
            throw new ValidationException('cClassTribIs do IS é obrigatório e deve ter 6 dígitos.');
        }
        if (($entrada->unidadeTributavel === null) !== ($entrada->quantidadeTributavel === null)) {
            throw new ValidationException('IS por quantidade: informe unidadeTributavel e quantidadeTributavel juntos.');
        }
        if ($entrada->aliquota === null) {
            throw MissingFieldException::campo('aliquota', 'Imposto Seletivo');
        }

        $base = $entrada->baseCalculoOverride !== null
            ? Matematica::escalar($entrada->baseCalculoOverride, 2)
            : $ctx->basePropria();

        return new IsResultado(
            cstIs: $entrada->cstIs,
            cClassTribIs: $entrada->cClassTribIs,
            baseCalculo: $base,
            aliquota: ArredondadorBancario::arredondar($entrada->aliquota, 4),
            valor: Matematica::percentualDe($base, $entrada->aliquota),
            unidadeTributavel: $entrada->unidadeTributavel,
            quantidadeTributavel: $entrada->quantidadeTributavel,
        );
    }

    private function valorComReducao(string $base, ?string $aliquota, ?string $percentualReducao): ?string
    {
        if ($aliquota === null) {
            return null;
        }

        $baseEfetiva = $base;
        if ($percentualReducao !== null) {
            $baseEfetiva = Matematica::subtrair($base, Matematica::percentualDe($base, $percentualReducao), 2);
        }

        return Matematica::percentualDe($baseEfetiva, $aliquota);
    }
}
