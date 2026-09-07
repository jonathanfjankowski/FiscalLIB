<?php

declare(strict_types=1);

namespace FiscalLib\Documento;

use FiscalLib\Common\Enums\StatusDocumento;
use FiscalLib\Exceptions\RejeicaoSefazException;

/**
 * Resultado consolidado de um documento fiscal (consulta ou emissão aguardada).
 * Campos nulos são comuns antes da autorização.
 */
final class ResultadoEmissao
{
    public function __construct(
        public readonly string $documentoId,
        public readonly string $status,
        public readonly ?StatusDocumento $statusDocumento = null,
        public readonly ?string $tipo = null,
        public readonly ?string $ambiente = null,
        public readonly ?int $serie = null,
        public readonly ?int $numero = null,
        public readonly ?string $chaveAcesso = null,
        public readonly ?string $protocoloAutorizacao = null,
        public readonly ?string $xmlAssinado = null,
        public readonly ?string $xmlRetornoSefaz = null,
        public readonly ?string $motivoStatus = null,
        public readonly ?string $criadoEm = null,
        public readonly ?string $atualizadoEm = null,
        /** Payload original do emissor, para quem precisar de campos extras. */
        public readonly ?array $raw = null,
    ) {
    }

    public function statusEnum(): ?StatusDocumento
    {
        return $this->statusDocumento ?? StatusDocumento::deTextoOuDesconhecido($this->status);
    }

    public function isTerminal(): bool
    {
        $status = $this->statusEnum();

        return $status?->isTerminal() ?? false;
    }

    public function isAutorizada(): bool
    {
        return $this->statusEnum() === StatusDocumento::Autorizada;
    }

    public function isRejeicaoSefaz(): bool
    {
        return $this->statusEnum()?->isRejeicaoSefaz() ?? false;
    }

    /**
     * Exige AUTORIZADA — lança RejeicaoSefazException (com código extraído)
     * quando REJEITADA/DENEGADA, e RejeicaoSefazException genérica para ERRO_INTERNO.
     */
    public function exigirAutorizada(): self
    {
        $status = $this->statusEnum();
        if ($status === StatusDocumento::Autorizada) {
            return $this;
        }

        if ($status !== null && $status->isRejeicaoSefaz()) {
            throw new RejeicaoSefazException(
                $this->motivoStatus ?? "Documento {$this->status}.",
                $this->status,
                $this->motivoStatus,
                $this->xmlRetornoSefaz,
                $this->extrairCodigoRejeicao(),
                $this->chaveAcesso,
            );
        }

        throw new RejeicaoSefazException(
            $this->motivoStatus ?? "Documento em status {$this->status}, não autorizado.",
            $this->status,
            $this->motivoStatus,
            $this->xmlRetornoSefaz,
            null,
            $this->chaveAcesso,
        );
    }

    /** Extrai o código da rejeição do motivoStatus ou do XML de retorno da SEFAZ. */
    public function extrairCodigoRejeicao(): ?string
    {
        if ($this->motivoStatus !== null && preg_match('/(?:Rejei[çc][ãa]o|Denega[çc][ãa]o)\s*(\d{3})/iu', $this->motivoStatus, $m)) {
            return $m[1];
        }

        if ($this->xmlRetornoSefaz !== null
            && preg_match('/<cStat[^>]*>(\d{3})<\/cStat>/u', $this->xmlRetornoSefaz, $m)) {
            return $m[1];
        }

        return null;
    }
}
