<?php

declare(strict_types=1);

namespace FiscalLib\Common\Enums;

/**
 * Regime tributário do emitente (CRT).
 */
enum RegimeTributario: int
{
    case SimplesNacional = 1;
    case SimplesNacionalExcessoReceita = 2;
    case RegimeNormal = 3;

    public function isSimplesNacional(): bool
    {
        return $this === self::SimplesNacional || $this === self::SimplesNacionalExcessoReceita;
    }

    /** Obrigatório a partir de 04/01/2027 para SN; 03/08/2026 para regime normal. */
    public function prazoObrigatorioIbsCbs(): string
    {
        return $this->isSimplesNacional() ? '2027-01-04' : '2026-08-03';
    }
}
