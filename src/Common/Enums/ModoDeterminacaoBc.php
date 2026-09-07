<?php

declare(strict_types=1);

namespace FiscalLib\Common\Enums;

/**
 * Modalidade de determinação da BC do ICMS (modBC) e da ST (modBCST).
 * Valores string conforme o contrato da FiscalAPI.
 */
enum ModoDeterminacaoBc: string
{
    case MargemValorAgregado = '0';
    case Pauta = '1';
    case PrecoTabelado = '2';
    case ValorOperacao = '3';
    case PrecoTabeladoMaximo = '4';
    case PautaImportacao = '5';
    case ValorOperacaoLiquido = '6';
}
