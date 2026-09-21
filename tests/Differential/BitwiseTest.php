<?php

declare(strict_types=1);

namespace Adapik\Polyfill\Gmp\Tests\Differential;

use Adapik\Polyfill\Gmp\Calculator;
use PHPUnit\Framework\Attributes\DataProvider;

final class BitwiseTest extends DifferentialTestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function pairs(): iterable
    {
        $values = ['0', '1', '-1', '5', '-5', '255', '-255', '1024', '-1024', '123456789012345678901234567890', '-123456789012345678901234567890'];

        foreach ($values as $a) {
            foreach ($values as $b) {
                yield "$a,$b" => [$a, $b];
            }
        }
    }

    #[DataProvider('pairs')]
    public function testAndOrXor(string $a, string $b): void
    {
        self::assertSame(\gmp_strval(\gmp_and($a, $b)), Calculator::bitAnd($a, $b)->toBase(10), "and($a,$b)");
        self::assertSame(\gmp_strval(\gmp_or($a, $b)), Calculator::bitOr($a, $b)->toBase(10), "or($a,$b)");
        self::assertSame(\gmp_strval(\gmp_xor($a, $b)), Calculator::bitXor($a, $b)->toBase(10), "xor($a,$b)");
        self::assertSame(\gmp_hamdist($a, $b), Calculator::hamdist($a, $b), "hamdist($a,$b)");
    }

    public function testCom(): void
    {
        foreach (['0', '1', '-1', '5', '-5', '255', '-255'] as $value) {
            self::assertSame(\gmp_strval(\gmp_com($value)), Calculator::com($value)->toBase(10));
        }
    }

    public function testPopcount(): void
    {
        foreach (['0', '1', '-1', '5', '-5', '255', '-255', '123456789012345678901234567890'] as $value) {
            self::assertSame(\gmp_popcount(\gmp_init($value)), Calculator::popcount($value), "popcount($value)");
        }
    }

    public function testTestbit(): void
    {
        foreach (['0', '1', '-1', '5', '-5', '255', '-255'] as $value) {
            for ($bit = 0; $bit < 16; $bit++) {
                self::assertSame(
                    \gmp_testbit(\gmp_init($value), $bit),
                    Calculator::testbit($value, $bit),
                    "testbit($value,$bit)",
                );
            }
        }
    }

    public function testScan0AndScan1(): void
    {
        foreach (['0', '1', '-1', '4', '-4', '255', '-255', '256', '-256'] as $value) {
            for ($start = 0; $start < 10; $start++) {
                self::assertSame(
                    \gmp_scan0(\gmp_init($value), $start),
                    Calculator::scan0($value, $start),
                    "scan0($value,$start)",
                );
                self::assertSame(
                    \gmp_scan1(\gmp_init($value), $start),
                    Calculator::scan1($value, $start),
                    "scan1($value,$start)",
                );
            }
        }
    }

    public function testSetbitAndClrbitMutateInPlace(): void
    {
        foreach ([0, 5, 20] as $index) {
            $native = \gmp_init(4);
            \gmp_setbit($native, $index);
            $polyfill = Calculator::withBit(Calculator::init(4), $index, true);
            self::assertSame(\gmp_strval($native), $polyfill->toBase(10), "setbit index=$index");

            \gmp_clrbit($native, $index);
            $polyfill = Calculator::withBit($polyfill, $index, false);
            self::assertSame(\gmp_strval($native), $polyfill->toBase(10), "clrbit index=$index");
        }
    }
}
