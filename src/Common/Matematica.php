<?php

declare(strict_types=1);

namespace FiscalLib\Common;

/**
 * Aritmética decimal exata sobre strings com bcmath.
 * Nenhum cálculo fiscal passa por float.
 */
final class Matematica
{
    /** Normaliza qualquer entrada numérica para string decimal. */
    public static function normalizar(string|int|float $valor): string
    {
        if (is_string($valor)) {
            $limpo = str_replace([' ', ','], ['', '.'], trim($valor));
            if ($limpo === '' || !is_numeric($limpo)) {
                throw new \InvalidArgumentException("Valor numérico inválido: '{$valor}'");
            }

            return $limpo;
        }

        if (is_float($valor) && floor($valor) !== $valor) {
            // Floats só entram com precisão trivial (ex.: 100.0). Usar strings
            // para valores com centavos significativos.
            if (abs($valor) > PHP_FLOAT_MAX / 1000) {
                throw new \InvalidArgumentException('Valor float fora de faixa segura.');
            }
        }

        return (string) $valor;
    }

    public static function escalar(string|int|float $valor, int $casas = 2): string
    {
        return ArredondadorBancario::arredondar(self::normalizar($valor), $casas);
    }

    public static function somar(string|int|float $a, string|int|float $b, int $casas = 2): string
    {
        return bcadd(self::normalizar($a), self::normalizar($b), $casas);
    }

    public static function subtrair(string|int|float $a, string|int|float $b, int $casas = 2): string
    {
        return bcsub(self::normalizar($a), self::normalizar($b), $casas);
    }

    public static function multiplicar(string|int|float $a, string|int|float $b, int $casas = 2): string
    {
        return bcmul(self::normalizar($a), self::normalizar($b), $casas);
    }

    public static function dividir(string|int|float $a, string|int|float $b, int $casas = 2): string
    {
        $divisor = self::normalizar($b);
        if (bccomp($divisor, '0', 12) === 0) {
            throw new \InvalidArgumentException('Divisão por zero.');
        }

        return bcdiv(self::normalizar($a), $divisor, $casas);
    }

    /** Percentual: valor × alíquota / 100 */
    public static function percentualDe(string|int|float $valor, string|int|float $aliquota, int $casas = 2): string
    {
        return self::dividir(self::multiplicar($valor, $aliquota, 6), '100', $casas);
    }

    public static function comparar(string|int|float $a, string|int|float $b, int $casas = 2): int
    {
        return bccomp(self::normalizar($a), self::normalizar($b), $casas);
    }

    public static function igual(string|int|float $a, string|int|float $b, string|int|float $tolerancia = '0.01'): bool
    {
        $diferenca = bcsub(self::normalizar($a), self::normalizar($b), 6);
        $tol = self::normalizar($tolerancia);

        return bccomp(self::abs($diferenca), $tol, 6) <= 0;
    }

    public static function abs(string $valor): string
    {
        return str_starts_with($valor, '-') ? substr($valor, 1) : $valor;
    }

    public static function zero(): string
    {
        return '0.00';
    }
}
