<?php

declare(strict_types=1);

namespace FiscalLib\Tests\Unit;

use FiscalLib\Common\Enums\Ambiente;
use FiscalLib\Common\Enums\ModeloDocumento;
use FiscalLib\Common\Enums\TipoManifestacao;
use FiscalLib\Adapters\FiscalApi\GestaoFiscalApi;
use FiscalLib\Config\FiscalConfig;
use FiscalLib\Tests\Fake\Psr18Fake;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

/**
 * Endpoints de gestão (fora da porta EmissorInterface): cada método tem
 * contrato HTTP próprio — URL, método, corpo e headers — travado aqui.
 */
final class GestaoFiscalApiTest extends TestCase
{
    private Psr18Fake $fake;
    private GestaoFiscalApi $gestao;

    protected function setUp(): void
    {
        $this->fake = new Psr18Fake();
        $this->gestao = new GestaoFiscalApi(FiscalConfig::criar('http://api.test', 'fk_test_abc'), $this->fake);
    }

    private function respostaJson(string $corpo): Response
    {
        return new Response(200, ['Content-Type' => 'application/json'], $corpo);
    }

    // ---------------------------------------------------------- certificados

    public function testEnviarCertificadoConteudoMontaMultipartComIdempotencyKey(): void
    {
        $this->fake->enviarNova($this->respostaJson('{"id":"cert-1"}'));

        $dados = $this->gestao->enviarCertificadoConteudo('CONTEUDO-PFX', 'senha-do-pfx', 'meu-cert.pfx');

        self::assertSame(['id' => 'cert-1'], $dados);
        $req = $this->fake->requisicoes[0];
        self::assertSame('POST', $req->getMethod());
        self::assertSame('http://api.test/v1/certificados', (string) $req->getUri());
        self::assertNotSame('', $req->getHeaderLine('Idempotency-Key'), 'retry de 5xx não pode reenviar o PFX sem idempotência');
        self::assertStringContainsString('multipart/form-data; boundary=', $req->getHeaderLine('Content-Type'));
        $corpo = (string) $req->getBody();
        self::assertStringContainsString('meu-cert.pfx', $corpo);
        self::assertStringContainsString('CONTEUDO-PFX', $corpo);
        self::assertStringContainsString('senha-do-pfx', $corpo);
    }

    public function testEnviarCertificadoPorCaminhoLeOArquivo(): void
    {
        $caminho = tempnam(sys_get_temp_dir(), 'pfx');
        \assert($caminho !== false);
        file_put_contents($caminho, 'PFX-DO-DISCO');
        try {
            $this->fake->enviarNova($this->respostaJson('{"ok":true}'));

            $this->gestao->enviarCertificado($caminho, 'senha');

            $corpo = (string) $this->fake->requisicoes[0]->getBody();
            self::assertStringContainsString(basename($caminho), $corpo);
            self::assertStringContainsString('PFX-DO-DISCO', $corpo);
        } finally {
            @unlink($caminho);
        }
    }

    public function testListarCertificados(): void
    {
        $this->fake->enviarNova($this->respostaJson('[{"id":"cert-1","validoAte":"2027-01-01"}]'));

        $lista = $this->gestao->listarCertificados();

        self::assertSame('http://api.test/v1/certificados', (string) $this->fake->requisicoes[0]->getUri());
        self::assertSame('2027-01-01', $lista[0]['validoAte']);
    }

    // ---------------------------------------------------------------- api keys

    public function testCriarApiKeyEnviaAmbienteEDescricao(): void
    {
        $this->fake->enviarNova($this->respostaJson('{"id":"key-1","chave":"fk_x"}'));

        $dados = $this->gestao->criarApiKey(Ambiente::Producao, 'ERP produção');

        self::assertSame('POST', $this->fake->requisicoes[0]->getMethod());
        self::assertNotSame('', $this->fake->requisicoes[0]->getHeaderLine('Idempotency-Key'));
        $corpo = json_decode((string) $this->fake->requisicoes[0]->getBody(), true);
        self::assertSame('producao', $corpo['ambiente']);
        self::assertSame('ERP produção', $corpo['descricao']);
        self::assertSame('key-1', $dados['id']);
    }

