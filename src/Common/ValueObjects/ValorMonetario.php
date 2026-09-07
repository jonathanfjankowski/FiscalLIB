<?php

declare(strict_types=1);

namespace FiscalLib\Common\ValueObjects;

use FiscalLib\Common\Matematica;
use FiscalLib\Exceptions\InvalidValueException;

/**
 * Valor monetário não negativo com 2 casas decimais, armazenado como string.
 */
final class ValorMonetario implements \Stringable
{
    private function __construct(private readonly string $valor)
    {
    }

    public static function criar(string|int|float $valor): self
    {
        $texto = Matematica::normalizar($valor);

        if (bccomp($texto, '0', 6) < 0) {
            throw InvalidValueException::campo('valor', $texto, 'não pode ser negativo.');
        }

        return new self(Matematica::escalar($texto, 2));
    }

    public static function zero(): self
    {
        return new self('0.00');
    }

    public function valor(): string
    {
        return $this->valor;
    }

    public function isZero(): bool
    {
        return bccomp($this->valor, '0', 2) === 0;
    }

    public function somar(self $outro): self
    {
        return new self(Matematica::somar($this->valor, $outro->valor));
    }

    public function __toString(): string
    {
        return $this->valor;
    }
}
