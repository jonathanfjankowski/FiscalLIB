<?php

declare(strict_types=1);

namespace FiscalLib\Common\ValueObjects;

use FiscalLib\Common\Matematica;
use FiscalLib\Exceptions\InvalidValueException;

/**
 * Alíquota percentual (0–100), até 4 casas decimais. Armazenada como string.
 */
final class Aliquota implements \Stringable
{
    private function __construct(private readonly string $valor)
    {
    }

    public static function criar(string|int|float $valor): self
    {
        $texto = Matematica::normalizar($valor);

        if (bccomp($texto, '0', 6) < 0 || bccomp($texto, '100', 6) > 0) {
            throw InvalidValueException::campo('aliquota', $texto, 'deve estar entre 0 e 100.');
        }

        return new self(Matematica::escalar($texto, 4));
    }

    public function valor(): string
    {
        return $this->valor;
    }

    public function isZero(): bool
    {
        return bccomp($this->valor, '0', 6) === 0;
    }

    public function __toString(): string
    {
        return $this->valor;
    }
}
