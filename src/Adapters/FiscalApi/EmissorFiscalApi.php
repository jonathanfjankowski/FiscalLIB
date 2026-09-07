<?php

declare(strict_types=1);

namespace FiscalLib\Adapters\FiscalApi;

use FiscalLib\Common\Enums\ModeloDocumento;
use FiscalLib\Config\FiscalConfig;
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
use FiscalLib\Exceptions\ValidationException;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Único emissor que acompanha a lib: a FiscalAPI real.
 * Outros emissores (outra API / SEFAZ direta) implementam EmissorInterface
 * por fora — este arquivo é o exemplo vivo do contrato.
 */
final class EmissorFiscalApi implements EmissorInterface
{
    private readonly ClienteHttp $http;
    private readonly MapeadorDocumento $mapeadorDocumento;
    private readonly MapeadorResultado $mapeadorResultado;

    public function __construct(
        private readonly FiscalConfig $config,
        ?ClientInterface $http = null,
        ?RequestFactoryInterface $fabricaRequisicao = null,
        ?StreamFactoryInterface $fabricaStream = null,
        ?callable $esperar = null,
    ) {
        $this->http = new ClienteHttp($config, $http, $fabricaRequisicao, $fabricaStream, $esperar);
        $this->mapeadorDocumento = new MapeadorDocumento();
        $this->mapeadorResultado = new MapeadorResultado();
    }

    public function config(): FiscalConfig
    {
        return $this->config;
    }

    public function emitir(NfeDocumento|NfseDocumento $documento, ?OpcoesEmissao $opcoes = null): AceiteEmissao
    {
        $idempotencyKey = $opcoes->idempotencyKey ?? self::novaIdempotencyKey();

        if ($documento instanceof NfeDocumento) {
            $caminho = $documento->modelo === ModeloDocumento::Nfce
                ? '/v1/documentos-fiscais/nfce'
                : '/v1/documentos-fiscais/nfe';
            $corpo = $this->mapeadorDocumento->paraEmissaoRequest($documento);
        } else {
            $caminho = '/v1/documentos-fiscais/nfse/dps';
            $corpo = $this->mapeadorDocumento->paraNfseDps($documento);
        }

        $resposta = $this->http->post($caminho, $corpo, $idempotencyKey);

        return $this->mapeadorResultado->paraAceiteEmissao($resposta);
    }

    public function consultar(string $documentoId): ResultadoEmissao
    {
        $resposta = $this->http->get("/v1/documentos-fiscais/{$this->idSeguro($documentoId)}");

        return $this->mapeadorResultado->paraResultadoEmissao($resposta);
    }

    public function cancelar(string $documentoId, string $justificativa, ?OpcoesEvento $opcoes = null): ResultadoEvento
    {
        self::validarTexto($justificativa, 'justificativa');
        $resposta = $this->http->post(
            "/v1/documentos-fiscais/{$this->idSeguro($documentoId)}/cancelamento",
            ['justificativa' => $justificativa],
            $opcoes->idempotencyKey ?? self::novaIdempotencyKey(),
        );

        return $this->mapeadorResultado->paraResultadoEvento($resposta, $documentoId);
    }

    public function cartaCorrecao(string $documentoId, string $correcao, ?OpcoesEvento $opcoes = null): ResultadoEvento
    {
        self::validarTexto($correcao, 'correcao');
        $resposta = $this->http->post(
            "/v1/documentos-fiscais/{$this->idSeguro($documentoId)}/carta-correcao",
            ['correcao' => $correcao],
            $opcoes->idempotencyKey ?? self::novaIdempotencyKey(),
        );

        return $this->mapeadorResultado->paraResultadoEvento($resposta, $documentoId);
    }

