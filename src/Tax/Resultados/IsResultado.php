<?php

declare(strict_types=1);

namespace FiscalLib\Tax\Resultados;

/**
 * Imposto Seletivo (impostosV2.is). Por quantidade exige
 * unidadeTributavel + quantidadeTributavel juntos.
 */
final class IsResultado
{
    use Serializavel;

    public function __construct(
        public readonly string $cstIs,          // 2 dígitos (SEPEC)
        public readonly string $cClassTribIs,   // 6 dígitos — obrigatório
        public readonly ?string $baseCalculo = null,
        public readonly ?string $aliquota = null,
        public readonly ?string $valor = null,
        public readonly ?string $tipoBaseCalculo = null, // valor|quantidade|area|volume
        public readonly ?string $unidadeTributavel = null,
        public readonly ?string $quantidadeTributavel = null,
    ) {
    }
}
