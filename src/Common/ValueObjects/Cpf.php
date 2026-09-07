<?php

declare(strict_types=1);

namespace FiscalLib\Common\ValueObjects;

use FiscalLib\Exceptions\InvalidValueException;

/**
 * CPF com validação de dígito verificador.
 */
final class Cpf implements \Stringable
{
    private function __construct(private readonly string $valor)
    {
    }

    public static function criar(string $valor): self
    {
        $limpo = preg_replace('/[^0-9]/', '', $valor) ?? '';

        if (strlen($limpo) !== 11) {
            throw InvalidValueException::campo('cpf', $valor, 'CPF deve ter 11 dígitos.');
        }

        if (preg_match('/^(\d)\1{10}$/', $limpo)) {
            throw InvalidValueException::campo('cpf', $valor, 'dígito verificador inválido.');
        }

        $calcular = function (string $base): int {
            $peso = strlen($base) + 1;
            $soma = 0;
            foreach (str_split($base) as $digito) {
                $soma += ((int) $digito) * $peso;
                $peso--;
            }
            $resto = ($soma * 10) % 11;

            return $resto === 10 ? 0 : $resto;
        };

        if ($calcular(substr($limpo, 0, 9)) !== (int) $limpo[9]
            || $calcular(substr($limpo, 0, 10)) !== (int) $limpo[10]) {
            throw InvalidValueException::campo('cpf', $valor, 'dígito verificador inválido.');
        }

        return new self($limpo);
    }

    public static function valido(string $valor): bool
    {
        try {
            self::criar($valor);

            return true;
        } catch (InvalidValueException) {
            return false;
        }
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
