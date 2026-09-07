<?php

declare(strict_types=1);

namespace FiscalLib\Documento;

/**
 * Endereço compartilhado (contrato FiscalAPI: EnderecoDto / NfseEnderecoDto).
 */
final class Endereco
{
    public function __construct(
        public readonly ?string $cep = null,
        public readonly ?string $logradouro = null,
        public readonly ?string $numero = null,
        public readonly ?string $complemento = null,
        public readonly ?string $bairro = null,
        public readonly ?string $codigoMunicipioIbge = null,
        public readonly ?string $uf = null,
        public readonly ?string $nomeMunicipio = null,
    ) {
    }
}
