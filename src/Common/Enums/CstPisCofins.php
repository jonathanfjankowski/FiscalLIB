<?php

declare(strict_types=1);

namespace FiscalLib\Common\Enums;

/**
 * CST de PIS/COFINS (saídas) — subconjunto suportado pelo contrato FiscalAPI.
 * O CST 03 (tributação monofásica por quantidade) não faz parte do contrato.
 */
enum CstPisCofins: string
{
    case OperacaoTributavelCumulativo = '01';
    case OperacaoTributavelAliquotaDiferenciada = '02';
    case OperacaoTributavelMonofasicaAliquotaZero = '04';
    case OperacaoComSubstituicaoTributaria = '05';
    case OperacaoTributavelAliquotaZero = '06';
    case OperacaoIsenta = '07';
    case OperacaoSemIncidencia = '08';
    case OperacaoComSuspensao = '09';
    case OutrasOperacoes = '99';

    /** 01/02 exigem base × alíquota. */
    public function exigeAliquota(): bool
    {
        return in_array($this, [self::OperacaoTributavelCumulativo, self::OperacaoTributavelAliquotaDiferenciada], true);
    }

    /** Somente o 99 aceita alíquota facultativa (sem alíquota → sem valores). */
    public function admiteAliquotaOpcional(): bool
    {
        return $this === self::OutrasOperacoes;
    }
}
