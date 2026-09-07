<?php

declare(strict_types=1);

namespace FiscalLib\Documento;

/**
 * Resultado de um evento fiscal (cancelamento, CC-e, inutilização).
 */
final class ResultadoEvento
{
    public function __construct(
        public readonly string $eventoId,
        public readonly string $tipo,
        public readonly string $status,
        public readonly ?string $documentoId = null,
        public readonly ?string $criadoEm = null,
        public readonly ?string $motivoStatus = null,
        public readonly ?array $raw = null,
    ) {
    }
}
