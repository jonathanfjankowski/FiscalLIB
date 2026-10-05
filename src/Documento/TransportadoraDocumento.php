<?php

declare(strict_types=1);

namespace FiscalLib\Documento;

/**
 * Transportadora do grupo transp (v2 §7) — todos os campos opcionais,
 * coerentes com o TransportadoraDto da FiscalAPI.
 */
final class TransportadoraDocumento
{
    public function __construct(
        public readonly ?string $cnpjCpf = null,
        public readonly ?string $nome = null,
        public readonly ?string $inscricaoEstadual = null,
        public readonly ?string $logradouro = null,
        public readonly ?string $municipio = null,
        public readonly ?string $uf = null,
    ) {
    }
}
