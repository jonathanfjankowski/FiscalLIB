<?php

declare(strict_types=1);

namespace FiscalLib\Common;

/**
 * Aritmética decimal exata sobre strings com bcmath.
 * Nenhum cálculo fiscal passa por float.
 *
 * Invariante V004: toda operação devolve o valor com arredondamento bancário
 * (half-to-even) no número de casas pedido — o bcmath sozinho trunca, então os
 * resultados são calculados com dígitos de guarda e arredondados uma única vez.
 */
final class Matematica
{
    private const GUARDA = 6;

    /** Normaliza qualquer entrada numérica para string decimal. */
    public static function normalizar(string|int|float $valor): string
    {
        if (!is_string($valor)) {
            $valor = (string) $valor;
        }

        $limpo = str_replace([' ', ','], ['', '.'], trim($valor));
        // Notação científica ('1e3'), '.5' e '+5' passam em is_numeric, mas o
        // bcmath rejeita: o domínio da lib é decimal puro, opcionalmente negativo.
        if (!preg_match('/^-?\d+(\.\d+)?$/', $limpo)) {
            throw new \InvalidArgumentException("Valor numérico inválido: '{$valor}'");
        }

        return $limpo;
    }

    public static function escalar(string|int|float $valor, int $casas = 2): string
    {
        return ArredondadorBancario::arredondar(self::normalizar($valor), $casas);
    }

    public static function somar(string|int|float $a, string|int|float $b, int $casas = 2): string
    {
        return self::arredondado(bcadd(self::normalizar($a), self::normalizar($b), $casas + self::GUARDA), $casas);
    }

    public static function subtrair(string|int|float $a, string|int|float $b, int $casas = 2): string
    {
        return self::arredondado(bcsub(self::normalizar($a), self::normalizar($b), $casas + self::GUARDA), $casas);
    }

    public static function multiplicar(string|int|float $a, string|int|float $b, int $casas = 2): string
    {
        return self::arredondado(bcmul(self::normalizar($a), self::normalizar($b), $casas + self::GUARDA), $casas);
    }

    public static function dividir(string|int|float $a, string|int|float $b, int $casas = 2): string
    {
        $divisor = self::normalizar($b);
        if (bccomp($divisor, '0', 12) === 0) {
            throw new \InvalidArgumentException('Divisão por zero.');
        }

        return self::arredondado(bcdiv(self::normalizar($a), $divisor, $casas + self::GUARDA), $casas);
    }

    /** Percentual: valor × alíquota / 100, com um único arredondamento bancário final. */
    public static function percentualDe(string|int|float $valor, string|int|float $aliquota, int $casas = 2): string
    {
        $produto = bcmul(self::normalizar($valor), self::normalizar($aliquota), 12);

        return self::arredondado(bcdiv($produto, '100', 12), $casas);
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

    private static function arredondado(string $valor, int $casas): string
    {
        return ArredondadorBancario::arredondar($valor, $casas);
    }
}