    public function inutilizar(InutilizacaoPedido $pedido, ?OpcoesEvento $opcoes = null): ResultadoEvento
    {
        self::validarTexto($pedido->justificativa, 'justificativa');
        if ($pedido->numeroFinal < $pedido->numeroInicial) {
            throw ValidationException::erro('numeroFinal', 'numeroFinal não pode ser menor que numeroInicial.');
        }

        $resposta = $this->http->post('/v1/inutilizacoes', [
            'ambiente' => $pedido->ambiente->value,
            'modelo' => $pedido->modelo === ModeloDocumento::Nfce ? 65 : 55,
            'serie' => $pedido->serie,
            'numeroInicial' => $pedido->numeroInicial,
            'numeroFinal' => $pedido->numeroFinal,
            'justificativa' => $pedido->justificativa,
        ], $opcoes->idempotencyKey ?? self::novaIdempotencyKey());

        return $this->mapeadorResultado->paraResultadoEvento($resposta);
    }

    public function consultarInutilizacao(string $eventoId): ResultadoEvento
    {
        $resposta = $this->http->get('/v1/inutilizacoes/' . $this->idSeguro($eventoId));

        return $this->mapeadorResultado->paraResultadoEvento($resposta);
    }

    public function baixarPdf(string $documentoId, bool $emBase64 = false): ArquivoPdf
    {
        $caminho = "/v1/documentos-fiscais/{$this->idSeguro($documentoId)}/pdf";
        if ($emBase64) {
            $caminho .= '?formato=base64';
            $dados = $this->http->get($caminho);

            return new ArquivoPdf((string) ($dados['pdfBase64'] ?? ''), true, (string) ($dados['contentType'] ?? 'application/pdf'));
        }

        [$conteudo, $contentType] = $this->http->getBinario($caminho);

        return new ArquivoPdf($conteudo, false, $contentType);
    }

    public function substituir(
        string $documentoId,
        NfseDocumento $substituta,
        int $cMotivo,
        ?string $xMotivo = null,
        ?OpcoesEmissao $opcoes = null,
    ): AceiteEmissao {
        if (! in_array($cMotivo, [1, 2, 3, 4, 5, 99], true)) {
            throw ValidationException::erro('cMotivo', 'cMotivo aceita 1–5 e 99.');
        }
        if ($cMotivo === 99 && ($xMotivo === null || trim($xMotivo) === '')) {
            throw ValidationException::erro('xMotivo', 'cMotivo 99 exige xMotivo.');
        }

        $corpo = [
            'dps' => $this->mapeadorDocumento->paraNfseDps($substituta),
            'cMotivo' => $cMotivo,
        ];
        if ($xMotivo !== null) {
            $corpo['xMotivo'] = $xMotivo;
        }

        $resposta = $this->http->post(
            "/v1/documentos-fiscais/{$this->idSeguro($documentoId)}/substituicao",
            $corpo,
            $opcoes->idempotencyKey ?? self::novaIdempotencyKey(),
        );

        return $this->mapeadorResultado->paraAceiteEmissao($resposta);
    }

    // ------------------------------------------------------------------ utils

    public static function novaIdempotencyKey(): string
    {
        $dados = random_bytes(16);
        $dados[6] = chr((ord($dados[6]) & 0x0f) | 0x40);
        $dados[8] = chr((ord($dados[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s%s%s-%s%s-%s%s-%s%s-%s%s%s%s%s%s', str_split(bin2hex($dados), 2));
    }

    private static function validarTexto(string $texto, string $campo): void
    {
        $tamanho = mb_strlen(trim($texto));
        if ($tamanho < 15 || $tamanho > 1000) {
            throw ValidationException::erro($campo, "{$campo} deve ter entre 15 e 1000 caracteres (regra SEFAZ).");
        }
    }

    /** Barras e caracteres de caminho não são permitidos em ids. */
    private function idSeguro(string $id): string
    {
        if (preg_match('/[^A-Za-z0-9\-]/', $id)) {
            throw ValidationException::erro('id', 'Identificador inválido.');
        }

        return rawurlencode($id);
    }
}
