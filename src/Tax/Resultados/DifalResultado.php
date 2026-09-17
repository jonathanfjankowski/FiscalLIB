<?php

declare(strict_types=1);

namespace FiscalLib\Tax\Resultados;

/**
 * DIFAL — operação interestadual com consumidor final (impostosV2.icms.difal).
 * Partilha 100% para o destino (Convênio 190/2017): valorIcmsOrigem = 0.
 * Fórmula MOC (rejeições 815/816): vICMSUFDest = vBCUFDest × (interna − interestadual).
 */
final class DifalResultado
{
    use Serializavel;

    public function __construct(
        public readonly int $aliquotaInterestadual,      // 4, 7 ou 12 (pICMSInter)
        public readonly string $baseDestino,             // vBCUFDest
        public readonly string $aliquotaDestino,         // pICMSUFDest
        public readonly string $valorIcmsDestino,        // vICMSUFDest
        public readonly string $valorIcmsOrigem = '0.00',// vICMSUFRemet — 0 na partilha vigente
        public readonly ?string $fcpPercentualDestino = null,
        public readonly ?string $valorFcpDestino = null,
    ) {
    }
}
