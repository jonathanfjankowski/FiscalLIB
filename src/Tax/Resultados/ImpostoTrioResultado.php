<?php

declare(strict_types=1);

namespace FiscalLib\Tax\Resultados;

/**
 * Trio cst + baseCalculo + aliquota + valor — serve IPI, PIS e COFINS
 * (impostosV2.ipi/.pis/.cofins). IPI inclui cEnq (default "999").
 */
final class ImpostoTrioResultado
{
    use Serializavel;

    public function __construct(
        public readonly string $cst,
        public readonly ?string $baseCalculo = null,
        public readonly ?string $aliquota = null,
        public readonly ?string $valor = null,
        public readonly ?string $cEnq = null,
    ) {
    }
}
