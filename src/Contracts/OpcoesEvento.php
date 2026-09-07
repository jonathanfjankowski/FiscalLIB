<?php

declare(strict_types=1);

namespace FiscalLib\Contracts;

/**
 * Opções de eventos (idempotência).
 */
final class OpcoesEvento
{
    public function __construct(
        public readonly ?string $idempotencyKey = null,
    ) {
    }
}
