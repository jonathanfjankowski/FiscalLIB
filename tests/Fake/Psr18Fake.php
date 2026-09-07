<?php

declare(strict_types=1);

namespace FiscalLib\Tests\Fake;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Cliente PSR-18 falso: grava requisições e devolve respostas em fila.
 */
final class Psr18Fake implements ClientInterface
{
    /** @var list<RequestInterface> */
    public array $requisicoes = [];

    /** @var list<ResponseInterface> */
    public array $respostas = [];

    public function enviarNova(ResponseInterface $resposta): void
    {
        $this->respostas[] = $resposta;
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->requisicoes[] = $request;

        if ($this->respostas !== []) {
            return array_shift($this->respostas);
        }

        return new \GuzzleHttp\Psr7\Response(200, ['Content-Type' => 'application/json'], '{}');
    }
}
