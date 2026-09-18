<?php

declare(strict_types=1);

namespace FiscalLib\Tax\Tabelas;

/**
 * Parâmetros de DIFAL resolvidos — casam 1:1 com NfeTaxContext::difalInterestadual().
 */
final class ParametrosDifal
{
    public function __construct(
        public readonly int $aliquotaInterestadual,
        public readonly string $aliquotaInternaUfDestino,
        public readonly ?string $aliquotaFcpUfDestino = null,
    ) {
    }
}
