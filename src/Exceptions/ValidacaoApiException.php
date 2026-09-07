<?php

declare(strict_types=1);

namespace FiscalLib\Exceptions;

/**
 * 400/422 — payload inválido ou inconsistência semântica/aritmética
 * (o dicionário de erros por campo vem do ValidationProblemDetails).
 */
final class ValidacaoApiException extends ApiHttpException
{
}
