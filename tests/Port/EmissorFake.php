<?php

declare(strict_types=1);

namespace FiscalLib\Tests\Port;

use FiscalLib\Contracts\EmissorInterface;
use FiscalLib\Contracts\OpcoesEmissao;
use FiscalLib\Contracts\OpcoesEvento;
use FiscalLib\Documento\AceiteEmissao;
use FiscalLib\Documento\ArquivoPdf;
use FiscalLib\Documento\InutilizacaoPedido;
use FiscalLib\Documento\NfeDocumento;
use FiscalLib\Documento\NfseDocumento;
use FiscalLib\Documento\ResultadoEmissao;
use FiscalLib\Documento\ResultadoEvento;

/**
 * Implementação mínima da porta para testar o NÚCLEO sem qualquer HTTP.
 * Prova que um terceiro pode plugar outra API/SEFAZ implementando só isto.
 */
class EmissorFake implements EmissorInterface
{
    /** @var list<array{documento: NfeDocumento|NfseDocumento, opcoes: ?OpcoesEmissao}> */
    public array $emissoes = [];

    /** @var list<ResultadoEmissao> fila consumida por consultar() */
    public array $filaConsultas = [];

    /** @var list<string> */
    public array $cancelamentos = [];

    public ResultadoEvento $proximoEvento;
    public function __construct()
    {
        $this->proximoEvento = new ResultadoEvento('evento-1', 'CANCELAMENTO', 'PENDENTE');
    }

    public function emitir(NfeDocumento|NfseDocumento $documento, ?OpcoesEmissao $opcoes = null): AceiteEmissao
    {
        $this->emissoes[] = ['documento' => $documento, 'opcoes' => $opcoes];

        return new AceiteEmissao('doc-1', 'PENDENTE', $documento->ambiente->value, null, '/v1/documentos-fiscais/doc-1');
    }

    public function consultar(string $documentoId): ResultadoEmissao
    {
        if ($this->filaConsultas !== []) {
            return array_shift($this->filaConsultas);
        }

        return new ResultadoEmissao($documentoId, 'AUTORIZADA', null, 'NFE', 'homologacao', 1, 42, '41260912345678000199550010000000421012345678');
    }

    public function cancelar(string $documentoId, string $justificativa, ?OpcoesEvento $opcoes = null): ResultadoEvento
    {
        $this->cancelamentos[] = $documentoId;

        return $this->proximoEvento;
    }

    public function cartaCorrecao(string $documentoId, string $correcao, ?OpcoesEvento $opcoes = null): ResultadoEvento
    {
        return new ResultadoEvento('evento-2', 'CCE', 'PENDENTE', $documentoId);
    }

    public function inutilizar(InutilizacaoPedido $pedido, ?OpcoesEvento $opcoes = null): ResultadoEvento
    {
        return new ResultadoEvento('evento-3', 'INUTILIZACAO', 'PENDENTE');
    }

    public function consultarInutilizacao(string $eventoId): ResultadoEvento
    {
        return new ResultadoEvento($eventoId, 'INUTILIZACAO', 'PROCESSADO');
    }

    public function baixarPdf(string $documentoId, bool $emBase64 = false): ArquivoPdf
    {
        return new ArquivoPdf('%PDF-1.4 fake');
    }

    public function substituir(string $documentoId, NfseDocumento $substituta, int $cMotivo, ?string $xMotivo = null, ?OpcoesEmissao $opcoes = null): AceiteEmissao
    {
        return new AceiteEmissao('doc-2', 'PENDENTE');
    }
}
