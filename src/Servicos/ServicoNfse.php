<?php

declare(strict_types=1);

namespace FiscalLib\Servicos;

use FiscalLib\Contracts\EmissorInterface;
use FiscalLib\Contracts\OpcoesEmissao;
use FiscalLib\Documento\AceiteEmissao;
use FiscalLib\Documento\NfeDocumento;
use FiscalLib\Documento\NfseDocumento;
use FiscalLib\Documento\ResultadoEmissao;
use FiscalLib\Nfse\NfseBuilder;

/**
 * NFS-e Nacional (DPS): builder + emissão/consulta/substituição.
 */
final class ServicoNfse extends ServicoDocumentos
{
    public function novo(): NfseBuilder
    {
        return NfseBuilder::make();
    }

    public function emitir(NfeDocumento|NfseDocumento $documento, ?OpcoesEmissao $opcoes = null): ResultadoEmissao
    {
        if (! $documento instanceof NfseDocumento) {
            throw new \InvalidArgumentException('ServicoNfse só emite NfseDocumento.');
        }

        return parent::emitir($documento, $opcoes);
    }

    /**
     * Substituição de NFS-e autorizada. cMotivo 1–5 e 99 (99 exige xMotivo).
     * Devolve o aceite da SUBSTITUTA — acompanhe pelo novo documentoId.
     */
    public function substituir(
        string $documentoId,
        NfseDocumento $substituta,
        int $cMotivo,
        ?string $xMotivo = null,
        ?OpcoesEmissao $opcoes = null,
    ): AceiteEmissao {
        return $this->emissor->substituir($documentoId, $substituta, $cMotivo, $xMotivo, $opcoes);
    }
}
