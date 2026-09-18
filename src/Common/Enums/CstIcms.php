<?php

declare(strict_types=1);

namespace FiscalLib\Common\Enums;

/**
 * CST do ICMS (regime normal) — subconjunto suportado pelo contrato FiscalAPI.
 * Fora do contrato: 02, 15, 30, 53, 61, ICMSPart e ICMSST.
 */
enum CstIcms: string
{
    case TributadaIntegralmente = '00';
    case TributadaComCobrancaIcmsPorSt = '10';
    case ComReducaoDeBaseDeCalculo = '20';
    case Isenta = '40';
    case NaoTributada = '41';
    case Diferimento = '51';
    case IcmsCobradoAnteriormentePorSt = '60';
    case ComReducaoDeBaseECobrancaPorSt = '70';
    case Outros = '90';
}
