<?php

declare(strict_types=1);

namespace FiscalLib\Contracts;

/**
 * A PORTA da lib: como qualquer documento pronto (modelo Documento/*) vira
 * um documento fiscal no mundo real. A lib entrega UMA implementação —
 * Adapters\FiscalApi\EmissorFiscalApi — e documenta este contrato para quem
 * quiser plugar outra API ou a SEFAZ direta.
 */
interface EmissorInterface
{
    /**
     * Submete o documento e devolve o aceite imediato (ex.: 202 + id).
     * Retry de rede deve reutilizar a mesma idempotency key.
     */
    public function emitir(
        \FiscalLib\Documento\NfeDocumento|\FiscalLib\Documento\NfseDocumento $documento,
        ?OpcoesEmissao $opcoes = null,
    ): \FiscalLib\Documento\AceiteEmissao;

    /** Estado atual do documento pelo id do aceite. */
    public function consultar(string $documentoId): \FiscalLib\Documento\ResultadoEmissao;

    /** Cancelamento de documento AUTORIZADA (justificativa 15–1000 chars). */
    public function cancelar(string $documentoId, string $justificativa, ?OpcoesEvento $opcoes = null): \FiscalLib\Documento\ResultadoEvento;

    /** Carta de correção — apenas NF-e (modelo 55), 15–1000 chars. */
    public function cartaCorrecao(string $documentoId, string $correcao, ?OpcoesEvento $opcoes = null): \FiscalLib\Documento\ResultadoEvento;

    /** Inutiliza uma faixa de numeração. */
    public function inutilizar(\FiscalLib\Documento\InutilizacaoPedido $pedido, ?OpcoesEvento $opcoes = null): \FiscalLib\Documento\ResultadoEvento;

    /** Consulta o estado de um pedido de inutilização. */
    public function consultarInutilizacao(string $eventoId): \FiscalLib\Documento\ResultadoEvento;

    /** DANFE/PDF do documento (autorizado ou cancelado). */
    public function baixarPdf(string $documentoId, bool $emBase64 = false): \FiscalLib\Documento\ArquivoPdf;

    /**
     * Substituição de NFS-e autorizada (o DPS substituído vira a nova nota).
     * cMotivo 1–5 e 99 (99 exige xMotivo).
     */
    public function substituir(
        string $documentoId,
        \FiscalLib\Documento\NfseDocumento $substituta,
        int $cMotivo,
        ?string $xMotivo = null,
        ?OpcoesEmissao $opcoes = null,
    ): \FiscalLib\Documento\AceiteEmissao;
}
