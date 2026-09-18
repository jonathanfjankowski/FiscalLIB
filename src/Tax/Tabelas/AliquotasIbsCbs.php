<?php

declare(strict_types=1);

namespace FiscalLib\Tax\Tabelas;

/**
 * Alíquotas IBS/CBS de referência — casam com IbsCbsEntrada::criar().
 */
final class AliquotasIbsCbs
{
    public function __construct(
        public readonly string $aliquotaCbs,
        public readonly string $aliquotaIbsEstadual,
        public readonly string $aliquotaIbsMunicipal,
    ) {
    }
}
