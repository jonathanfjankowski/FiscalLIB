<?php

declare(strict_types=1);

namespace FiscalLib\Adapters\FiscalApi;

use FiscalLib\Config\FiscalConfig;
use FiscalLib\Exceptions\ApiHttpException;
use FiscalLib\Exceptions\ApiIndisponivelException;
use FiscalLib\Exceptions\AutenticacaoException;
use FiscalLib\Exceptions\EstadoInvalidoException;
use FiscalLib\Exceptions\LimiteRequisicoesException;
use FiscalLib\Exceptions\NaoEncontradoException;
use FiscalLib\Exceptions\ValidacaoApiException;
use GuzzleHttp\Exception\ConnectException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Cliente HTTP do adaptador FiscalAPI: header `Authorization: ApiKey`,
 * `Idempotency-Key` opcional por chamada e retry de rede (429/5xx/timeout)
 * SEMPRE com a mesma chave — seguro por design da API.
 * Erros 4xx/5xx viram exceções tipadas a partir do ProblemDetails.
 */
final class ClienteHttp
{
    private readonly ClientInterface $http;
    private readonly RequestFactoryInterface $fabricaRequisicao;
    private readonly StreamFactoryInterface $fabricaStream;

    /** @var callable(int $tentativa): void */
    private $esperar;

    public function __construct(
        private readonly FiscalConfig $config,
        ?ClientInterface $http = null,
        ?RequestFactoryInterface $fabricaRequisicao = null,
        ?StreamFactoryInterface $fabricaStream = null,
        ?callable $esperar = null,
    ) {
        $this->http = $http ?? new \GuzzleHttp\Client(['timeout' => $config->timeoutHttpSegundos]);

        $descobridor = new \GuzzleHttp\Psr7\HttpFactory();
        $this->fabricaRequisicao = $fabricaRequisicao ?? $descobridor;
        $this->fabricaStream = $fabricaStream ?? $descobridor;
        $this->esperar = $esperar ?? static function (int $tentativa): void {
            \usleep((int) (200_000 * (2 ** ($tentativa - 1))));
        };
    }

    public function get(string $caminho, ?string $idempotencyKey = null): array
    {
        return $this->enviar('GET', $caminho, null, $idempotencyKey);
    }

    /**
     * @param array<string,mixed> $corpoJson
     */
    public function post(string $caminho, array $corpoJson, string $idempotencyKey): array
    {
        return $this->enviar('POST', $caminho, $corpoJson, $idempotencyKey);
    }

    /**
     * @param array<string,mixed> $corpoJson
     */
    public function postSemIdempotencia(string $caminho, array $corpoJson): array
    {
        return $this->enviar('POST', $caminho, $corpoJson, null);
    }

    /**
     * @param array<string,mixed> $corpoJson
     */
    public function put(string $caminho, array $corpoJson): array
    {
        return $this->enviar('PUT', $caminho, $corpoJson, null);
    }

    public function delete(string $caminho): void
    {
        $this->enviar('DELETE', $caminho, null, null);
    }

    /** Envia uma requisição construída externamente, injetando auth/retry. */
    public function enviarAutenticado(RequestInterface $requisicao): ResponseInterface
    {
        foreach ($this->cabecalhosAutenticacao() as $nome => $valor) {
            $requisicao = $requisicao->withHeader($nome, $valor);
        }

        return $this->enviarComRetry($requisicao);
    }

    /** @return array<string,string> */
    private function cabecalhosAutenticacao(): array
    {
        $cabecalhos = [
            'Authorization' => 'ApiKey ' . $this->config->apiKey,
            'User-Agent' => $this->config->userAgent(),
        ];
        if ($this->config->ambiente !== null) {
            $cabecalhos['X-Fiscal-Ambiente'] = $this->config->ambiente->value;
        }

        return $cabecalhos;
    }

    /**
     * Download bruto (PDF). Devolve [conteudo, contentType].
     *
     * @return array{0:string,1:string}
     */
    public function getBinario(string $caminho): array
    {
        $requisicao = $this->novaRequisicao('GET', $caminho, null, null)
            ->withHeader('Accept', 'application/pdf, application/json');
        $resposta = $this->enviarComRetry($requisicao);

        return [(string) $resposta->getBody(), $resposta->getHeaderLine('Content-Type') ?: 'application/pdf'];
    }

    // ------------------------------------------------------------------ core

