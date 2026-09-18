<?php

declare(strict_types=1);

namespace FiscalLib\Documento;

use FiscalLib\Common\Enums\FormaPagamento;

/**
 * Parcela de pagamento (contrato: PagamentoDto).
 */
final class Pagamento
{
    public function __construct(
        public readonly FormaPagamento $forma,
        public readonly string $valor,
    ) {
    }

    public function formaCodigo(): string
    {
        return $this->forma->value;
    }
}
