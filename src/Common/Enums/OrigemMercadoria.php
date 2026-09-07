<?php

declare(strict_types=1);

namespace FiscalLib\Common\Enums;

/**
 * Origem da mercadoria (tabela A do ICMS).
 */
enum OrigemMercadoria: int
{
    case Nacional = 0;
    case EstrangeiraImportacaoDireta = 1;
    case EstrangeiraAdquiridaInterno = 2;
    case NacionalConteudoImportacao40 = 3;
    case NacionalProcessoProdutivoBasico = 4;
    case NacionalConteudoImportacaoInferior40 = 5;
    case EstrangeiraImportacaoDiretaSemSimilar = 6;
    case EstrangeiraInternoSemSimilar = 7;
    case NacionalConteudoImportacaoSuperior70 = 8;
}
