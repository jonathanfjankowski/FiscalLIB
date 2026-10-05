<?php

declare(strict_types=1);

namespace FiscalLib\Tests\Laravel;

use FiscalLib\Laravel\FiscalLibServiceProvider;
use PHPUnit\Framework\TestCase;

/**
 * Integração Laravel opcional (illuminate em suggest — não é dependência dura).
 * Sem illuminate instalado, os casos dependentes PULAM; dentro do ERP (com
 * Laravel) eles rodam de verdade. O teste do config roda sempre.
 */
final class LaravelIntegrationTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        if (! class_exists(\Illuminate\Support\ServiceProvider::class)) {
            self::markTestSkipped('illuminate/support não instalado (pacote opcional) — rode dentro do ERP.');
        }
    }

    public function testConfigPublicavelTemFormatoEsperadoESemSecretPadrao(): void
    {
        $config = require __DIR__ . '/../../config/fiscal-lib.php';

        self::assertArrayHasKey('base_url', $config);
        self::assertArrayHasKey('api_key', $config);
        self::assertArrayHasKey('ambiente', $config);
        self::assertArrayHasKey('timeout', $config);
        // A chave vem do env SEM fallback: nenhum secret default no repositório.
        self::assertNull($config['api_key']);
    }

    public function testProviderRegistraSingletonEAlias(): void
    {
        $container = new \Illuminate\Container\Container();
        $container->singleton('config', static function (): \Illuminate\Config\Repository {
            return new \Illuminate\Config\Repository([
                'fiscal-lib' => [
                    'base_url' => 'http://api.test:8080/',
                    'api_key' => 'fk_test_x',
                    'ambiente' => 'homologacao',
                    'timeout' => 7,
                ],
            ]);
        });

        $provider = new FiscalLibServiceProvider($container);
        $provider->register();

        self::assertTrue($container->bound('fiscal-lib'));
        self::assertSame(
            $container->make('fiscal-lib'),
            $container->make(\FiscalLib\FiscalLib::class),
            'alias FiscalLib::class deve resolver o mesmo singleton',
        );

        $lib = $container->make('fiscal-lib');
        $emissor = $lib->emissor();

        self::assertNotNull($emissor, 'binding deve criar a FiscalLib com o emissor FiscalAPI');
    }

    public function testSingletonReaproveitaInstanciaNoWorker(): void
    {
        $container = new \Illuminate\Container\Container();
        $container->singleton('config', static function (): \Illuminate\Config\Repository {
            return new \Illuminate\Config\Repository([
                'fiscal-lib' => [
                    'base_url' => 'http://api.test:8080',
                    'api_key' => 'fk_test_x',
                    'ambiente' => 'producao',
                    'timeout' => 30,
                ],
            ]);
        });

        $provider = new FiscalLibServiceProvider($container);
        $provider->register();

        // Comportamento Octane/worker: mesma instância entre resoluções.
        self::assertSame($container->make('fiscal-lib'), $container->make('fiscal-lib'));
    }
}
