<?php

declare(strict_types=1);

namespace FiscalLib\Exceptions;

/**
 * Erro HTTP do emissor (ou de qualquer implementação de EmissorInterface que
 * fale HTTP). Carrega o status e, quando disponível, o ProblemDetails (RFC 7807).
 */
class ApiHttpException extends FiscalLibException
{
    public function __construct(
        string $message,
        public readonly ?int $status = null,
        public readonly ?array $problem = null,
        public readonly ?string $campo = null,
        /** @var array<string, list<string>> */
        public readonly array $errosPorCampo = [],
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
