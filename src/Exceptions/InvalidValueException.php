<?php

declare(strict_types=1);

namespace FiscalLib\Exceptions;

/**
 * Valor fora do domínio esperado (formato, faixa, dígito verificador...).
 */
final class InvalidValueException extends ValidationException
{
    public static function campo(string $campo, string $valor, string $regra): self
    {
        return new self("Campo '{$campo}' com valor inválido ('{$valor}'): {$regra}");
    }
}
