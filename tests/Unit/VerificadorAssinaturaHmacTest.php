<?php

declare(strict_types=1);

namespace FiscalLib\Tests\Unit;

use FiscalLib\Exceptions\FiscalLibException;
use FiscalLib\Webhook\VerificadorAssinaturaHmac;
use PHPUnit\Framework\TestCase;

final class VerificadorAssinaturaHmacTest extends TestCase
{
    private const SEGREDO = 'um-segredo-de-ao-menos-16-chars';

    private string $corpo = '{"evento":"documento.autorizado","id":"doc-1"}';

    public function testAssinaturaValidaPassa(): void
    {
        VerificadorAssinaturaHmac::validar(
            $this->corpo,
            VerificadorAssinaturaHmac::header($this->corpo, self::SEGREDO, 1_700_000_000),
            self::SEGREDO,
            agora: 1_700_000_100,
        );

        self::addToAssertionCount(1); // não lançou
    }

    public function testAssinaturaDivergenteFalha(): void
    {
        $this->expectException(FiscalLibException::class);
        $this->expectExceptionMessage('inválida');

        VerificadorAssinaturaHmac::validar(
            $this->corpo . ' ',
            VerificadorAssinaturaHmac::header($this->corpo, self::SEGREDO, 1_700_000_000),
            self::SEGREDO,
            agora: 1_700_000_100,
        );
    }

    public function testJanelaAntiReplay(): void
    {
        $this->expectException(FiscalLibException::class);
        $this->expectExceptionMessage('anti-replay');

        VerificadorAssinaturaHmac::validar(
            $this->corpo,
            VerificadorAssinaturaHmac::header($this->corpo, self::SEGREDO, 1_700_000_000),
            self::SEGREDO,
            janelaSegundos: 300,
            agora: 1_700_000_400,
        );
    }

    public function testHeaderAusenteFalha(): void
    {
        $this->expectException(FiscalLibException::class);
        VerificadorAssinaturaHmac::validar($this->corpo, null, self::SEGREDO);
    }
}
