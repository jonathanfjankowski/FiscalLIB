<?php

declare(strict_types=1);

namespace FiscalLib\Laravel;

use FiscalLib\Config\FiscalConfig;
use FiscalLib\FiscalLib;

/**
 * Integração opcional com Laravel. Em config/app.php (ou bootstrap/providers.php):
 *
 *   FiscalLib\Laravel\FiscalLibServiceProvider::class,
 *
 * config/fiscal-lib.php:
 *   return [
 *       'base_url'  => env('FISCAL_API_BASE_URL', 'http://localhost:8080'),
 *       'api_key'   => env('FISCAL_API_KEY'),
 *       'ambiente'  => env('FISCAL_AMBIENTE', 'homologacao'), // producao|homologacao
 *       'timeout'   => 30,
 *   ];
 *
 * Uso: app('fiscal-lib')->nfe()->... ou FiscalLibFacade::nfe()->...
 */
final class FiscalLibServiceProvider extends \Illuminate\Support\ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/fiscal-lib.php', 'fiscal-lib');

        $this->app->singleton('fiscal-lib', static function ($app): FiscalLib {
            $config = $app['config']->get('fiscal-lib');
            $ambiente = ($config['ambiente'] ?? null) === 'producao'
                ? \FiscalLib\Common\Enums\Ambiente::Producao
                : \FiscalLib\Common\Enums\Ambiente::Homologacao;

            return FiscalLib::comFiscalApi(new FiscalConfig(
                baseUrl: rtrim((string) $config['base_url'], '/'),
                apiKey: (string) $config['api_key'],
                ambiente: $ambiente,
                timeoutHttpSegundos: (int) ($config['timeout'] ?? 30),
            ));
        });

        $this->app->alias('fiscal-lib', FiscalLib::class);
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../../config/fiscal-lib.php' => config_path('fiscal-lib.php'),
        ], 'fiscal-lib-config');
    }
}
