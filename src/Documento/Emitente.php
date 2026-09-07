<?php

declare(strict_types=1);

namespace FiscalLib\Documento;

use FiscalLib\Common\ValueObjects\Cnpj;
use FiscalLib\Common\ValueObjects\Cpf;

/**
 * Dados do emitente/prestador. Na FiscalAPI o emitente vem do perfil do
 * tenant — estes dados servem para validação local e para outros emissores.
 */
final class Emitente
{
    public function __construct(
        public readonly Cnpj|Cpf $documento,
        public readonly string $razaoSocial,
        public readonly ?string $nomeFantasia = null,
        public readonly ?string $inscricaoEstadual = null,
        public readonly ?string $inscricaoMunicipal = null,
        public readonly ?Endereco $endereco = null,
    ) {
    }

    public function cnpjCpf(): string
    {
        return $this->documento->valor();
    }
}
