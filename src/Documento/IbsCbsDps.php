<?php

declare(strict_types=1);

namespace FiscalLib\Documento;

/**
 * Bloco RTC (IBS/CBS) da DPS — layout 1.01. Só códigos: os valores são
 * calculados pelo Ambiente Nacional (R-NFS007).
 */
final class IbsCbsDps
{
    public function __construct(
        public readonly string $codigoIndicadorOperacao, // cIndOp — 6 dígitos (SEPEC)
        public readonly string $cstIbsCbs,               // 3 dígitos
        public readonly string $cClassTrib,              // 6 dígitos
        public readonly int $finalidade = 0,             // finNFSe — 0 regular
        public readonly ?int $indicadorFinal = 1,        // indFinal 0/1
        public readonly ?int $indicadorDestinatario = 0, // indDest 0/1
        public readonly ?int $tipoOperacaoGov = null,    // tpOper 1–5 (ente governamental)
        public readonly ?int $tipoEnteGovernamental = null, // tpEnteGov 1–4
        public readonly ?string $codigoCreditoPresumido = null, // 2 dígitos
    ) {
    }
}
