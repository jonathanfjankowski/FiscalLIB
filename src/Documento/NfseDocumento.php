<?php

declare(strict_types=1);

namespace FiscalLib\Documento;

use FiscalLib\Common\Enums\Ambiente;
use FiscalLib\Tax\Resultados\NfseTaxResultado;

/**
 * MODELO intermediário de NFS-e Nacional (DPS, layout 1.01).
 * Prestador (emitente) é opcional: a FiscalAPI usa o perfil do tenant.
 */
final class NfseDocumento
{
    public function __construct(
        public readonly Ambiente $ambiente,
        public readonly int $serie,
        public readonly Tomador $tomador,
        public readonly ServicoFiscal $servico,
        public readonly NfseTaxResultado $tributos,
        public readonly ?IbsCbsDps $ibsCbs = null,
        public readonly ?string $dataCompetencia = null, // yyyy-MM-dd — default: hoje (UTC) na API
        public readonly ?int $tipoEmissor = null,        // 1 prestador (default), 2 tomador, 3 intermediário
        public readonly ?int $codigoMunicipioEmissor = null,
        public readonly ?Emitente $prestador = null,
        public readonly ?string $informacoesComplementares = null,
    ) {
    }
}
