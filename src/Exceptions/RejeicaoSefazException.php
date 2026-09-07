<?php

declare(strict_types=1);

namespace FiscalLib\Exceptions;

/**
 * A SEFAZ rejeitou/denegou o documento. Carrega o motivo legível,
 * o XML de retorno (onde está o detalhe técnico) e o código da rejeição
 * quando conseguido extrair.
 */
final class RejeicaoSefazException extends FiscalLibException
{
    public function __construct(
        string $message,
        public readonly string $status,
        public readonly ?string $motivoStatus = null,
        public readonly ?string $xmlRetornoSefaz = null,
        public readonly ?string $codigoRejeicao = null,
        public readonly ?string $chaveAcesso = null,
    ) {
        parent::__construct($message);
    }
}
