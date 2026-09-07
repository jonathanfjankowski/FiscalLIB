<?php

declare(strict_types=1);

namespace FiscalLib\Servicos;

use FiscalLib\Contracts\EmissorInterface;
use FiscalLib\Documento\NfeDocumento;
use FiscalLib\Documento\NfseDocumento;
use FiscalLib\Nfce\NfceBuilder;

/**
 * NFC-e (modelo 65): builder + emissão/consulta/PDF.
 */
final class ServicoNfce extends ServicoDocumentos
{
    public function novo(): NfceBuilder
    {
        return NfceBuilder::nfce();
    }

    public function emitir(NfeDocumento|NfseDocumento $documento, ?\FiscalLib\Contracts\OpcoesEmissao $opcoes = null): \FiscalLib\Documento\ResultadoEmissao
    {
        if (! $documento instanceof NfeDocumento || $documento->modelo !== \FiscalLib\Common\Enums\ModeloDocumento::Nfce) {
            throw new \InvalidArgumentException('ServicoNfce só emite NfeDocumento com modelo 65.');
        }

        return parent::emitir($documento, $opcoes);
    }
}
