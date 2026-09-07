<?php

declare(strict_types=1);

namespace FiscalLib\Servicos;

use FiscalLib\Contracts\EmissorInterface;
use FiscalLib\Contracts\OpcoesEmissao;
use FiscalLib\Documento\AceiteEmissao;
use FiscalLib\Documento\ArquivoPdf;
use FiscalLib\Documento\NfeDocumento;
use FiscalLib\Documento\NfseDocumento;
use FiscalLib\Documento\ResultadoEmissao;

/**
 * Serviço base dos documentos: alto nível (emitir = POST + polling até
 * estado terminal) e baixo nível (emitirAsync = só o aceite imediato —
 * persista o documentoId antes de qualquer coisa).
 */
class ServicoDocumentos
{
    public function __construct(
        protected readonly EmissorInterface $emissor,
        protected readonly AguardadorTerminal $aguardador,
    ) {
    }

    /**
     * Emissão completa: submete e aguarda o estado terminal. Rejeições da
     * SEFAZ NÃO lançam exceção aqui — o resultado carrega status/motivo
     * (use ResultadoEmissao::exigirAutorizada() se preferir exceção).
     */
    public function emitir(NfeDocumento|NfseDocumento $documento, ?OpcoesEmissao $opcoes = null): ResultadoEmissao
    {
        return $this->aguardador->aguardar($this->emitirAsync($documento, $opcoes)->documentoId);
    }

    /** Só a submissão (202 + id). Use com filas/workers do ERP. */
    public function emitirAsync(NfeDocumento|NfseDocumento $documento, ?OpcoesEmissao $opcoes = null): AceiteEmissao
    {
        return $this->emissor->emitir($documento, $opcoes);
    }

    public function consultar(string $documentoId): ResultadoEmissao
    {
        return $this->emissor->consultar($documentoId);
    }

    public function baixarPdf(string $documentoId, bool $emBase64 = false): ArquivoPdf
    {
        return $this->emissor->baixarPdf($documentoId, $emBase64);
    }
}
