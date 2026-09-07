<?php

declare(strict_types=1);

namespace FiscalLib\Common\Enums;

/**
 * Status de um documento fiscal na FiscalAPI (valores literais do contrato).
 */
enum StatusDocumento: string
{
    case Pendente = 'PENDENTE';
    case Processando = 'PROCESSANDO';
    case Autorizada = 'AUTORIZADA';
    case Rejeitada = 'REJEITADA';
    case Contingencia = 'CONTINGENCIA';
    case CancelamentoPendente = 'CANCELAMENTO_PENDENTE';
    case Cancelada = 'CANCELADA';
    case ErroCancelamento = 'ERRO_CANCELAMENTO';
    case Denegada = 'DENEGADA';
    case ErroInterno = 'ERRO_INTERNO';

    public function isTerminal(): bool
    {
        return in_array($this, [
            self::Autorizada,
            self::Rejeitada,
            self::Denegada,
            self::Cancelada,
            self::ErroInterno,
        ], true);
    }

    public function isTransitorio(): bool
    {
        return ! $this->isTerminal();
    }

    public function isAutorizadaOuEquivalente(): bool
    {
        return in_array($this, [self::Autorizada, self::Denegada], true);
    }

    public function isRejeicaoSefaz(): bool
    {
        return in_array($this, [self::Rejeitada, self::Denegada], true);
    }

    /** Converte um status desconhecido (API evoluiu) sem quebrar. */
    public static function deTexto(string $texto): self
    {
        $valor = mb_strtoupper(trim($texto));

        return self::tryFrom($valor) ?? self::ErroInterno; // caller deve checar match exato antes
    }

    public static function deTextoOuDesconhecido(string $texto): ?self
    {
        return self::tryFrom(mb_strtoupper(trim($texto)));
    }
}
