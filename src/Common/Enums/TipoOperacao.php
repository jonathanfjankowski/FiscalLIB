<?php

declare(strict_types=1);

namespace FiscalLib\Common\Enums;

/**
 * Tipo da operação (tpNF).
 */
enum TipoOperacao: string
{
    case Entrada = 'entrada';
    case Saida = 'saida';
}
