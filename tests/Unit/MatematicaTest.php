<?php

declare(strict_types=1);

namespace FiscalLib\Tests\Unit;

use FiscalLib\Common\Matematica;
use PHPUnit\Framework\TestCase;

final class MatematicaTest extends TestCase
{
    // ------------------------------------------------------------- normalizar

    /** @dataProvider entradasValidas */
    public function testNormalizarEntradasValidas(string|int|float $entrada, string $esperado): void
    {
        self::assertSame($esperado, Matematica::normalizar($entrada));
    }

    public static function entradasValidas(): iterable
    {
        yield 'string decimal' => ['18.00', '18.00'];
        yield 'string com vírgula decimal' => ['12,5', '12.5'];
        yield 'string com espaços' => [' 18.00 ', '18.00'];
        yield 'negativo' => ['-7.35', '-7.35'];
        yield 'inteiro' => [100, '100'];
        yield 'float trivial' => [100.0, '100'];
        yield 'float com centavos triviais' => [0.1, '0.1'];
    }

    /** @dataProvider entradasInvalidas */
    public function testNormalizarRejeitaEntradasInvalidas(string $entrada): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Matematica::normalizar($entrada);
    }

    public static function entradasInvalidas(): iterable
    {
        yield 'vazia' => [''];
        yield 'texto' => ['abc'];
        yield 'notação científica' => ['1e3'];
        yield 'sem dígito antes do ponto' => ['.5'];
        yield 'sinal explícito' => ['+5'];
        yield 'NaN' => ['NaN'];
        yield 'infinito' => ['INF'];
        yield 'float gigante em notação científica' => ['1.0E+300'];
    }

    // ------------------------------------------------------------ percentualDe

    /**
     * A fórmula fiscal base × alíquota / 100 deve arredondar bancário (V004),
     * nunca truncar — bcmath sozinho trunca e subavalia até 1 centavo.
     *
     * @dataProvider casosPercentualDe
     */
    public function testPercentualDeArredondaBancario(string $valor, string $aliquota, int $casas, string $esperado): void
    {
        self::assertSame($esperado, Matematica::percentualDe($valor, $aliquota, $casas));
    }

    public static function casosPercentualDe(): iterable
    {
        // 100 × 12,3456% = 12,3456 → bancário sobe para 12,35 (truncamento daria 12,34)
        yield 'alíquota 4 casas sobe' => ['100', '12.3456', 2, '12.35'];
        // 333,33 × 18% = 59,9994 → 60,00 (truncamento daria 59,99)
        yield 'base com fração sobe' => ['333.33', '18', 2, '60.00'];
        // 10 × 0,05% = 0,005 → exatamente meio → dígito par (0)
        yield 'meio para par baixo' => ['10', '0.05', 2, '0.00'];
        // 10 × 0,15% = 0,015 → exatamente meio → dígito par (2)
        yield 'meio para par cima' => ['10', '0.15', 2, '0.02'];
        yield 'sem fração é exato' => ['1000', '18', 2, '180.00'];
        yield 'quatro casas' => ['100', '12.34567', 4, '12.3457'];
        yield 'negativo' => ['-100', '12.3456', 2, '-12.35'];
        yield 'alíquota zero' => ['100', '0', 2, '0.00'];
    }

    // ------------------------------------------------- operações arredondadas

    public function testSomarArredondaBancario(): void
    {
        // 2,469 → 2,47 (bcmath sozinho daria 2,46)
        self::assertSame('2.47', Matematica::somar('1.2345', '1.2345', 2));
    }

    public function testSubtrairArredondaBancario(): void
    {
        // 0,005 exato → dígito par: 0,00
        self::assertSame('0.00', Matematica::subtrair('1.005', '1', 2));
        // 0,015 exato → dígito par: 0,02
        self::assertSame('0.02', Matematica::subtrair('1.015', '1', 2));
    }

    public function testMultiplicarArredondaBancario(): void
    {
        self::assertSame('1.12', Matematica::multiplicar('1.115', '1', 2));
        self::assertSame('25.44', Matematica::multiplicar('2.543', '10.005', 2));
    }

    public function testDividirArredondaBancario(): void
    {
        // 2/3 = 0,6667 (bcmath sozinho daria 0,66)
        self::assertSame('0.67', Matematica::dividir('2', '3', 2));
        self::assertSame('3.33', Matematica::dividir('10', '3', 2));
    }

    public function testDividirPorZeroLancaExcecao(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Divisão por zero.');
        Matematica::dividir('5', '0');
    }

    public function testDividirPorQuaseZeroLancaExcecao(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Matematica::dividir('5', '0.0000000000001');
    }

    public function testEscalarArredondaEntradaDireta(): void
    {
        self::assertSame('12.35', Matematica::escalar('12.3456', 2));
        self::assertSame('12.3457', Matematica::escalar('12.34567', 4));
    }

    // --------------------------------------------------------- igual/comparar

    public function testIgualComToleranciaPadraoDeUmCentavo(): void
    {
        self::assertTrue(Matematica::igual('1.005', '1.01'));
        self::assertTrue(Matematica::igual('100.00', '100.00'));
        self::assertFalse(Matematica::igual('1.00', '1.02'));
    }

    public function testIgualComToleranciaCustomizada(): void
    {
        self::assertTrue(Matematica::igual('10.00', '10.04', '0.05'));
        self::assertFalse(Matematica::igual('10.00', '10.06', '0.05'));
    }

    public function testComparar(): void
    {
        self::assertSame(1, Matematica::comparar('2.35', '2.34', 2));
        self::assertSame(-1, Matematica::comparar('2.33', '2.34', 2));
        self::assertSame(0, Matematica::comparar('2.345', '2.346', 2));
    }

    // -------------------------------------------------------------- utilitários

    public function testAbs(): void
    {
        self::assertSame('12.34', Matematica::abs('-12.34'));
        self::assertSame('12.34', Matematica::abs('12.34'));
    }

    public function testZero(): void
    {
        self::assertSame('0.00', Matematica::zero());
    }
}
