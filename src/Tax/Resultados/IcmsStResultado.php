<?php

declare(strict_types=1);

namespace FiscalLib\Tax\Resultados;

/**
 * Grupo ST do ICMS (impostosV2.icms.st).
 * Própria (10/70/90, CSOSN 201/202/203/900) ou retida (60/500).
 */
final class IcmsStResultado
{
    use Serializavel;

    public function __construct(
        public readonly ?string $modBcSt = null,
        public readonly ?string $percentualMva = null,
        public readonly ?string $percentualReducaoBcSt = null,
        public readonly ?string $baseCalculoSt = null,
        public readonly ?string $aliquotaSt = null,
        public readonly ?string $valorSt = null,
        public readonly ?string $fcpPercentualSt = null,
        public readonly ?string $valorFcpSt = null,
        public readonly ?string $baseCalculoStRetido = null,
        public readonly ?string $aliquotaStRetida = null,
        public readonly ?string $valorStRetido = null,
        public readonly ?string $valorIcmsSubstituto = null,
        public readonly ?string $fcpPercentualStRetido = null,
        public readonly ?string $valorFcpStRetido = null,
    ) {
    }
}
