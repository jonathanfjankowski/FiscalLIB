<?php

declare(strict_types=1);

namespace FiscalLib\Servicos;

use FiscalLib\Contracts\EmissorInterface;
use FiscalLib\Documento\NfeDocumento;
use FiscalLib\Documento\NfseDocumento;
use FiscalLib\Nfe\NfeBuilder;

/**
 * NF-e (modelo 55): builder + emissão/consulta/PDF.
 */
final class ServicoNfe extends ServicoDocumentos
{
    public function novo(): NfeBuilder
    {
        return NfeBuilder::nfe();
    }

    public function emitir(NfeDocumento|NfseDocumento $documento, ?\FiscalLib\Contracts\OpcoesEmissao $opcoes = null): \FiscalLib\Documento\ResultadoEmissao
    {
        if (! $documento instanceof NfeDocumento) {
            throw new \InvalidArgumentException('ServicoNfe só emite NfeDocumento.');
        }

        return parent::emitir($documento, $opcoes);
    }
}
