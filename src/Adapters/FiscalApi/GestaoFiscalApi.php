<?php

declare(strict_types=1);

namespace FiscalLib\Adapters\FiscalApi;

use FiscalLib\Common\Enums\Ambiente;
use FiscalLib\Common\Enums\ModeloDocumento;
use FiscalLib\Common\Enums\TipoManifestacao;
use FiscalLib\Config\FiscalConfig;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\MultipartStream;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Endpoints de gestão da FiscalAPI (fora do EmissorInterface — são
 * específicos desta API): certificados, api-keys, perfil/webhooks do tenant,
 * status-serviço e distribuição DFe + manifestação.
 *
 * Métodos devolvem o JSON decodificado (array) — a forma desses recursos é
 * estável e autoexplicativa; DTOs rígidos aqui adicionariam ruído.
 */
final class GestaoFiscalApi
{
    private readonly ClienteHttp $http;
    private readonly FiscalConfig $config;
    private readonly HttpFactory $fabrica;

    public function __construct(FiscalConfig $config, ?ClientInterface $http = null)
    {
        $this->config = $config;
        $this->http = new ClienteHttp($config, $http);
        $this->fabrica = new HttpFactory();
    }

    // ---------------------------------------------------------- certificados

    /** Upload do certificado A1 (.pfx, até 10 MB) por caminho de arquivo. */
    public function enviarCertificado(string $caminhoPfx, string $senha): array
    {
        return $this->enviarCertificadoConteudo((string) file_get_contents($caminhoPfx), $senha, basename($caminhoPfx));
    }

    public function enviarCertificadoConteudo(string $conteudoPfx, string $senha, string $nomeArquivo = 'certificado.pfx'): array
    {
        $multipart = new MultipartStream([
            ['name' => 'pfx', 'filename' => $nomeArquivo, 'contents' => $conteudoPfx],
            ['name' => 'senha', 'contents' => $senha],
        ]);

        $requisicao = $this->fabrica
            ->createRequest('POST', $this->config->baseUrl . '/v1/certificados')
            ->withBody($multipart)
            ->withHeader('Content-Type', 'multipart/form-data; boundary=' . $multipart->getBoundary())
            ->withHeader('Accept', 'application/json');

        return $this->decodificar($this->http->enviarAutenticado($requisicao));
    }

    /** @return list<array<string,mixed>> Metadados — inclua monitoramento de `validoAte`. */
    public function listarCertificados(): array
    {
        return $this->http->get('/v1/certificados');
    }

    // -------------------------------------------------------------- api keys

    public function criarApiKey(Ambiente $ambiente, ?string $descricao = null): array
    {
        return $this->http->post('/v1/api-keys', array_filter([
            'ambiente' => $ambiente->value,
            'descricao' => $descricao,
        ], static fn ($v) => $v !== null), EmissorFiscalApi::novaIdempotencyKey());
    }

    /** @return list<array<string,mixed>> */
    public function listarApiKeys(): array
    {
        return $this->http->get('/v1/api-keys');
    }

    public function revogarApiKey(string $id): void
    {
        $this->http->delete('/v1/api-keys/' . rawurlencode($id));
    }

    // ---------------------------------------------------------- tenant / CSC

    public function perfil(): array
    {
        return $this->http->get('/v1/tenants/perfil');
    }

    /** Campos nulos não são alterados pela API. CSC (NFC-e) é gravado cifrado. */
    public function atualizarPerfil(array $campos): array
    {
        return $this->http->put('/v1/tenants/perfil', $campos);
    }

    public function webhooks(): array
    {
        return $this->http->get('/v1/tenants/webhooks');
    }

    public function configurarWebhooks(?string $webhookUrl = null, ?string $webhookSecret = null): array
    {
        return $this->http->put('/v1/tenants/webhooks', array_filter([
            'webhookUrl' => $webhookUrl,
            'webhookSecret' => $webhookSecret,
        ], static fn ($v) => $v !== null));
    }

    // --------------------------------------------------------- status serviço

    public function statusServico(ModeloDocumento $modelo, Ambiente $ambiente): array
    {
        return $this->http->get('/v1/status-servico?' . http_build_query([
            'modelo' => $modelo->value,
            'ambiente' => $ambiente->value,
        ]));
    }

    // -------------------------------------------------- notas recebidas (DFe)

    public function notasRecebidas(?string $nsu = null): array
    {
        return $this->http->get('/v1/notas-recebidas' . ($nsu !== null ? '?nsu=' . rawurlencode($nsu) : ''));
    }

    public function notaRecebida(string $id): array
    {
        return $this->http->get('/v1/notas-recebidas/' . rawurlencode($id));
    }

    public function xmlCompletoNotaRecebida(string $id): string
    {
        $requisicao = $this->fabrica
            ->createRequest('GET', $this->config->baseUrl . '/v1/notas-recebidas/' . rawurlencode($id) . '/xml-completo')
            ->withHeader('Accept', 'application/xml, text/xml');

        return (string) $this->http->enviarAutenticado($requisicao)->getBody();
    }

    public function manifestar(string $notaRecebidaId, TipoManifestacao $tipo, ?string $justificativa = null): array
    {
        return $this->http->post(
            '/v1/notas-recebidas/' . rawurlencode($notaRecebidaId) . '/manifestacao',
            array_filter(['tipo' => $tipo->value, 'justificativa' => $justificativa], static fn ($v) => $v !== null),
            EmissorFiscalApi::novaIdempotencyKey(),
        );
    }

    // ------------------------------------------------------------------ utils

    private function decodificar(ResponseInterface $resposta): array
    {
        $dados = json_decode((string) $resposta->getBody(), true);

        return is_array($dados) ? $dados : [];
    }
}
