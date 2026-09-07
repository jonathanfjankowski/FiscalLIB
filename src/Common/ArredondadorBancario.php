<?php

declare(strict_types=1);

namespace FiscalLib\Common;

/**
 * Arredondamento bancário (round half to even) — V004 da spec.
 * Exatamente .5 arredonda para o dígito par mais próximo.
 */
final class ArredondadorBancario
{
    public static function arredondar(string $valor, int $casas = 2): string
    {
        if ($casas < 0) {
            throw new \InvalidArgumentException('Número de casas não pode ser negativo.');
        }

        $negativo = str_starts_with($valor, '-');
        $absoluto = Matematica::abs($valor);

        [$inteiro, $fracao] = array_pad(explode('.', $absoluto), 2, '');

        $fracao = str_pad($fracao, $casas + 2, '0');
        $mantido = $casas === 0 ? '' : substr($fracao, 0, $casas);
        $dDigito = (int) $fracao[$casas];
        $rabo = substr($fracao, $casas + 1);

        $resultado = $inteiro . ($mantido !== '' ? '.' . $mantido : '');
        $unidadeMenor = $casas === 0 ? '1' : ('0.' . str_pad('', $casas - 1, '0') . '1');

        $raboSignificativo = ltrim($rabo, '0') !== '';

        if ($dDigito > 5 || ($dDigito === 5 && $raboSignificativo)) {
            // Arredonda para longe do zero.
            $resultado = bcadd($resultado, $unidadeMenor, $casas);
        } elseif ($dDigito === 5) {
            // Exatamente .5 → para o dígito par mais próximo.
            if (! self::ultimoDigitoPar($resultado, $casas)) {
                $resultado = bcadd($resultado, $unidadeMenor, $casas);
            }
        }
        // dDigito < 5 → trunca (já feito).

        return $negativo && bccomp($resultado, '0', $casas) !== 0
            ? '-' . $resultado
            : $resultado;
    }

    private static function ultimoDigitoPar(string $numero, int $casas): bool
    {
        if ($casas === 0) {
            return ((int) substr($numero, -1)) % 2 === 0;
        }

        $partes = explode('.', $numero);
        $fracao = str_pad($partes[1] ?? '', $casas, '0');

        return ((int) $fracao[$casas - 1]) % 2 === 0;
    }
}
