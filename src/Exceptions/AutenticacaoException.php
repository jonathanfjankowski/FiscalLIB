<?php

declare(strict_types=1);

namespace FiscalLib\Exceptions;

/**
 * 401/403 — API key ausente/inválida/revogada ou ambiente divergente da chave.
 */
final class AutenticacaoException extends ApiHttpException
{
}
