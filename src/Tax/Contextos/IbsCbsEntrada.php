<?php

declare(strict_types=1);

namespace FiscalLib\Tax\Contextos;

/**
 * Entrada do bloco IBS/CBS (LC 214/2025). As alíquotas são do ERP;
 * os valores são calculados pelo TaxEngine ("por fora" — somam no total).
 */
final class IbsCbsEntrada
{
    public ?string $percentualReducaoCbs = null;
    public ?string $percentualReducaoIbsEstadual = null;
    public ?string $percentualReducaoIbsMunicipal = null;

    private function __construct(
        public readonly string $cstIbsCbs,
        public readonly string $cClassTrib,
        public readonly ?string $aliquotaIbsEstadual,
        public readonly ?string $aliquotaIbsMunicipal,
        public readonly ?string $aliquotaCbs,
    ) {
    }

    public static function criar(
        string $cstIbsCbs,
        string $cClassTrib,
        string|int|float|null $aliquotaIbsEstadual = null,
        string|int|float|null $aliquotaIbsMunicipal = null,
        string|int|float|null $aliquotaCbs = null,
    ): self {
        return new self(
            $cstIbsCbs,
            $cClassTrib,
            $aliquotaIbsEstadual === null ? null : \FiscalLib\Common\Matematica::normalizar($aliquotaIbsEstadual),
            $aliquotaIbsMunicipal === null ? null : \FiscalLib\Common\Matematica::normalizar($aliquotaIbsMunicipal),
            $aliquotaCbs === null ? null : \FiscalLib\Common\Matematica::normalizar($aliquotaCbs),
        );
    }

    public function comReducaoCbs(string|int|float $percentual): self
    {
        $this->percentualReducaoCbs = \FiscalLib\Common\Matematica::normalizar($percentual);

        return $this;
    }

    public function comReducaoIbsEstadual(string|int|float $percentual): self
    {
        $this->percentualReducaoIbsEstadual = \FiscalLib\Common\Matematica::normalizar($percentual);

        return $this;
    }

    public function comReducaoIbsMunicipal(string|int|float $percentual): self
    {
        $this->percentualReducaoIbsMunicipal = \FiscalLib\Common\Matematica::normalizar($percentual);

        return $this;
    }
}
