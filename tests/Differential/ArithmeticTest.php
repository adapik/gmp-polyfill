<?php

declare(strict_types=1);

namespace Adapik\Polyfill\Gmp\Tests\Differential;

use Adapik\Polyfill\Gmp\Calculator;
use PHPUnit\Framework\Attributes\DataProvider;

final class ArithmeticTest extends DifferentialTestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function pairs(): iterable
    {
        $values = ['0', '1', '-1', '2', '-2', '7', '-7', '8', '-8', '1000000000000000000000', '-1000000000000000000000', (string) \PHP_INT_MAX, (string) \PHP_INT_MIN];

        foreach ($values as $a) {
            foreach ($values as $b) {
                yield "$a,$b" => [$a, $b];
            }
        }
    }

    #[DataProvider('pairs')]
    public function testAddSubMul(string $a, string $b): void
    {
        self::assertSame(\gmp_strval(\gmp_add($a, $b)), Calculator::add($a, $b)->toBase(10));
        self::assertSame(\gmp_strval(\gmp_sub($a, $b)), Calculator::sub($a, $b)->toBase(10));
        self::assertSame(\gmp_strval(\gmp_mul($a, $b)), Calculator::mul($a, $b)->toBase(10));
    }

    #[DataProvider('pairs')]
    public function testDivisionFamily(string $a, string $b): void
    {
        if ($b === '0') {
            $this->expectException(\DivisionByZeroError::class);
            Calculator::divQ($a, $b);

            return;
        }

        foreach ([\GMP_ROUND_ZERO, \GMP_ROUND_PLUSINF, \GMP_ROUND_MINUSINF] as $mode) {
            self::assertSame(
                \gmp_strval(\gmp_div_q($a, $b, $mode)),
                Calculator::divQ($a, $b, $mode)->toBase(10),
                "div_q a=$a b=$b mode=$mode",
            );
            self::assertSame(
                \gmp_strval(\gmp_div_r($a, $b, $mode)),
                Calculator::divR($a, $b, $mode)->toBase(10),
                "div_r a=$a b=$b mode=$mode",
            );

            [$nativeQ, $nativeR] = \gmp_div_qr($a, $b, $mode);
            [$q, $r] = Calculator::divQR($a, $b, $mode);
            self::assertSame(\gmp_strval(self::asGmp($nativeQ)), $q->toBase(10), "div_qr.q a=$a b=$b mode=$mode");
            self::assertSame(\gmp_strval(self::asGmp($nativeR)), $r->toBase(10), "div_qr.r a=$a b=$b mode=$mode");
        }

        self::assertSame(\gmp_strval(\gmp_mod($a, $b)), Calculator::mod($a, $b)->toBase(10), "mod a=$a b=$b");
    }

    public function testDivExactWhenEvenlyDivisible(): void
    {
        foreach ([['100', '5'], ['-100', '5'], ['100', '-5'], ['-100', '-5'], ['0', '7']] as [$a, $b]) {
            self::assertSame(
                \gmp_strval(\gmp_divexact($a, $b)),
                Calculator::divexact($a, $b)->toBase(10),
                "a=$a b=$b",
            );
        }
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function powCases(): iterable
    {
        foreach (['0', '1', '-1', '2', '-2', '3', '10', '-10'] as $base) {
            foreach ([0, 1, 2, 3, 10, 63] as $exp) {
                yield "$base^$exp" => [$base, $exp];
            }
        }
    }

    #[DataProvider('powCases')]
    public function testPow(string $base, int $exp): void
    {
        self::assertSame(\gmp_strval(\gmp_pow($base, $exp)), Calculator::pow($base, $exp)->toBase(10));
    }

    public function testPowRejectsNegativeExponent(): void
    {
        try {
            $discarded = \gmp_pow('2', -1);
            self::fail('native should reject negative exponent');
        } catch (\ValueError) {
        }

        $this->expectException(\ValueError::class);
        Calculator::pow('2', -1);
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function powmCases(): iterable
    {
        yield 'small' => ['5', '3', '13'];
        yield 'zero exponent' => ['5', '0', '13'];
        yield 'negative base' => ['-5', '3', '13'];
        yield 'negative exponent' => ['5', '-1', '13'];
        yield 'rsa-like' => ['123456789', '65537', '1000000007'];
        yield 'large modulus' => ['2', '1000', '999999999999999999999989'];
    }

    #[DataProvider('powmCases')]
    public function testPowm(string $num, string $exp, string $mod): void
    {
        $expected = null;
        $expectedThrows = null;
        try {
            $expected = \gmp_strval(\gmp_powm($num, $exp, $mod));
        } catch (\Throwable $e) {
            $expectedThrows = $e::class;
        }

        if ($expectedThrows !== null) {
            $this->expectException($expectedThrows);
            Calculator::powm($num, $exp, $mod);

            return;
        }

        self::assertSame($expected, Calculator::powm($num, $exp, $mod)->toBase(10));
    }

    public function testNegAbs(): void
    {
        foreach (['0', '5', '-5', '123456789012345678901234567890', '-123456789012345678901234567890'] as $value) {
            self::assertSame(\gmp_strval(\gmp_neg($value)), Calculator::neg($value)->toBase(10));
            self::assertSame(\gmp_strval(\gmp_abs($value)), Calculator::abs($value)->toBase(10));
        }
    }

    public function testSqrtAndSqrtrem(): void
    {
        foreach (['0', '1', '2', '3', '4', '99', '100', '101', '123456789012345678901234567890'] as $value) {
            self::assertSame(\gmp_strval(\gmp_sqrt($value)), Calculator::sqrt($value)->toBase(10));

            [$nativeSqrt, $nativeRem] = \gmp_sqrtrem($value);
            [$sqrt, $rem] = Calculator::sqrtrem($value);
            self::assertSame(\gmp_strval(self::asGmp($nativeSqrt)), $sqrt->toBase(10));
            self::assertSame(\gmp_strval(self::asGmp($nativeRem)), $rem->toBase(10));
        }

        $this->expectException(\ValueError::class);
        Calculator::sqrt('-1');
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function rootCases(): iterable
    {
        foreach (['0', '1', '8', '9', '26', '27', '28', '1000', '-8', '-27', '123456789012345678901234567890'] as $value) {
            foreach ([1, 2, 3, 4, 5, 10] as $nth) {
                yield "$value root $nth" => [$value, $nth];
            }
        }
    }

    #[DataProvider('rootCases')]
    public function testRootAndRootrem(string $value, int $nth): void
    {
        $expectedThrows = null;
        try {
            $expectedRoot = \gmp_strval(\gmp_root($value, $nth));
        } catch (\Throwable $e) {
            $expectedThrows = $e::class;
        }

        if ($expectedThrows !== null) {
            $this->expectException($expectedThrows);
            Calculator::root($value, $nth);

            return;
        }

        self::assertSame($expectedRoot, Calculator::root($value, $nth)->toBase(10));

        [$nativeRoot, $nativeRem] = \gmp_rootrem($value, $nth);
        [$root, $rem] = Calculator::rootrem($value, $nth);
        self::assertSame(\gmp_strval(self::asGmp($nativeRoot)), $root->toBase(10));
        self::assertSame(\gmp_strval(self::asGmp($nativeRem)), $rem->toBase(10));
    }

    public function testFact(): void
    {
        foreach ([0, 1, 2, 5, 10, 20, 30] as $n) {
            self::assertSame(\gmp_strval(\gmp_fact($n)), Calculator::fact($n)->toBase(10));
        }
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function binomialCases(): iterable
    {
        foreach ([0, 1, 2, 5, 10, 20, -1, -5, -10] as $n) {
            foreach ([-2, -1, 0, 1, 2, 5, 10, 20] as $k) {
                yield "C($n,$k)" => [(string) $n, $k];
            }
        }
    }

    #[DataProvider('binomialCases')]
    public function testBinomial(string $n, int $k): void
    {
        if ($k < 0) {
            $this->expectException(\ValueError::class);
            Calculator::binomial($n, $k);

            return;
        }

        self::assertSame(\gmp_strval(\gmp_binomial($n, $k)), Calculator::binomial($n, $k)->toBase(10), "n=$n k=$k");
    }

    public function testFuzzArithmetic(): void
    {
        for ($i = 0; $i < 40; $i++) {
            $a = self::randomBigIntString(25);
            $b = self::randomBigIntString(25);

            self::assertSame(\gmp_strval(\gmp_add($a, $b)), Calculator::add($a, $b)->toBase(10));
            self::assertSame(\gmp_strval(\gmp_sub($a, $b)), Calculator::sub($a, $b)->toBase(10));
            self::assertSame(\gmp_strval(\gmp_mul($a, $b)), Calculator::mul($a, $b)->toBase(10));

            if ($b !== '0') {
                self::assertSame(\gmp_strval(\gmp_div_q($a, $b)), Calculator::divQ($a, $b)->toBase(10));
                self::assertSame(\gmp_strval(\gmp_div_r($a, $b)), Calculator::divR($a, $b)->toBase(10));
                self::assertSame(\gmp_strval(\gmp_mod($a, $b)), Calculator::mod($a, $b)->toBase(10));
            }
        }
    }
}
