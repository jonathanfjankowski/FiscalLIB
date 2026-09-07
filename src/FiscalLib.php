<?php

declare(strict_types=1);

namespace FiscalLib;

use FiscalLib\Adapters\FiscalApi\EmissorFiscalApi;
use FiscalLib\Adapters\FiscalApi\GestaoFiscalApi;
use FiscalLib\Common\Enums\StatusDocumento;
use FiscalLib\Config\FiscalConfig;
use FiscalLib\Contracts\EmissorInterface;
use FiscalLib\Contracts\TaxEngineInterface;
use FiscalLib\Documento\ResultadoEmissao;
use FiscalLib\Servicos\AguardadorTerminal;
use FiscalLib\Servicos\ServicoDocumentos;
use FiscalLib\Servicos\ServicoEventos;
use FiscalLib\Servicos\ServicoNfce;
use FiscalLib\Servicos\ServicoNfe;
use FiscalLib\Servicos\ServicoNfse;
use FiscalLib\Tax\TaxEngine;

/**
 * Entrypoint da lib. O núcleo (TaxEngine + builders + modelo Documento)
 * não conhece HTTP; o transporte entra por EmissorInterface.
 *
 *   // FiscalAPI (embutido):
 *   $lib = FiscalLib::comFiscalApi(FiscalConfig::criar('https://api...', 'fk_test_...'));
 *   $lib->nfe()->emitir($documento);
 *
 *   // Outro emissor (sua implementação):
 *   $lib = new FiscalLib(new MeuEmissorDireto());
 *
 *   // Gestão (certificados/api-keys/tenant/DFe) — só disponível no adaptador FiscalAPI:
 *   $lib->gestao()?->statusServico(ModeloDocumento::Nfe, Ambiente::Homologacao);
 */
final class FiscalLib
{
    private readonly TaxEngineInterface $engine;
    private readonly ?FiscalConfig $config;
    private ?ServicoNfe $servicoNfe = null;
    private ?ServicoNfce $servicoNfce = null;
    private ?ServicoNfse $servicoNfse = null;
    private ?ServicoEventos $servicoEventos = null;
    private ?AguardadorTerminal $aguardador = null;

    public function __construct(
        private readonly EmissorInterface $emissor,
        ?FiscalConfig $config = null,
        ?TaxEngineInterface $engine = null,
    ) {
        $this->config = $config;
        $this->engine = $engine ?? new TaxEngine();
    }

    public static function comFiscalApi(FiscalConfig $config, ?\Psr\Http\Client\ClientInterface $http = null): self
    {
        return new self(new EmissorFiscalApi($config, $http), $config);
    }

    public function emissor(): EmissorInterface
    {
        return $this->emissor;
    }

    public function taxEngine(): TaxEngineInterface
    {
        return $this->engine;
    }

    public function nfe(): ServicoNfe
    {
        return $this->servicoNfe ??= new ServicoNfe($this->emissor, $this->aguardador());
    }

    public function nfce(): ServicoNfce
    {
        return $this->servicoNfce ??= new ServicoNfce($this->emissor, $this->aguardador());
    }

    public function nfse(): ServicoNfse
    {
        return $this->servicoNfse ??= new ServicoNfse($this->emissor, $this->aguardador());
    }

    public function eventos(): ServicoEventos
    {
        return $this->servicoEventos ??= new ServicoEventos($this->emissor);
    }

    /**
     * Consulta até estado terminal (usa os intervalos da FiscalConfig).
     * Utilitário para quem guardou apenas o id do aceite.
     */
    public function aguardarTerminal(string $documentoId): ResultadoEmissao
    {
        return $this->aguardador()->aguardar($documentoId);
    }

    /**
     * Gestão da FiscalAPI (certificados, api-keys, tenant, status-serviço,
     * notas recebidas). Null quando o emissor não é o adaptador FiscalAPI.
     */
    public function gestao(): ?GestaoFiscalApi
    {
        if ($this->emissor instanceof EmissorFiscalApi) {
            return new GestaoFiscalApi($this->config ?? $this->emissor->config());
        }

        return null;
    }

    /** Status em texto: "AUTORIZADA" etc. Útil para logs. */
    public static function statusNome(?StatusDocumento $status): string
    {
        return $status->value ?? 'DESCONHECIDO';
    }

    private function aguardador(): AguardadorTerminal
    {
        $this->aguardador ??= $this->config === null
            ? new AguardadorTerminal($this->emissor)
            : AguardadorTerminal::daConfig($this->emissor, $this->config);

        return $this->aguardador;
    }
}
