<?php

declare(strict_types=1);

namespace FiscalLib\Exceptions;

/**
 * Erro interno do motor tributário (entrada incoerente que não deveria
 * chegar até o cálculo — ex.: alíquota obrigatória ausente para CST tributado).
 */
final class TaxCalculationException extends FiscalLibException
{
}
