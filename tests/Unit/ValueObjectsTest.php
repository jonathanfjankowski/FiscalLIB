<?php

declare(strict_types=1);

namespace FiscalLib\Tests\Unit;

use FiscalLib\Common\ValueObjects\ChaveAcesso;
use FiscalLib\Common\ValueObjects\Cnpj;
use FiscalLib\Common\ValueObjects\CodigoCfop;
use FiscalLib\Common\ValueObjects\CodigoMunibge;
use FiscalLib\Common\ValueObjects\CodigoNbs;
use FiscalLib\Common\ValueObjects\Cpf;
use FiscalLib\Exceptions\InvalidValueException;
use PHPUnit\Framework\TestCase;

final class ValueObjectsTest extends TestCase
{
    public function testCnpjValido(): void
    {
        self::assertSame('11444777000161', Cnpj::criar('11.444.777/0001-61')->valor());
    }

    public function testCnpjDvInvalido(): void
    {
        $this->expectException(InvalidValueException::class);
        Cnpj::criar('11444777000162');
    }

    public function testCnpjAlfanumericoAceito(): void
    {
        // NT 009/2026 — charset [0-9A-Z], 14 posições (sem DV clássico)
        $cnpj = Cnpj::criar('12ABC34501DE35');

        self::assertTrue($cnpj->isAlfanumerico());
        self::assertSame('12ABC34501DE35', $cnpj->valor());
    }

    public function testCnpjAlfanumericoInvalido(): void
    {
        $this->expectException(InvalidValueException::class);
        Cnpj::criar('12abc34501de3#');
    }

    public function testCpfValidoEInvalido(): void
    {
        self::assertSame('52998224725', Cpf::criar('529.982.247-25')->valor());

        $this->expectException(InvalidValueException::class);
        Cpf::criar('52998224726');
    }

    public function testChaveAcesso44ComDvValido(): void
    {
        $corpo = '4126091234567800019955001000000042101234567'; // 43 posições
        self::assertSame(43, strlen($corpo));

        $chave = $corpo . ChaveAcesso::dvModulo11($corpo);

        self::assertSame(44, strlen($chave));
        self::assertSame($chave, ChaveAcesso::criar($chave)->valor());
        self::assertFalse(ChaveAcesso::criar($chave)->isNfse());
    }

    public function testChaveAcesso50Nfse(): void
    {
        $chave = 'NFS' . '2609' . str_repeat('0', 43); // 3 + 4 + 43 = 50 posições
        self::assertTrue(ChaveAcesso::valido($chave));
        self::assertTrue(ChaveAcesso::criar($chave)->isNfse());

        // Prefixo errado / tamanho errado → inválida
        self::assertFalse(ChaveAcesso::valido('NFE' . str_repeat('0', 47)));
        self::assertFalse(ChaveAcesso::valido('NFS' . str_repeat('0', 46)));
    }

    public function testChaveAcessoDvInvalido(): void
    {
        $corpo = '4126091234567800019955001000000042101234567';
        $dv = ChaveAcesso::dvModulo11($corpo);
        $dvErrado = $dv === 8 ? 7 : 8;

        $this->expectException(InvalidValueException::class);
        ChaveAcesso::criar($corpo . $dvErrado);
    }

    public function testCodigoMunicipioENbs(): void
    {
        self::assertSame('4106902', CodigoMunibge::criar('4106902')->valor());
        self::assertSame('112011000', CodigoNbs::criar('1.12.01.10.00')->valor());

        $this->expectException(InvalidValueException::class);
        CodigoNbs::criar('1120110');
    }

    public function testCfopHelpers(): void
    {
        self::assertTrue(CodigoCfop::criar('6102')->isSaida());
        self::assertTrue(CodigoCfop::criar('2102')->isEntrada());
        self::assertTrue(CodigoCfop::criar('7102')->isExterior());
        self::assertFalse(CodigoCfop::criar('5102')->isEntrada());
    }
}
