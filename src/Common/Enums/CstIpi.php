<?php

declare(strict_types=1);

namespace FiscalLib\Common\Enums;

/**
 * CST do IPI — subconjunto suportado pelo contrato FiscalAPI
 * (fora dele: 52–55, 98 e as entradas 50–69).
 */
enum CstIpi: string
{
    case EntradaComCredito = '00';
    case EntradaTributadaAliquotaZero = '01';
    case EntradaIsenta = '02';
    case EntradaNaoTributada = '03';
    case EntradaImune = '04';
    case EntradaComSuspensao = '05';
    case OutrasEntradas = '49';
    case SaidaTributada = '50';
    case SaidaTributadaAliquotaZero = '51';
    case OutrasSaidas = '99';

    /** Grupo que exige base × alíquota (00, 49, 50, 99). */
    public function tributado(): bool
    {
        return in_array($this, [self::EntradaComCredito, self::OutrasEntradas, self::SaidaTributada, self::OutrasSaidas], true);
    }
}
