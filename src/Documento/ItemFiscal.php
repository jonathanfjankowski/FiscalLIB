<?php

declare(strict_types=1);

namespace FiscalLib\Documento;

use FiscalLib\Tax\Resultados\NfeTaxResultado;

/**
 * Item de NF-e/NFC-e já com tributos calculados.
 * valorTotal é o BRUTO (qtd × unitário, sem desconto) — semântica do contrato.
 */
final class ItemFiscal
{
    public function __construct(
        public readonly string $codigo,
        public readonly string $descricao,
        public readonly string $quantidade,        // 4 decimais
        public readonly string $valorUnitario,     // até 10 decimais
        public readonly string $valorTotal,        // bruto, 2 decimais
        public readonly ?NfeTaxResultado $tributos = null,
        public readonly ?string $ncm = null,
        public readonly ?string $cest = null,
        public readonly ?string $cfop = null,
        public readonly ?string $gtin = null,      // default "SEM GTIN"
        public readonly ?string $unidade = null,   // default "UN"
        public readonly string $valorDesconto = '0.00',
    ) {
    }
}
