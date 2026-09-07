<?php

declare(strict_types=1);

namespace FiscalLib\Documento;

use FiscalLib\Common\ValueObjects\Cnpj;
use FiscalLib\Common\ValueObjects\Cpf;

/**
 * Destinatário da NF-e/NFC-e (contrato: DestinatarioDto).
 */
final class Destinatario
{
    public function __construct(
        public readonly Cnpj|Cpf $documento,
        public readonly string $nome,
        public readonly ?string $inscricaoEstadual = null,
        public readonly ?Endereco $endereco = null,
    ) {
    }

    public function cnpjCpf(): string
    {
        return $this->documento->valor();
    }
}
