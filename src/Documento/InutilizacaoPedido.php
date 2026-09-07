<?php

declare(strict_types=1);

namespace FiscalLib\Documento;

use FiscalLib\Common\Enums\Ambiente;
use FiscalLib\Common\Enums\ModeloDocumento;

/**
 * Pedido de inutilização de faixa de numeração (não ligado a um documento).
 */
final class InutilizacaoPedido
{
    public function __construct(
        public readonly Ambiente $ambiente,
        public readonly ModeloDocumento $modelo,
        public readonly int $serie,
        public readonly int $numeroInicial,
        public readonly int $numeroFinal,
        public readonly string $justificativa, // 15–1000 chars
    ) {
    }
}
