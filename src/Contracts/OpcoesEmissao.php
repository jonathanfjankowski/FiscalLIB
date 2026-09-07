<?php

declare(strict_types=1);

namespace FiscalLib\Contracts;

/**
 * Opções de emissão. A idempotency key deve ser ESTÁVEL por tentativa lógica:
 * gere quando o pedido entrar no faturamento e reutilize em qualquer retry —
 * trocar a key pode criar documentos duplicados e consumir números.
 */
final class OpcoesEmissao
{
    public function __construct(
        public readonly ?string $idempotencyKey = null,
    ) {
    }

    public static function comIdempotencia(string $idempotencyKey): self
    {
        return new self($idempotencyKey);
    }
}
