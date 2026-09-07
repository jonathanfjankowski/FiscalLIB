<?php

declare(strict_types=1);

namespace FiscalLib\Documento;

/**
 * Resposta imediata do POST de emissão (202 Accepted) — o documento
 * foi aceito e está PENDENTE. Persista documentoId imediatamente.
 */
final class AceiteEmissao
{
    public function __construct(
        public readonly string $documentoId,
        public readonly string $status,
        public readonly ?string $ambiente = null,
        public readonly ?string $criadoEm = null,
        public readonly ?string $linkConsulta = null,
        public readonly ?array $raw = null,
    ) {
    }
}
