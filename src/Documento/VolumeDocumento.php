<?php

declare(strict_types=1);

namespace FiscalLib\Documento;

/**
 * Volume da carga (grupo vol, v2 §7). Lacres é lista de números (grupo lacre).
 */
final class VolumeDocumento
{
    /**
     * @param list<string> $lacres
     */
    public function __construct(
        public readonly ?int $quantidade = null,
        public readonly ?string $especie = null,
        public readonly ?string $marca = null,
        public readonly ?string $numeracao = null,
        public readonly ?string $pesoLiquido = null,
        public readonly ?string $pesoBruto = null,
        public readonly array $lacres = [],
    ) {
    }
}
