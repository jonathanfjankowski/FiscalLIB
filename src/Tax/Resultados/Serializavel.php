<?php

declare(strict_types=1);

namespace FiscalLib\Tax\Resultados;

/**
 * Serializa o resultado no formato dos grupos de imposto do contrato
 * (campos camelCase, nulos e vazios omitidos). É o contrato que a
 * FiscalAPI valida por aritmética.
 */
trait Serializavel
{
    /** @return array<string, mixed> */
    public function paraArray(): array
    {
        return self::serializar(get_object_vars($this));
    }

    /** @param array<string, mixed> $valores @return array<string, mixed> */
    private static function serializar(array $valores): array
    {
        $saida = [];
        foreach ($valores as $chave => $valor) {
            if ($valor === null) {
                continue;
            }
            // Traits não são tipos: instanceof não funcionaria aqui.
            if (is_object($valor) && method_exists($valor, 'paraArray')) {
                $valor = $valor->paraArray();
                if ($valor === []) {
                    continue;
                }
            } elseif (is_array($valor)) {
                $valor = self::serializar($valor);
                if ($valor === []) {
                    continue;
                }
            }
            $saida[$chave] = $valor;
        }

        return $saida;
    }
}
