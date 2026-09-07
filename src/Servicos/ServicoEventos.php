<?php

declare(strict_types=1);

namespace FiscalLib\Servicos;

use FiscalLib\Contracts\EmissorInterface;
use FiscalLib\Contracts\OpcoesEvento;
use FiscalLib\Documento\InutilizacaoPedido;
use FiscalLib\Documento\ResultadoEvento;

/**
 * Eventos fiscais: cancelamento, carta de correção e inutilização.
 */
final class ServicoEventos
{
    public function __construct(private readonly EmissorInterface $emissor)
    {
    }

    /** Cancela documento AUTORIZADA (justificativa 15–1000). Vira CANCELAMENTO_PENDENTE → CANCELADA. */
    public function cancelar(string $documentoId, string $justificativa, ?OpcoesEvento $opcoes = null): ResultadoEvento
    {
        return $this->emissor->cancelar($documentoId, $justificativa, $opcoes);
    }

    /** CC-e — apenas NF-e (modelo 55). NFC-e: cancele e reemita. */
    public function cartaCorrecao(string $documentoId, string $correcao, ?OpcoesEvento $opcoes = null): ResultadoEvento
    {
        return $this->emissor->cartaCorrecao($documentoId, $correcao, $opcoes);
    }

    public function inutilizar(InutilizacaoPedido $pedido, ?OpcoesEvento $opcoes = null): ResultadoEvento
    {
        return $this->emissor->inutilizar($pedido, $opcoes);
    }

    public function consultarInutilizacao(string $eventoId): ResultadoEvento
    {
        return $this->emissor->consultarInutilizacao($eventoId);
    }
}
