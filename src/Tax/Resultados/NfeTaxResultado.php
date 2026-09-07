<?php

declare(strict_types=1);

namespace FiscalLib\Tax\Resultados;

/**
 * Resultado completo de tributos de um item de NF-e/NFC-e —
 * espelha a estrutura `impostosV2` do contrato.
 */
final class NfeTaxResultado
{
    use Serializavel;

    public function __construct(
        public readonly ?IcmsResultado $icms = null,
        public readonly ?ImpostoTrioResultado $ipi = null,
        public readonly ?ImpostoTrioResultado $pis = null,
        public readonly ?ImpostoTrioResultado $cofins = null,
        public readonly ?IbsCbsResultado $ibsCbs = null,
        public readonly ?IsResultado $is = null,
    ) {
    }

    /** vICMSST do item (para fórmula do total da nota). */
    public function totalSt(): string
    {
        return $this->icms?->st->valorSt ?? '0.00';
    }

    /** vFCP-ST do item (composto no total; FCP próprio não compõe). */
    public function totalFcpSt(): string
    {
        return $this->icms?->st->valorFcpSt ?? '0.00';
    }

    public function totalIpi(): string
    {
        return $this->ipi->valor ?? '0.00';
    }

    public function totalIs(): string
    {
        return $this->is->valor ?? '0.00';
    }

    public function totalIcms(): string
    {
        return $this->icms->valor ?? '0.00';
    }

    public function totalFcp(): string
    {
        return $this->icms->valorFcp ?? '0.00';
    }
}