    /**
     * @param array<string,mixed>|null $corpoJson
     */
    private function enviar(string $metodo, string $caminho, ?array $corpoJson, ?string $idempotencyKey): array
    {
        $json = $corpoJson === null ? null : self::codificarJson($corpoJson);
        $requisicao = $this->novaRequisicao($metodo, $caminho, $json, $idempotencyKey)
            ->withHeader('Accept', 'application/json');
        if ($json !== null) {
            $requisicao = $requisicao->withHeader('Content-Type', 'application/json');
        }

        $resposta = $this->enviarComRetry($requisicao);
        $corpo = (string) $resposta->getBody();

        if ($corpo === '' || $corpo === 'null') {
            return [];
        }

        $decodificado = json_decode($corpo, true);
        if (! is_array($decodificado)) {
            if ($resposta->getStatusCode() >= 400) {
                throw ApiIndisponivelException::rede("Resposta não-JSON com status {$resposta->getStatusCode()}.");
            }

            throw new \FiscalLib\Exceptions\SerializationException('Resposta do emissor não é JSON válido.');
        }

        return $decodificado;
    }

    private function novaRequisicao(string $metodo, string $caminho, ?string $json, ?string $idempotencyKey): RequestInterface
    {
        $requisicao = $this->fabricaRequisicao->createRequest($metodo, $this->config->baseUrl . $caminho);

        foreach ($this->cabecalhosAutenticacao() as $nome => $valor) {
            $requisicao = $requisicao->withHeader($nome, $valor);
        }
        if ($idempotencyKey !== null) {
            $requisicao = $requisicao->withHeader('Idempotency-Key', $idempotencyKey);
        }
        if ($json !== null) {
            $requisicao = $requisicao->withBody($this->fabricaStream->createStream($json));
        }

        return $requisicao;
    }

    private function enviarComRetry(RequestInterface $requisicao): ResponseInterface
    {
        $ultimaExcecao = null;

        for ($tentativa = 1; $tentativa <= max(1, $this->config->tentativasRede); $tentativa++) {
            try {
                $resposta = $this->http->sendRequest($requisicao);
                $status = $resposta->getStatusCode();

                // 429/5xx → retry com a MESMA requisição (mesma Idempotency-Key).
                if ($status === 429 || $status >= 500) {
                    $ultimaExcecao = $this->excecaoParaStatus($resposta);
                    if ($tentativa < $this->config->tentativasRede) {
                        ($this->esperar)($tentativa);
                        continue;
                    }
                    throw $ultimaExcecao;
                }

                if ($status >= 400) {
                    throw $this->excecaoParaStatus($resposta);
                }

                return $resposta;
            } catch (ConnectException $e) {
                $ultimaExcecao = ApiIndisponivelException::rede($e->getMessage(), $e);
                if ($tentativa < $this->config->tentativasRede) {
                    ($this->esperar)($tentativa);
                    continue;
                }
                throw $ultimaExcecao;
            } catch (ClientExceptionInterface $e) {
                throw ApiIndisponivelException::rede($e->getMessage(), $e);
            }
        }

        throw $ultimaExcecao ?? ApiIndisponivelException::rede('Falha desconhecida.'); // @codeCoverageIgnore
    }

    private function excecaoParaStatus(ResponseInterface $resposta): ApiHttpException
    {
        $status = $resposta->getStatusCode();
        $problem = null;
        $corpo = (string) $resposta->getBody();

        $decodificado = json_decode($corpo, true);
        if (is_array($decodificado) && (isset($decodificado['title']) || isset($decodificado['errors']))) {
            $problem = ProblemDetails::doArray($decodificado);
        }

        $mensagem = $problem?->resumo() ?? "Erro HTTP {$status} do emissor.";

        return match (true) {
            $status === 400 || $status === 422 => new ValidacaoApiException($mensagem, $status, $problem?->raw, $problem?->campo, $problem->errosPorCampo ?? []),
            $status === 401 || $status === 403 => new AutenticacaoException($mensagem, $status, $problem?->raw),
            $status === 404 => new NaoEncontradoException($mensagem, $status, $problem?->raw),
            $status === 409 => new EstadoInvalidoException($mensagem, $status, $problem?->raw),
            $status === 429 => new LimiteRequisicoesException($mensagem, $status, $problem?->raw),
            default => ApiIndisponivelException::rede($mensagem),
        };
    }

    /** @param array<string,mixed> $corpo */
    private static function codificarJson(array $corpo): string
    {
        $json = json_encode($corpo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
        if ($json === false) {
            throw new \FiscalLib\Exceptions\SerializationException('Falha ao serializar o payload: ' . json_last_error_msg());
        }

        return $json;
    }
}
