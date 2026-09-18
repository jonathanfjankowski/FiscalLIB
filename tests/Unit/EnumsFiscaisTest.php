<?php

declare(strict_types=1);

namespace FiscalLib\Tests\Unit;

use FiscalLib\Common\Enums\CstIcms;
use FiscalLib\Common\Enums\CstIpi;
use FiscalLib\Common\Enums\CstPisCofins;
use FiscalLib\Common\Enums\Csosn;
use PHPUnit\Framework\TestCase;

/**
 * Contrato dos enums de CST/CSOSN — fonte única agora vive nas cases; estas
 * listas DEVEM espelhar o ValidadorImpostosV2 da FiscalAPI. Caso novo ou
 * removido no contrato = atualizar aqui e lá (com testes nos dois lados).
 */
final class EnumsFiscaisTest extends TestCase
{
    public function testCstIcmsEspelhaOContrato(): void
    {
        self::assertSame(
            ['00', '10', '20', '40', '41', '51', '60', '70', '90'],
            array_map(static fn (CstIcms $c): string => $c->value, CstIcms::cases())
        );
    }

    public function testCsosnEspelhaOContrato(): void
    {
        self::assertSame(
            ['101', '102', '103', '201', '202', '203', '300', '400', '500', '900'],
            array_map(static fn (Csosn $c): string => $c->value, Csosn::cases())
        );
    }

    public function testCstIpiGruposTributadoVsNaoTributado(): void
    {
        $tributados = array_filter(CstIpi::cases(), static fn (CstIpi $c): bool => $c->tributado());

        self::assertSame(
            ['00', '49', '50', '99'],
            array_values(array_map(static fn (CstIpi $c): string => $c->value, $tributados))
        );
        self::assertSame(
            ['01', '02', '03', '04', '05', '51'],
            array_values(array_map(
                static fn (CstIpi $c): string => $c->value,
                array_filter(CstIpi::cases(), static fn (CstIpi $c): bool => ! $c->tributado())
            ))
        );
    }

    public function testCstPisCofinsGrupos(): void
    {
        // 03 (monofásica por quantidade) não existe no contrato
        self::assertNotContains('03', array_map(static fn (CstPisCofins $c): string => $c->value, CstPisCofins::cases()));

        $exigemAliquota = array_filter(CstPisCofins::cases(), static fn (CstPisCofins $c): bool => $c->exigeAliquota());
        self::assertSame(['01', '02'], array_values(array_map(static fn (CstPisCofins $c): string => $c->value, $exigemAliquota)));

        $opcionais = array_filter(CstPisCofins::cases(), static fn (CstPisCofins $c): bool => $c->admiteAliquotaOpcional());
        self::assertSame(['99'], array_values(array_map(static fn (CstPisCofins $c): string => $c->value, $opcionais)));
    }
}
