<?php

declare(strict_types=1);

namespace FiscalLib\Exceptions;

/**
 * Campo obrigatório ausente no builder/documento.
 */
final class MissingFieldException extends ValidationException
{
    public static function campo(string $campo, ?string $contexto = null): self
    {
        $onde = $contexto === null ? '' : " ({$contexto})";

        return new self("Campo obrigatório ausente: {$campo}{$onde}.");
    }
}
