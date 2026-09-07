<?php

declare(strict_types=1);

namespace FiscalLib\Documento;

use FiscalLib\Common\ValueObjects\Cnpj;
use FiscalLib\Common\ValueObjects\Cpf;

/**
 * Tomador do serviço (contrato: NfseTomaDto).
 */
final class Tomador
{
    public function __construct(
        public readonly Cnpj|Cpf $documento,
        public readonly ?string $nome = null,
        public readonly ?string $inscricaoMunicipal = null,
        public readonly ?string $telefone = null,
        public readonly ?string $email = null,
        public readonly ?Endereco $endereco = null,
    ) {
    }

    public function cnpjCpf(): string
    {
        return $this->documento->valor();
    }
}
