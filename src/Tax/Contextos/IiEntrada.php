<?php

declare(strict_types=1);

namespace FiscalLib\Tax\Contextos;

use FiscalLib\Common\Matematica;

/**
 * Entrada de Imposto de Importação (grupo `II` da NF-e — espelha IiDto da FiscalAPI).
 */
final class IiEntrada
{
    public readonly string $baseCalculo;
    public readonly string $valorDespesasAduaneiras;
    public readonly string $valorIi;
    public readonly ?string $valorIof;

    public function __construct(
        string|int|float $baseCalculo,
        string|int|float $valorDespesasAduaneiras,
        string|int|float $valorIi,
        string|int|float|null $valorIof = null,
    ) {
        $this->baseCalculo = Matematica::escalar($baseCalculo, 2);
        $this->valorDespesasAduaneiras = Matematica::escalar($valorDespesasAduaneiras, 2);
        $this->valorIi = Matematica::escalar($valorIi, 2);
        $this->valorIof = $valorIof === null ? null : Matematica::escalar($valorIof, 2);
    }
}
