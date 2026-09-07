<?php

declare(strict_types=1);

namespace FiscalLib\Common\Enums;

/**
 * Indicador de presença do comprador (indPres).
 */
enum IndicadorPresenca: string
{
    case NaoSeAplica = 'nao_se_aplica';
    case Presencial = 'presencial';
    case Internet = 'internet';
    case Teleatendimento = 'teleatendimento';
    case EntregaDomicilio = 'entrega_domicilio';
    case ForaEstabelecimento = 'fora_estabelecimento';
    case Outros = 'outros';

    /** Código indPres do layout 4.00. */
    public function codigo(): int
    {
        return match ($this) {
            self::NaoSeAplica => 0,
            self::Presencial => 1,
            self::Internet => 2,
            self::Teleatendimento => 3,
            self::EntregaDomicilio => 4,
            self::ForaEstabelecimento => 5,
            self::Outros => 9,
        };
    }
}
