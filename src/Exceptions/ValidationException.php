<?php

declare(strict_types=1);

namespace FiscalLib\Exceptions;

/**
 * Regra de negócio violada antes de qualquer envio ao emissor.
 * Carrega a lista de erros por campo: ['campo' => ['mensagem', ...]].
 *
 * @phpstan-consistent-constructor
 */
class ValidationException extends FiscalLibException
{
    /** @param array<string, list<string>> $erros */
    public function __construct(
        string $message,
        private readonly array $erros = [],
    ) {
        parent::__construct($message);
    }

    /** @return array<string, list<string>> */
    public function erros(): array
    {
        return $this->erros;
    }

    public function comErro(string $campo, string $mensagem): static
    {
        $erros = $this->erros;
        $erros[$campo][] = $mensagem;

        return new static($mensagem, $erros);
    }

    public static function erro(string $campo, string $mensagem): static
    {
        return new static($mensagem, [$campo => [$mensagem]]);
    }
}
