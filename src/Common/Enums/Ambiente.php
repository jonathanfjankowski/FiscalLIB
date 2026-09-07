<?php

declare(strict_types=1);

namespace FiscalLib\Common\Enums;

/**
 * Ambiente da SEFAZ. Valores string conforme o contrato da FiscalAPI.
 * A API key (fk_test_ / fk_live_) amarra o ambiente — divergência → 403.
 */
enum Ambiente: string
{
    case Producao = 'producao';
    case Homologacao = 'homologacao';

    public function chaveEsperada(): string
    {
        return $this === self::Producao ? 'fk_live_' : 'fk_test_';
    }
}
