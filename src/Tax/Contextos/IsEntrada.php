<?php

declare(strict_types=1);

namespace FiscalLib\Tax\Contextos;

/**
 * Entrada do Imposto Seletivo. Por quantidade: informe baseCalculoOverride
 * (a base que será validada × alíquota) + unidadeTributavel e
 * quantidadeTributavel juntos.
 */
final class IsEntrada
{
    private function __construct(
        public readonly string $cstIs,
        public readonly string $cClassTribIs,
        public readonly ?string $aliquota,
        public readonly ?string $baseCalculoOverride,
        public readonly ?string $unidadeTributavel,
        public readonly ?string $quantidadeTributavel,
    ) {
    }

    public static function porValor(string $cstIs, string $cClassTribIs, string|int|float $aliquota): self
    {
        return new self($cstIs, $cClassTribIs, \FiscalLib\Common\Matematica::normalizar($aliquota), null, null, null);
    }

    public static function porQuantidade(
        string $cstIs,
        string $cClassTribIs,
        string|int|float $aliquota,
        string|int|float $baseCalculo,
        string $unidadeTributavel,
        string|int|float $quantidadeTributavel,
    ): self {
        return new self(
            $cstIs,
            $cClassTribIs,
            \FiscalLib\Common\Matematica::normalizar($aliquota),
            \FiscalLib\Common\Matematica::normalizar($baseCalculo),
            $unidadeTributavel,
            \FiscalLib\Common\Matematica::normalizar($quantidadeTributavel),
        );
    }
}
