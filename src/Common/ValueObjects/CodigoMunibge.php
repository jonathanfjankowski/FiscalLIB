<?php

declare(strict_types=1);

namespace FiscalLib\Common\ValueObjects;

use FiscalLib\Exceptions\InvalidValueException;

/**
 * Código IBGE de município (7 dígitos).
 */
final class CodigoMunibge implements \Stringable
{
    private function __construct(private readonly string $valor)
    {
    }

    public static function criar(string $valor): self
    {
        $limpo = preg_replace('/[^0-9]/', '', $valor) ?? '';

        if (strlen($limpo) !== 7) {
            throw InvalidValueException::campo('codigoMunicipioIbge', $valor, 'deve ter 7 dígitos.');
        }

        return new self($limpo);
    }

    public function ufCodigo(): string
    {
        return substr($this->valor, 0, 2);
    }

    public function valor(): string
    {
        return $this->valor;
    }

    public function __toString(): string
    {
        return $this->valor;
    }
}
