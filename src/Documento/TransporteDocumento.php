<?php

declare(strict_types=1);

namespace FiscalLib\Documento;

/**
 * Grupo transp da NF-e (v2 §7): modalidade do frete (modFrete "0"–"3", "9"),
 * transportadora e volumes. Opcional — ausente, a API assume "9" (sem
 * ocorrência de transporte).
 */
final class TransporteDocumento
{
    /**
     * @param list<VolumeDocumento> $volumes
     */
    public function __construct(
        public readonly string $modalidadeFrete = '9',
        public readonly ?TransportadoraDocumento $transportadora = null,
        public readonly array $volumes = [],
    ) {
    }
}
