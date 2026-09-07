<?php

declare(strict_types=1);

namespace FiscalLib\Exceptions;

/**
 * 5xx, timeout ou falha de rede. Em emissões, reenviar com a MESMA
 * Idempotency-Key é seguro.
 */
final class ApiIndisponivelException extends ApiHttpException
{
    public static function rede(string $detalhe, ?\Throwable $anterior = null): self
    {
        return new self("Emissor indisponível: {$detalhe}", null, null, null, [], $anterior);
    }
}
