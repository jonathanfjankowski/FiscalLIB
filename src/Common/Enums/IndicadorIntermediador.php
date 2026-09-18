<?php

declare(strict_types=1);

namespace FiscalLib\Common\Enums;

/**
 * Indicador de intermediador/marketplace (NT 2020.006 — indIntermed, só NF-e 55).
 */
enum IndicadorIntermediador: int
{
    case SemIntermediador = 0;
    case PlataformaTerceiros = 1;
}
