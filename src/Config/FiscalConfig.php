<?php

declare(strict_types=1);

namespace FiscalLib\Config;

use FiscalLib\Common\Enums\Ambiente;

/**
 * Configuração da lib. Apenas `baseUrl`/`apiKey`/`ambiente` interessam ao
 * adaptador FiscalAPI; implementações alternativas de EmissorInterface podem
 * ignorar o que não precisarem.
 */
final class FiscalConfig
{
    /** Intervalos de polling (segundos) até estado terminal. */
    public const INTERVALOS_POLLING_PADRAO = [2, 5, 10, 20, 30, 60];
    public const TIMEOUT_TOTAL_POLLING_PADRAO = 300;

    public function __construct(
        public readonly string $baseUrl,
        public readonly string $apiKey,
        public readonly ?Ambiente $ambiente = null,       // X-Fiscal-Ambiente opcional
        public readonly int $timeoutHttpSegundos = 30,
        public readonly int $tentativasRede = 3,          // 429/5xx/timeout com a MESMA idempotency key
        public readonly array $intervalosPolling = self::INTERVALOS_POLLING_PADRAO,
        public readonly int $timeoutTotalPollingSegundos = self::TIMEOUT_TOTAL_POLLING_PADRAO,
        public readonly string $versaoLib = '0.1.0',
    ) {
    }

    public function userAgent(): string
    {
        return "fiscal-lib-php/{$this->versaoLib}";
    }

    public static function criar(string $baseUrl, string $apiKey, ?Ambiente $ambiente = null): self
    {
        return new self(rtrim($baseUrl, '/'), $apiKey, $ambiente);
    }
}
