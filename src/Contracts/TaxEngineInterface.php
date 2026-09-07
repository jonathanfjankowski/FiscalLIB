<?php

declare(strict_types=1);

namespace FiscalLib\Contracts;

use FiscalLib\Tax\Contextos\NfeTaxContext;
use FiscalLib\Tax\Contextos\NfseTaxContext;
use FiscalLib\Tax\Resultados\NfeTaxResultado;
use FiscalLib\Tax\Resultados\NfseTaxResultado;

/**
 * Motor tributário — puro, stateless, sem I/O. Recebe o contexto da
 * operação e devolve valores prontos no formato do contrato (impostosV2 / DPS).
 * Qualquer implementação alternativa deve honrar a aritmética validada
 * pela FiscalAPI (base × alíquota / 100 = valor, tolerância R$ 0,01).
 */
interface TaxEngineInterface
{
    public function calcularNfe(NfeTaxContext $contexto): NfeTaxResultado;

    public function calcularNfse(NfseTaxContext $contexto): NfseTaxResultado;
}
