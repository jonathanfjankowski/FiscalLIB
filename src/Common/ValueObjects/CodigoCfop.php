<?php

declare(strict_types=1);

namespace FiscalLib\Common\ValueObjects;

use FiscalLib\Exceptions\InvalidValueException;

/**
 * CFOP (4 dígitos) com utilitários de operação (R001–R003 da spec).
 */
final class CodigoCfop implements \Stringable
{
    private function __construct(private readonly string $valor)
    {
    }

    public static function criar(string $valor): self
    {
        $limpo = preg_replace('/[^0-9]/', '', $valor) ?? '';

        if (strlen($limpo) !== 4) {
            throw InvalidValueException::campo('cfop', $valor, 'deve ter 4 dígitos.');
        }

        $primeiro = (int) $limpo[0];
        if ($primeiro < 1 || $primeiro > 7) {
            throw InvalidValueException::campo('cfop', $valor, 'primeiro dígito deve indicar operação (1–7).');
        }

        return new self($limpo);
    }

    public function valor(): string
    {
        return $this->valor;
    }

    /** 5xxx/6xxx/7xxx (R001). */
    public function isSaida(): bool
    {
        return in_array($this->valor[0], ['5', '6', '7'], true);
    }

    /** 1xxx/2xxx/3xxx (R002). */
    public function isEntrada(): bool
    {
        return in_array($this->valor[0], ['1', '2', '3'], true);
    }

    /** 7xxx = exterior (R003). */
    public function isExterior(): bool
    {
        return str_starts_with($this->valor, '7');
    }

    public function __toString(): string
    {
        return $this->valor;
    }
}
