<?php

declare(strict_types=1);

namespace FiscalLib\Common\ValueObjects;

use FiscalLib\Exceptions\InvalidValueException;

/**
 * Código NBS — Nomenclatura Brasileira de Serviços (9 dígitos, aceita "1.05.01.00" pontuado).
 */
final class CodigoNbs implements \Stringable
{
    private function __construct(private readonly string $valor)
    {
    }

    public static function criar(string $valor): self
    {
        $limpo = str_replace('.', '', trim($valor));

        if (!preg_match('/^\d{9}$/', $limpo)) {
            throw InvalidValueException::campo('codigoNbs', $valor, 'NBS deve ter 9 dígitos (R-NFS001).');
        }

        return new self($limpo);
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
