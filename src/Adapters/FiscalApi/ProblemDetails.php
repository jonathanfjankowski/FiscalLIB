<?php

declare(strict_types=1);

namespace FiscalLib\Adapters\FiscalApi;

/**
 * ProblemDetails RFC 7807 (application/problem+json) da FiscalAPI —
 * incluindo ValidationProblemDetails (dicionário `errors`) e a extensão `campo`.
 */
final class ProblemDetails
{
    /**
     * @param array<string, list<string>> $errosPorCampo
     */
    private function __construct(
        public readonly ?string $tipo,
        public readonly ?string $titulo,
        public readonly ?int $status,
        public readonly ?string $detalhe,
        public readonly ?string $instancia,
        public readonly ?string $campo,
        public readonly array $errosPorCampo,
        public readonly array $raw,
    ) {
    }

    /** @param array<string,mixed> $corpo */
    public static function doArray(array $corpo): self
    {
        $erros = [];
        if (isset($corpo['errors']) && is_array($corpo['errors'])) {
            foreach ($corpo['errors'] as $campo => $mensagens) {
                $erros[(string) $campo] = is_array($mensagens) ? array_map('strval', $mensagens) : [(string) $mensagens];
            }
        }

        return new self(
            tipo: isset($corpo['type']) ? (string) $corpo['type'] : null,
            titulo: isset($corpo['title']) ? (string) $corpo['title'] : null,
            status: isset($corpo['status']) ? (int) $corpo['status'] : null,
            detalhe: isset($corpo['detail']) ? (string) $corpo['detail'] : null,
            instancia: isset($corpo['instance']) ? (string) $corpo['instance'] : null,
            campo: isset($corpo['campo']) ? (string) $corpo['campo'] : null,
            errosPorCampo: $erros,
            raw: $corpo,
        );
    }

    public function resumo(): string
    {
        $partes = [];
        if ($this->titulo !== null) {
            $partes[] = $this->titulo;
        }
        if ($this->detalhe !== null) {
            $partes[] = $this->detalhe;
        }
        if ($partes === [] && $this->errosPorCampo !== []) {
            foreach ($this->errosPorCampo as $campo => $mensagens) {
                foreach ($mensagens as $mensagem) {
                    $partes[] = "{$campo}: {$mensagem}";
                }
            }
        }

        return $partes === [] ? 'Erro não especificado do emissor.' : implode(' | ', $partes);
    }
}
