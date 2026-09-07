<?php

declare(strict_types=1);

namespace FiscalLib\Servicos;

use FiscalLib\Config\FiscalConfig;
use FiscalLib\Contracts\EmissorInterface;
use FiscalLib\Documento\ResultadoEmissao;
use FiscalLib\Exceptions\ApiIndisponivelException;

/**
 * Polling até estado terminal (AUTORIZADA, REJEITADA, DENEGADA, CANCELADA,
 * ERRO_INTERNO). CONTINGENCIA e CANCELAMENTO_PENDENTE são transitórios —
 * a API já faz o retry sozinha; continue consultando.
 *
 * Backoff padrão: 2s → 5s → 10s → 20s → 30s → 60s (teto), timeout total configurável.
 * O intervalo de espera é injetável para testes.
 */
final class AguardadorTerminal
{
    /** @var callable(int $segundos): void */
    private $dormir;

    /**
     * @param list<int>|null $intervalos
     */
    public function __construct(
        private readonly EmissorInterface $emissor,
        private readonly ?array $intervalos = null,
        private readonly ?int $timeoutTotalSegundos = null,
        ?callable $dormir = null,
    ) {
        $this->dormir = $dormir ?? static function (int $segundos): void {
            \sleep($segundos);
        };
    }

    public static function daConfig(EmissorInterface $emissor, FiscalConfig $config, ?callable $dormir = null): self
    {
        return new self($emissor, $config->intervalosPolling, $config->timeoutTotalPollingSegundos, $dormir);
    }

    /**
     * @throws ApiIndisponivelException se o timeout total for atingido sem estado terminal.
     */
    public function aguardar(string $documentoId): ResultadoEmissao
    {
        $intervalos = $this->intervalos ?? FiscalConfig::INTERVALOS_POLLING_PADRAO;
        $timeout = $this->timeoutTotalSegundos ?? FiscalConfig::TIMEOUT_TOTAL_POLLING_PADRAO;

        $inicio = microtime(true);
        $indice = 0;
        $resultado = null;

        while (true) {
            ($this->dormir)($intervalos[min($indice, count($intervalos) - 1)]);
            $indice++;

            $resultado = $this->emissor->consultar($documentoId);
            if ($resultado->isTerminal()) {
                return $resultado;
            }

            if ((microtime(true) - $inicio) >= $timeout) {
                throw ApiIndisponivelException::rede(
                    "Timeout de {$timeout}s aguardando estado terminal do documento {$documentoId} " .
                    "(último status: {$resultado->status}). Continue consultando depois."
                );
            }
        }
    }
}
