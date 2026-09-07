<?php

declare(strict_types=1);

namespace FiscalLib\Tests\Unit;

use FiscalLib\Common\ArredondadorBancario;
use PHPUnit\Framework\TestCase;

final class ArredondadorBancarioTest extends TestCase
{
    /** @dataProvider valores */
    public function testArredondamentoBancario(string $entrada, int $casas, string $esperado): void
    {
        self::assertSame($esperado, ArredondadorBancario::arredondar($entrada, $casas));
    }

    public static function valores(): iterable
    {
        // Exatamente .5 → dígito par mais próximo (casos extremos da spec V004)
        yield 'meio para baixo par' => ['2.345', 2, '2.34'];
        yield 'meio para cima par' => ['2.355', 2, '2.36'];
        yield '0.005 zero' => ['0.005', 2, '0.00'];
        yield '1.015' => ['1.015', 2, '1.02'];
        yield '1.005' => ['1.005', 2, '1.00'];
        yield 'truncamento simples' => ['100.004', 2, '100.00'];
        yield 'subida simples' => ['100.006', 2, '100.01'];
        yield 'quatro casas' => ['0.12345', 4, '0.1234'];
        yield 'quatro casas para cima' => ['0.12346', 4, '0.1235'];
        yield 'negativo meio' => ['-2.345', 2, '-2.34'];
        yield 'negativo subida' => ['-2.355', 2, '-2.36'];
        yield 'zero casas meio' => ['2.5', 0, '2'];
        yield 'zero casas meio par' => ['3.5', 0, '4'];
        yield 'inteiro' => ['18', 2, '18.00'];
    }
}