    public function testCriarApiKeySemDescricaoOmiteOCampo(): void
    {
        $this->fake->enviarNova($this->respostaJson('{"id":"key-2"}'));

        $this->gestao->criarApiKey(Ambiente::Homologacao);

        $corpo = (string) $this->fake->requisicoes[0]->getBody();
        self::assertStringNotContainsString('descricao', $corpo);
    }

    public function testListarERevogarApiKeys(): void
    {
        $this->fake->enviarNova($this->respostaJson('[{"id":"key-1"}]'));
        $this->gestao->listarApiKeys();

        $this->fake->enviarNova(new Response(204));
        $this->gestao->revogarApiKey('key 1/á');

        self::assertSame('http://api.test/v1/api-keys', (string) $this->fake->requisicoes[0]->getUri());
        self::assertSame('DELETE', $this->fake->requisicoes[1]->getMethod());
        self::assertSame('http://api.test/v1/api-keys/key%201%2F%C3%A1', (string) $this->fake->requisicoes[1]->getUri());
    }

    // -------------------------------------------------------------- tenant/CSC

    public function testPerfilEAtualizarPerfil(): void
    {
        $this->fake->enviarNova($this->respostaJson('{"cnpj":"12345678000199"}'));
        $perfil = $this->gestao->perfil();

        $this->fake->enviarNova($this->respostaJson('{"cnpj":"12345678000199","ie":"123"}'));
        $atualizado = $this->gestao->atualizarPerfil(['inscricaoEstadual' => '123']);

        self::assertSame('http://api.test/v1/tenants/perfil', (string) $this->fake->requisicoes[0]->getUri());
        self::assertSame('PUT', $this->fake->requisicoes[1]->getMethod());
        self::assertSame('123', $atualizado['ie']);
    }

    public function testConfigurarWebhooksEnviaSomenteCamposInformados(): void
    {
        $this->fake->enviarNova($this->respostaJson('{"webhookUrl":"https://erp.test/hook"}'));

        $this->gestao->configurarWebhooks(webhookUrl: 'https://erp.test/hook');

        $corpo = (string) $this->fake->requisicoes[0]->getBody();
        self::assertStringContainsString('webhookUrl', $corpo);
        self::assertStringNotContainsString('webhookSecret', $corpo);
    }

    // ------------------------------------------------- notas recebidas (DFe)

    public function testNotasRecebidasComESemNsu(): void
    {
        $this->fake->enviarNova($this->respostaJson('[]'));
        $this->gestao->notasRecebidas();

        $this->fake->enviarNova($this->respostaJson('[]'));
        $this->gestao->notasRecebidas('NSU 42');

        self::assertSame('http://api.test/v1/notas-recebidas', (string) $this->fake->requisicoes[0]->getUri());
        self::assertSame('http://api.test/v1/notas-recebidas?nsu=NSU%2042', (string) $this->fake->requisicoes[1]->getUri());
    }

    public function testXmlCompletoNotaRecebidaPedeXmlEDevolveCorpo(): void
    {
        $this->fake->enviarNova(new Response(200, ['Content-Type' => 'application/xml'], '<nfeProc/>'));

        $xml = $this->gestao->xmlCompletoNotaRecebida('doc-9');

        $req = $this->fake->requisicoes[0];
        self::assertStringEndsWith('/v1/notas-recebidas/doc-9/xml-completo', (string) $req->getUri());
        self::assertStringContainsString('application/xml', $req->getHeaderLine('Accept'));
        self::assertSame('<nfeProc/>', $xml);
    }

    public function testManifestarEnviaTipoEJustificativa(): void
    {
        $this->fake->enviarNova($this->respostaJson('{"eventoId":"ev-1"}'));

        $dados = $this->gestao->manifestar('doc-9', TipoManifestacao::CienciaOperacao);

        self::assertSame('POST', $this->fake->requisicoes[0]->getMethod());
        self::assertStringEndsWith('/v1/notas-recebidas/doc-9/manifestacao', (string) $this->fake->requisicoes[0]->getUri());
        self::assertNotSame('', $this->fake->requisicoes[0]->getHeaderLine('Idempotency-Key'));
        self::assertSame('ev-1', $dados['eventoId']);
    }
}
