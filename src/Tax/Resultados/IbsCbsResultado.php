<?php

declare(strict_types=1);

namespace FiscalLib\Tax\Resultados;

/**
 * Grupo IBS/CBS (impostosV2.ibsCbs) — LC 214/2025 / NT 2025.x.
 * IBS "por fora": soma no total da nota conforme o contrato v2.
 */
final class IbsCbsResultado
{
    use Serializavel;

    public function __construct(
        public readonly string $cstIbsCbs,           // 3 dígitos (SEPEC)
        public readonly string $cClassTrib,          // 6 dígitos — obrigatório sempre
        public readonly ?string $baseCalculo = null,
        public readonly ?string $aliquotaCbs = null,
        public readonly ?string $percentualReducaoCbs = null,
        public readonly ?string $valorCbs = null,
        public readonly ?string $aliquotaIbsEstadual = null,
        public readonly ?string $percentualReducaoIbsEstadual = null,
        public readonly ?string $valorIbsEstadual = null,
        public readonly ?string $aliquotaIbsMunicipal = null,
        public readonly ?string $percentualReducaoIbsMunicipal = null,
        public readonly ?string $valorIbsMunicipal = null,
        public readonly ?string $percentualDiferimentoCbs = null,
        public readonly ?string $valorDiferidoCbs = null,
        public readonly ?string $percentualDiferimentoIbs = null,
        public readonly ?string $valorDiferidoIbs = null,
        public readonly ?string $cstCreditoPresumido = null,
        public readonly ?string $valorCreditoPresumido = null,
    ) {
    }

    public function totalIbs(): string
    {
        return \FiscalLib\Common\Matematica::somar(
            $this->valorIbsEstadual ?? '0.00',
            $this->valorIbsMunicipal ?? '0.00'
        );
    }
}
