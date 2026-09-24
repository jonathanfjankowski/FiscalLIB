<?php

declare(strict_types=1);

namespace FiscalLib\Servicos;

use FiscalLib\Contracts\EmissorInterface;
use FiscalLib\Contracts\OpcoesEvento;
use FiscalLib\Documento\InutilizacaoPedido;
use FiscalLib\Documento\ResultadoEvento;
use FiscalLib\Exceptions\ValidationException;

/**
 * Eventos fiscais: cancelamento, carta de correção e inutilização.
 * As regras de entrada (justificativa 15–1000, faixa coerente) valem para
 * QUALQUER emissor — vivem aqui na porta, não só no adaptador FiscalAPI.
 */
final class ServicoEventos
{
    public function __construct(private readonly EmissorInterface $emissor)
    {
    }

    /** Cancela documento AUTORIZADA (justificativa 15–1000). Vira CANCELAMENTO_PENDENTE → CANCELADA. */
    public function cancelar(string $documentoId, string $justificativa, ?OpcoesEvento $opcoes = null): ResultadoEvento
    {
        self::exigirTexto($justificativa, 'justificativa');

        return $this->emissor->cancelar($documentoId, $justificativa, $opcoes);
    }

    /** CC-e — apenas NF-e (modelo 55). NFC-e: cancele e reemita. */
    public function cartaCorrecao(string $documentoId, string $correcao, ?OpcoesEvento $opcoes = null): ResultadoEvento
    {
        self::exigirTexto($correcao, 'correcao');

        return $this->emissor->cartaCorrecao($documentoId, $correcao, $opcoes);
    }

    public function inutilizar(InutilizacaoPedido $pedido, ?OpcoesEvento $opcoes = null): ResultadoEvento
    {
        self::exigirTexto($pedido->justificativa, 'justificativa');
        if ($pedido->numeroFinal < $pedido->numeroInicial) {
            throw ValidationException::erro('numeroFinal', 'numeroFinal não pode ser menor que numeroInicial.');
        }

        return $this->emissor->inutilizar($pedido, $opcoes);
    }

    public function consultarInutilizacao(string $eventoId): ResultadoEvento
    {
        return $this->emissor->consultarInutilizacao($eventoId);
    }

    /** XML do evento (cancelamento/CC-e) já protocolado — para arquivamento. */
    public function baixarXmlEvento(string $documentoId, string $eventoId): string
    {
        return $this->emissor->baixarXmlEvento($documentoId, $eventoId);
    }

<<<<<<< HEAD
    /** XML do evento (cancelamento/CC-e) já protocolado — para arquivamento. */
    public function baixarXmlEvento(string $documentoId, string $eventoId): string
    {
        return $this->emissor->baixarXmlEvento($documentoId, $eventoId);
=======
    /** Regra SEFAZ: justificativa/correção entre 15 e 1000 caracteres. */
    private static function exigirTexto(string $texto, string $campo): void
    {
        $tamanho = mb_strlen(trim($texto));
        if ($tamanho < 15 || $tamanho > 1000) {
            throw ValidationException::erro($campo, "{$campo} deve ter entre 15 e 1000 caracteres (regra SEFAZ).");
        }
>>>>>>> 4c6f691 (feat: Fase 5 da auditoria — cronograma IBS/CBS, R-NFS014, eventos na porta + cobertura)
    }
}
