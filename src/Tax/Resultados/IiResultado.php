<?php

declare(strict_types=1);

namespace FiscalLib\Tax\Resultados;

/**
 * Resultado do grupo II (Imposto de Importação) — serializa direto para `impostosV2.ii`.
 */
final class IiResultado
{
    use Serializavel;

    public function __construct(
        public readonly string $baseCalculo,
        public readonly string $valorDespesasAduaneiras,
        public readonly string $valorIi,
        public readonly ?string $valorIof = null,
    ) {
    }
}
