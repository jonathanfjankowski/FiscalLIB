<?php

declare(strict_types=1);

namespace FiscalLib\Documento;

use FiscalLib\Common\Matematica;

/**
 * Totais do documento — fórmula determinística do contrato (v2 §5.2):
 *
 *   Ativa a fórmula v2 quando qualquer campo novo está presente
 *   (desconto, frete, seguro, outras despesas ou IPI em item):
 *     valorNota = Σ brutos − descontos + frete + seguro + outras
 *                 + ST + FCP-ST + IPI
 *
 *   Caso contrário (regra antiga): valorNota = Σ brutos.
 *
 * IBS/CBS e IS NÃO entram no total (conferência à parte no contrato).
 */
final class TotaisDocumento
{
    public function __construct(
        public readonly string $valorProdutos,      // Σ bruto dos itens
        public readonly string $valorNota,
        public readonly ?string $valorDesconto = null, // desconto no total (document-level)
        public readonly ?string $valorFrete = null,
        public readonly ?string $valorSeguro = null,
        public readonly ?string $outrasDespesas = null,
        public readonly ?string $valorIbs = null,   // conferência (não compõe o total)
        public readonly ?string $valorCbs = null,
        public readonly ?string $valorIs = null,
    ) {
    }

    /**
     * Calcula os totais a partir dos itens e valores document-level.
     *
     * @param list<ItemFiscal> $itens
     */
    public static function calcular(
        array $itens,
        ?string $valorDescontoDocumento = null,
        ?string $valorFrete = null,
        ?string $valorSeguro = null,
        ?string $outrasDespesas = null,
    ): self {
        $brutos = '0.00';
        $descontosItens = '0.00';
        $st = '0.00';
        $fcpSt = '0.00';
        $ipi = '0.00';
        $ibs = '0.00';
        $cbs = '0.00';
        $is = '0.00';
        $formulaV2 = $valorDescontoDocumento !== null || $valorFrete !== null
            || $valorSeguro !== null || $outrasDespesas !== null;

        foreach ($itens as $item) {
            $brutos = Matematica::somar($brutos, $item->valorTotal);
            if (bccomp($item->valorDesconto, '0', 2) !== 0) {
                $descontosItens = Matematica::somar($descontosItens, $item->valorDesconto);
                $formulaV2 = true;
            }
            $t = $item->tributos;
            if ($t === null) {
                continue;
            }
            $st = Matematica::somar($st, $t->totalSt());
            $fcpSt = Matematica::somar($fcpSt, $t->totalFcpSt());
            if ($t->ipi !== null) {
                $formulaV2 = true;
                $ipi = Matematica::somar($ipi, $t->totalIpi());
            }
            if ($t->ibsCbs !== null) {
                $ibs = Matematica::somar($ibs, $t->ibsCbs->totalIbs());
                $cbs = Matematica::somar($cbs, $t->ibsCbs->valorCbs ?? '0.00');
            }
            if ($t->is !== null) {
                $is = Matematica::somar($is, $t->totalIs());
            }
        }

        if ($formulaV2) {
            $total = $brutos;
            $total = Matematica::subtrair($total, $descontosItens);
            $total = Matematica::subtrair($total, $valorDescontoDocumento ?? '0.00');
            $total = Matematica::somar($total, $valorFrete ?? '0.00');
            $total = Matematica::somar($total, $valorSeguro ?? '0.00');
            $total = Matematica::somar($total, $outrasDespesas ?? '0.00');
            $total = Matematica::somar($total, $st);
            $total = Matematica::somar($total, $fcpSt);
            $total = Matematica::somar($total, $ipi);
        } else {
            $total = $brutos;
        }

        return new self(
            valorProdutos: $brutos,
            valorNota: Matematica::escalar($total, 2),
            valorDesconto: $valorDescontoDocumento,
            valorFrete: $valorFrete,
            valorSeguro: $valorSeguro,
            outrasDespesas: $outrasDespesas,
            valorIbs: bccomp($ibs, '0', 2) === 0 ? null : $ibs,
            valorCbs: bccomp($cbs, '0', 2) === 0 ? null : $cbs,
            valorIs: bccomp($is, '0', 2) === 0 ? null : $is,
        );
    }
}
