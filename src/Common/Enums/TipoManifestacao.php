<?php

declare(strict_types=1);

namespace FiscalLib\Common\Enums;

/**
 * Tipo de manifestação do destinatário (Distribuição DFe).
 */
enum TipoManifestacao: int
{
    case CienciaOperacao = 210200;
    case ConfirmacaoOperacao = 210210;
    case OperacaoDesconhecida = 210220;
    case OperacaoNaoRealizada = 210240;
}
