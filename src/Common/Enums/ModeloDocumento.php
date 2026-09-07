<?php

declare(strict_types=1);

namespace FiscalLib\Common\Enums;

/**
 * Modelo de documento fiscal.
 */
enum ModeloDocumento: int
{
    case Nfe = 55;
    case Nfce = 65;
}
