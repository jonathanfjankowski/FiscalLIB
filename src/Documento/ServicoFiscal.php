<?php

declare(strict_types=1);

namespace FiscalLib\Documento;

/**
 * Serviço da NFS-e (contrato: NfseServicoDto).
 */
final class ServicoFiscal
{
    public function __construct(
        public readonly string $codigoTributarioNacional, // cTribNac (LC 116) — máx 6
        public readonly string $descricaoServico,         // xDescServ — máx 2000
        public readonly ?string $codigoNbs = null,        // cNBS 9 dígitos
        public readonly ?string $codigoTributarioMunicipal = null,
        public readonly ?int $codigoMunicipioPrestacao = null, // cLocPrestacao — default: município do tenant
    ) {
    }
}
