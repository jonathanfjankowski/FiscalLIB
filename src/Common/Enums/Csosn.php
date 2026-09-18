<?php

declare(strict_types=1);

namespace FiscalLib\Common\Enums;

/**
 * CSOSN do ICMS (Simples Nacional) — tabela completa suportada pelo contrato.
 */
enum Csosn: string
{
    case TributadaComPermissaoDeCredito = '101';
    case TributadaSemPermissaoDeCredito = '102';
    case IsencaoIcmsParaFaixaDeReceitaBruta = '103';
    case TributadaComPermissaoDeCreditoECobrancaPorSt = '201';
    case TributadaSemPermissaoDeCreditoECobrancaPorSt = '202';
    case IsencaoFaixaReceitaECobrancaPorSt = '203';
    case Imune = '300';
    case SemIncidencia = '400';
    case IcmsCobradoAnteriormentePorSt = '500';
    case Outros = '900';
}
