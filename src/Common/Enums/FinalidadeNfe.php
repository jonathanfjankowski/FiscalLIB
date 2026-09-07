<?php

declare(strict_types=1);

namespace FiscalLib\Common\Enums;

/**
 * Finalidade da emissão (finNFe).
 */
enum FinalidadeNfe: string
{
    case Normal = 'normal';
    case Complementar = 'complementar';
    case Ajuste = 'ajuste';
    case Devolucao = 'devolucao';
}
