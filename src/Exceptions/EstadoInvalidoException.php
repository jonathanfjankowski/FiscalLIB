<?php

declare(strict_types=1);

namespace FiscalLib\Exceptions;

/**
 * 409 — estado incompatível (ex.: cancelar documento que não está AUTORIZADA).
 */
final class EstadoInvalidoException extends ApiHttpException
{
}
