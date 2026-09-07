<?php

declare(strict_types=1);

namespace FiscalLib\Exceptions;

/**
 * Inconsistência entre regime tributário, CST/CSOSN, CFOP e valores —
 * ou uso de combinação não suportada pelo contrato (ex.: CST 30, ICMSPart).
 */
final class TaxInconsistencyException extends ValidationException
{
    public static function combinacaoNaoSuportada(string $detalhe): self
    {
        return new self("Combinação tributária não suportada: {$detalhe}");
    }

    public static function aritmetica(string $campo, string $esperado, string $recebido): self
    {
        return new self(
            "Inconsistência aritmética em '{$campo}': esperado {$esperado}, recebido {$recebido}.",
            [$campo => ["Esperado {$esperado}, recebido {$recebido}."]]
        );
    }
}
