<?php

declare(strict_types=1);

namespace FiscalLib\Adapters\FiscalApi;

use FiscalLib\Common\Enums\StatusDocumento;
use FiscalLib\Documento\AceiteEmissao;
use FiscalLib\Documento\ResultadoEmissao;
use FiscalLib\Documento\ResultadoEvento;

/**
 * JSON da FiscalAPI → modelos de resultado da lib.
 */
final class MapeadorResultado
{
    /** @param array<string,mixed> $dados */
    public function paraResultadoEmissao(array $dados): ResultadoEmissao
    {
        $status = (string) ($dados['status'] ?? '');

        return new ResultadoEmissao(
            documentoId: (string) ($dados['id'] ?? ''),
            status: $status,
            statusDocumento: StatusDocumento::deTextoOuDesconhecido($status),
            tipo: isset($dados['tipo']) ? (string) $dados['tipo'] : null,
            ambiente: isset($dados['ambiente']) ? (string) $dados['ambiente'] : null,
            serie: isset($dados['serie']) ? (int) $dados['serie'] : null,
            numero: isset($dados['numero']) ? (int) $dados['numero'] : null,
            chaveAcesso: isset($dados['chaveAcesso']) ? (string) $dados['chaveAcesso'] : null,
            protocoloAutorizacao: isset($dados['protocoloAutorizacao']) ? (string) $dados['protocoloAutorizacao'] : null,
            xmlAssinado: isset($dados['xmlAssinado']) ? (string) $dados['xmlAssinado'] : null,
            xmlRetornoSefaz: isset($dados['xmlRetornoSefaz']) ? (string) $dados['xmlRetornoSefaz'] : null,
            motivoStatus: isset($dados['motivoStatus']) ? (string) $dados['motivoStatus'] : null,
            criadoEm: isset($dados['criadoEm']) ? (string) $dados['criadoEm'] : null,
            atualizadoEm: isset($dados['atualizadoEm']) ? (string) $dados['atualizadoEm'] : null,
            raw: $dados,
        );
    }

    /** @param array<string,mixed> $dados */
    public function paraAceiteEmissao(array $dados): AceiteEmissao
    {
        $link = null;
        if (isset($dados['links']['consulta']) && is_string($dados['links']['consulta'])) {
            $link = $dados['links']['consulta'];
        }

        return new AceiteEmissao(
            documentoId: (string) ($dados['id'] ?? ''),
            status: (string) ($dados['status'] ?? ''),
            ambiente: isset($dados['ambiente']) ? (string) $dados['ambiente'] : null,
            criadoEm: isset($dados['criadoEm']) ? (string) $dados['criadoEm'] : null,
            linkConsulta: $link,
            raw: $dados,
        );
    }

    /** @param array<string,mixed> $dados */
    public function paraResultadoEvento(array $dados, ?string $documentoId = null): ResultadoEvento
    {
        return new ResultadoEvento(
            eventoId: (string) ($dados['eventoId'] ?? ''),
            tipo: (string) ($dados['tipo'] ?? ''),
            status: (string) ($dados['status'] ?? ''),
            documentoId: isset($dados['documentoId']) ? (string) $dados['documentoId'] : $documentoId,
            criadoEm: isset($dados['criadoEm']) ? (string) $dados['criadoEm'] : null,
            motivoStatus: isset($dados['motivoStatus']) ? (string) $dados['motivoStatus'] : null,
            raw: $dados,
        );
    }
}
