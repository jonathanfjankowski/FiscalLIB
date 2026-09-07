<?php

declare(strict_types=1);

namespace FiscalLib\Common\ValueObjects;

use FiscalLib\Exceptions\InvalidValueException;

/**
 * CNPJ com validação de dígito verificador.
 * Aceita o formato numérico clássico (14 dígitos) e o formato alfanumérico
 * da NT 009/2026 (14 posições [0-9A-Z], vigente a partir de jul/2026) —
 * no alfanumérico valida apenas o charset (algoritmo de DV ainda não
 * definido em implementação amplamente acordada).
 */
final class Cnpj implements \Stringable
{
    private function __construct(
        private readonly string $valor,
        private readonly bool $alfanumerico,
    ) {
    }

    public static function criar(string $valor): self
    {
        $limpo = strtoupper(preg_replace('/[^0-9A-Za-z]/', '', $valor) ?? '');

        if ($limpo === '') {
            throw InvalidValueException::campo('cnpj', $valor, 'vazio.');
        }

        if (ctype_digit($limpo)) {
            if (strlen($limpo) !== 14) {
                throw InvalidValueException::campo('cnpj', $valor, 'CNPJ numérico deve ter 14 dígitos.');
            }
            if (!self::dvNumericoValido($limpo)) {
                throw InvalidValueException::campo('cnpj', $valor, 'dígito verificador inválido.');
            }

            return new self($limpo, false);
        }

        if (strlen($limpo) !== 14 || !preg_match('/^[0-9A-Z]{14}$/', $limpo)) {
            throw InvalidValueException::campo('cnpj', $valor, 'CNPJ alfanumérico deve ter 14 posições [0-9A-Z] (NT 009/2026).');
        }

        return new self($limpo, true);
    }

    /** Valida sem lançar. */
    public static function valido(string $valor): bool
    {
        try {
            self::criar($valor);

            return true;
        } catch (InvalidValueException) {
            return false;
        }
    }

    private static function dvNumericoValido(string $cnpj): bool
    {
        if (preg_match('/^(\d)\1{13}$/', $cnpj)) {
            return false;
        }

        $calcular = function (string $base): int {
            // Pesos: 12 posições → 5..2; 13 posições → 6..2 (reiniciando em 9)
            $peso = strlen($base) === 13 ? 6 : 5;
            $soma = 0;
            foreach (str_split($base) as $digito) {
                $soma += ((int) $digito) * $peso;
                $peso = $peso === 2 ? 9 : $peso - 1;
            }
            $resto = $soma % 11;

            return $resto < 2 ? 0 : 11 - $resto;
        };

        return $calcular(substr($cnpj, 0, 12)) === (int) $cnpj[12]
            && $calcular(substr($cnpj, 0, 13)) === (int) $cnpj[13];
    }

    public function isAlfanumerico(): bool
    {
        return $this->alfanumerico;
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
