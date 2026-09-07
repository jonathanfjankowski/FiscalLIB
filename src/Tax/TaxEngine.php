<?php

declare(strict_types=1);

namespace FiscalLib\Tax;

use FiscalLib\Common\ArredondadorBancario;
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
 * CST/CSOSN fora do contrato (02, 15, 30, 53, 61, ICMSPart...) falham alto aqui —
 * a API rejeitaria com 422.
 */
final class TaxEngine implements TaxEngineInterface
{
    private const CSTS_SUPORTADOS = ['00', '10', '20', '40', '41', '51', '60', '70', '90'];
    private const CSOSNS_SUPORTADOS = ['101', '102', '103', '201', '202', '203', '300', '400', '500', '900'];

    public function calcularNfe(NfeTaxContext $ctx): NfeTaxResultado
    {
        if ($ctx->origem < 0 || $ctx->origem > 8) {
            throw new ValidationException("Origem da mercadoria ({$ctx->origem}) inválida — use 0 a 8.");
        }

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
        $basePisCofins = $ctx->baseTributaria();

        $valorIssqn = null;
        if ($ctx->aliquotaIssqn !== null && $ctx->tributacaoIssqn === 1) {
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
            aliquotaPis: $ctx->aliquotaPis,
            valorPis: $valorPis,
            aliquotaCofins: $ctx->aliquotaCofins,
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

    // ------------------------------------------------------------------ ICMS

    private function calcularIcms(NfeTaxContext $ctx): ?IcmsResultado
    {
        if ($ctx->cst === null && $ctx->csosn === null) {
            return null;
        }
        if ($ctx->cst !== null && $ctx->csosn !== null) {
            throw TaxInconsistencyException::combinacaoNaoSuportada('informe cst OU csosn — nunca os dois.');
        }

        if ($ctx->cst !== null) {
            return $this->icmsPorCst($ctx);
        }

        return $this->icmsPorCsosn($ctx);
    }

    private function icmsPorCst(NfeTaxContext $ctx): IcmsResultado
    {
        $cst = $ctx->cst ?? '';
        if (! in_array($cst, self::CSTS_SUPORTADOS, true)) {
            throw TaxInconsistencyException::combinacaoNaoSuportada(
                "CST '{$cst}' fora do contrato (suporta 00/10/20/40/41/51/60/70/90; CST 02/15/30/53/61, ICMSPart e ICMSST não suportados)."
            );
        }

        $basePropria = $ctx->basePropria();

        switch ($cst) {
            case '00':
                if ($ctx->percentualReducaoBc !== null) {
                    throw TaxInconsistencyException::combinacaoNaoSuportada('CST 00 não admite redução de BC — use CST 20.');
                }

                return $this->icmsTributado($ctx, $basePropria, null);

            case '10':
                return $this->icmsTributado($ctx, $basePropria, $this->stPropria($ctx, $basePropria));

            case '20':
                if ($ctx->percentualReducaoBc === null) {
                    throw MissingFieldException::campo('percentualReducaoBc', 'CST 20');
                }

                return $this->icmsTributado($ctx, $ctx->baseComReducao(), null);

            case '40':
            case '41':
                return new IcmsResultado(origem: $ctx->origem, cst: $cst);

            case '51':
                if ($ctx->aliquotaIcms === null) {
                    throw MissingFieldException::campo('aliquotaIcms', 'CST 51 (diferimento)');
                }
                $base = $ctx->baseComReducao();
                $valorOperacao = Matematica::percentualDe($base, $ctx->aliquotaIcms);
                $percentualDif = $ctx->percentualDiferimento ?? '0';
                $valorDiferido = Matematica::percentualDe($valorOperacao, $percentualDif);

                // `valor` só pode ir quando não há diferimento — o validador da
                // API exige valor = base × alíquota, que só vale sem diferimento.
                $valor = bccomp($percentualDif, '0', 2) === 0 ? $valorOperacao : null;

                return new IcmsResultado(
                    origem: $ctx->origem,
                    cst: $cst,
                    modBc: $ctx->modBc ?? '3',
                    baseCalculo: $base,
                    aliquota: ArredondadorBancario::arredondar($ctx->aliquotaIcms, 4),
                    valor: $valor,
                    percentualReducaoBc: $this->pct($ctx->percentualReducaoBc),
                    fcpPercentual: $this->pct($ctx->aliquotaFcp),
                    valorFcp: $ctx->aliquotaFcp === null ? null : Matematica::percentualDe($base, $ctx->aliquotaFcp),
                    valorIcmsOperacao: $valorOperacao,
                    percentualDiferimento: $this->pct($ctx->percentualDiferimento),
                    valorIcmsDiferido: bccomp($percentualDif, '0', 2) === 0 ? null : $valorDiferido,
                    difal: $this->difalSeAplicavel($ctx, $base),
                );

            case '60':
                return new IcmsResultado(
                    origem: $ctx->origem,
                    cst: $cst,
                    st: $this->stRetida($ctx),
                );

            case '70':
                if ($ctx->percentualReducaoBc === null) {
                    throw MissingFieldException::campo('percentualReducaoBc', 'CST 70');
                }
                $base = $ctx->baseComReducao();

                return $this->icmsTributado($ctx, $base, $this->stPropria($ctx, $base));

            default:
                throw new TaxCalculationException("CST {$cst} não tratado."); // @codeCoverageIgnore

            case '90':
                $base = $ctx->percentualReducaoBc !== null ? $ctx->baseComReducao() : $basePropria;
                $st = $ctx->modBcSt !== null ? $this->stPropria($ctx, $base)
                    : ($ctx->baseCalculoStRetida !== null ? $this->stRetida($ctx) : null);
                $trio = $ctx->aliquotaIcms !== null;

                return new IcmsResultado(
                    origem: $ctx->origem,
                    cst: $cst,
                    modBc: $trio ? ($ctx->modBc ?? '3') : null,
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
        }
    }

    private function icmsPorCsosn(NfeTaxContext $ctx): IcmsResultado
    {
        $csosn = $ctx->csosn ?? '';
        if (! in_array($csosn, self::CSOSNS_SUPORTADOS, true)) {
            throw TaxInconsistencyException::combinacaoNaoSuportada(
                "CSOSN '{$csosn}' fora do contrato (suporta 101–900)."
            );
        }

        $basePropria = $ctx->basePropria();
        $credito = $ctx->percentualCreditoSimples === null
            ? null
            : Matematica::percentualDe($basePropria, $ctx->percentualCreditoSimples);

        switch ($csosn) {
            case '101':
                if ($ctx->percentualCreditoSimples === null) {
                    throw MissingFieldException::campo('percentualCreditoSimples', 'CSOSN 101');
                }

                return new IcmsResultado(
                    origem: $ctx->origem,
                    csosn: $csosn,
                    percentualCreditoSimples: $ctx->percentualCreditoSimples,
                    valorCreditoSimples: $credito,
                );

            case '201':
                return new IcmsResultado(
                    origem: $ctx->origem,
                    csosn: $csosn,
                    percentualCreditoSimples: $ctx->percentualCreditoSimples,
                    valorCreditoSimples: $credito,
                    st: $this->stPropria($ctx, $basePropria),
                );

            case '202':
            case '203':
                return new IcmsResultado(origem: $ctx->origem, csosn: $csosn, st: $this->stPropria($ctx, $basePropria));

            case '300':
            case '400':
                return new IcmsResultado(origem: $ctx->origem, csosn: $csosn);

            case '500':
                return new IcmsResultado(origem: $ctx->origem, csosn: $csosn, st: $this->stRetida($ctx));

            default:
                throw new TaxCalculationException("CSOSN {$csosn} não tratado."); // @codeCoverageIgnore

            case '102':
            case '103':
                return new IcmsResultado(origem: $ctx->origem, csosn: $csosn);

            case '900':
                $base = $ctx->percentualReducaoBc !== null ? $ctx->baseComReducao() : $basePropria;
                $st = $ctx->modBcSt !== null ? $this->stPropria($ctx, $base)
                    : ($ctx->baseCalculoStRetida !== null ? $this->stRetida($ctx) : null);
                $trio = $ctx->aliquotaIcms !== null;

                return new IcmsResultado(
                    origem: $ctx->origem,
                    csosn: $csosn,
                    modBc: $trio ? ($ctx->modBc ?? '3') : null,
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
        }
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
            origem: $ctx->origem,
            cst: $ctx->cst,
            modBc: $ctx->modBc ?? '3',
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
            modBcSt: $ctx->modBcSt,
            percentualMva: $this->pct($ctx->percentualMva),
            percentualReducaoBcSt: $this->pct($ctx->percentualReducaoBcSt),
            baseCalculoSt: $baseSt,
            aliquotaSt: ArredondadorBancario::arredondar($ctx->aliquotaIcmsSt, 4),
            valorSt: Matematica::percentualDe($baseSt, $ctx->aliquotaIcmsSt),
            fcpPercentualSt: $ctx->aliquotaFcpSt,
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
            fcpPercentualStRetido: $ctx->fcpPercentualStRetido,
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

        return new DifalResultado(
            aliquotaInterestadual: $ctx->aliquotaInterestadual,
            baseDestino: $base,
            aliquotaDestino: ArredondadorBancario::arredondar($interna, 4),
            valorIcmsDestino: Matematica::percentualDe($base, $interna),
            valorIcmsOrigem: '0.00',
            fcpPercentualDestino: $ctx->aliquotaFcpUfDestino,
            valorFcpDestino: $ctx->aliquotaFcpUfDestino === null ? null : Matematica::percentualDe($base, $ctx->aliquotaFcpUfDestino),
        );
    }

    // ------------------------------------------------------------- Federais

    private function calcularIpi(NfeTaxContext $ctx): ?ImpostoTrioResultado
    {
        if ($ctx->cstIpi === null) {
            return null;
        }

        $cst = $ctx->cstIpi;

        if (in_array($cst, ['00', '49', '50', '99'], true)) {
            if ($ctx->aliquotaIpi === null) {
                throw MissingFieldException::campo('aliquotaIpi', "IPI CST {$cst}");
            }

            return new ImpostoTrioResultado(
                cst: $cst,
                baseCalculo: $ctx->basePropria(),
                aliquota: ArredondadorBancario::arredondar($ctx->aliquotaIpi, 4),
                valor: Matematica::percentualDe($ctx->basePropria(), $ctx->aliquotaIpi),
                cEnq: $ctx->cEnqIpi,
            );
        }

        if (in_array($cst, ['01', '02', '03', '04', '05', '51'], true)) {
            return new ImpostoTrioResultado(cst: $cst, cEnq: $ctx->cEnqIpi);
        }

        throw TaxInconsistencyException::combinacaoNaoSuportada("IPI CST '{$cst}' fora do contrato (00, 01–05, 49, 50, 51, 99).");
    }

    private function calcularPisCofins(?string $cst, ?string $aliquota, NfeTaxContext $ctx): ?ImpostoTrioResultado
    {
        if ($cst === null) {
            return null;
        }

        if ($cst === '03') {
            throw TaxInconsistencyException::combinacaoNaoSuportada('PIS/COFINS CST 03 (por quantidade) não suportado no contrato atual.');
        }

        if (in_array($cst, ['01', '02'], true)) {
            if ($aliquota === null) {
                throw MissingFieldException::campo('aliquota', "PIS/COFINS CST {$cst}");
            }

            return new ImpostoTrioResultado(
                cst: $cst,
                baseCalculo: $ctx->basePropria(),
                aliquota: ArredondadorBancario::arredondar($aliquota, 4),
                valor: Matematica::percentualDe($ctx->basePropria(), $aliquota),
            );
        }

        if (in_array($cst, ['04', '05', '06', '07', '08', '09'], true)) {
            return new ImpostoTrioResultado(cst: $cst);
        }

        if ($cst === '99') {
            if ($aliquota === null) {
                return new ImpostoTrioResultado(cst: $cst);
            }

            return new ImpostoTrioResultado(
                cst: $cst,
                baseCalculo: $ctx->basePropria(),
                aliquota: ArredondadorBancario::arredondar($aliquota, 4),
                valor: Matematica::percentualDe($ctx->basePropria(), $aliquota),
            );
        }

        throw TaxInconsistencyException::combinacaoNaoSuportada("PIS/COFINS CST '{$cst}' fora do contrato (01, 02, 04–09, 99).");
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
