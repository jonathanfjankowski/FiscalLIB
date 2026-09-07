<?php

declare(strict_types=1);

namespace FiscalLib\Common\Enums;

/**
 * Tipo de evento fiscal.
 */
enum TipoEvento: string
{
    case Cancelamento = 'CANCELAMENTO';
    case CartaCorrecao = 'CCE';
    case Inutilizacao = 'INUTILIZACAO';
}
