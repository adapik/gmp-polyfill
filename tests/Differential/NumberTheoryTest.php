<?php

declare(strict_types=1);

namespace Adapik\Polyfill\Gmp\Tests\Differential;

use Adapik\Polyfill\Gmp\Calculator;
use PHPUnit\Framework\Attributes\DataProvider;

final class NumberTheoryTest extends DifferentialTestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function pairs(): iterable
    {
        $values = ['0', '1', '-1', '6', '-6', '35', '-35', '17', '48', '123456789012345678901234567890', '-123456789012345678901234567890'];

        foreach ($values as $a) {
            foreach ($values as $b) {
                yield "$a,$b" => [$a, $b];
            }
        }
    }

    #[DataProvider('pairs')]
    public function testGcdLcm(string $a, string $b): void
    {
        self::assertSame(\gmp_strval(\gmp_gcd($a, $b)), Calculator::gcd($a, $b)->toBase(10), "gcd($a,$b)");
        self::assertSame(\gmp_strval(\gmp_lcm($a, $b)), Calculator::lcm($a, $b)->toBase(10), "lcm($a,$b)");
    }

    #[DataProvider('pairs')]
    public function testGcdext(string $a, string $b): void
    {
        // Native gmp_gcdext() returns an associative array keyed 'g', 's', 't' (not 0/1/2).
        $native = \gmp_gcdext($a, $b);
        [$g, $s, $t] = Calculator::gcdext($a, $b);

        self::assertSame(\gmp_strval(self::asGmp($native['g'])), $g->toBase(10), "gcdext($a,$b).g");

        // s and t are not unique for GMP either; verify the Bezout identity instead of exact values.
        $aBig = \gmp_init($a);
        $bBig = \gmp_init($b);
        $lhs = \gmp_add(\gmp_mul($aBig, $s->toBase(10)), \gmp_mul($bBig, $t->toBase(10)));
        self::assertSame(\gmp_strval($g->toBase(10)), \gmp_strval($lhs), "bezout($a,$b)");
    }

    public function testInvert(): void
    {
        $cases = [['4', '8'], ['3', '8'], ['-3', '8'], ['0', '5'], ['5', '1'], ['123456789', '1000000007']];

        foreach ($cases as [$a, $b]) {
            $native = \gmp_invert($a, $b);
            $actual = Calculator::invert($a, $b);

            if ($native === false) {
                self::assertFalse($actual, "invert($a,$b) expected false");
            } else {
                self::assertNotFalse($actual);
                self::assertSame(\gmp_strval($native), $actual->toBase(10), "invert($a,$b)");
            }
        }
    }

    public function testInvertByZeroThrows(): void
    {
        $this->expectException(\DivisionByZeroError::class);
        Calculator::invert('5', '0');
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function jacobiPairs(): iterable
    {
        foreach ([-10, -7, -3, -1, 0, 1, 2, 3, 5, 7, 10, 100] as $a) {
            foreach ([1, 3, 5, 7, 9, 11, 15, 101] as $b) {
                yield "jacobi($a,$b)" => [(string) $a, (string) $b];
            }
        }
    }

    #[DataProvider('jacobiPairs')]
    public function testJacobi(string $a, string $b): void
    {
        self::assertSame(\gmp_jacobi($a, $b), Calculator::jacobi($a, $b), "jacobi($a,$b)");
    }

    #[DataProvider('jacobiPairs')]
    public function testLegendreWhenModulusPositive(string $a, string $b): void
    {
        self::assertSame(\gmp_legendre($a, $b), Calculator::legendre($a, $b), "legendre($a,$b)");
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function kroneckerPairs(): iterable
    {
        foreach ([-10, -7, -3, -2, -1, 0, 1, 2, 3, 5, 7, 10, 100] as $a) {
            foreach ([-15, -8, -3, -1, 0, 1, 2, 3, 4, 8, 15, 101] as $b) {
                yield "kronecker($a,$b)" => [(string) $a, (string) $b];
            }
        }
    }

    #[DataProvider('kroneckerPairs')]
    public function testKronecker(string $a, string $b): void
    {
        self::assertSame(\gmp_kronecker($a, $b), Calculator::kronecker($a, $b), "kronecker($a,$b)");
    }

    public function testNextprime(): void
    {
        foreach (['-5', '0', '1', '2', '3', '4', '100', '997', '1000', '7919', (string) \PHP_INT_MAX] as $value) {
            self::assertSame(\gmp_strval(\gmp_nextprime($value)), Calculator::nextprime($value)->toBase(10), "nextprime($value)");
        }
    }

    public function testProbPrime(): void
    {
        foreach (['-5', '0', '1', '2', '3', '4', '17', '100', '997', '1000', '7919', '561'] as $value) {
            $native = \gmp_prob_prime($value);
            $actual = Calculator::probPrime($value);

            // Definite (2) vs probable (1) is an implementation detail of the primality test;
            // only require agreement on primality, not on the certainty level.
            self::assertSame($native > 0, $actual > 0, "prob_prime($value)");
        }
    }

    public function testPerfectSquareAndPower(): void
    {
        foreach (['-27', '-8', '-4', '-1', '0', '1', '2', '4', '8', '9', '16', '26', '27', '28', '100', '123456789012345678901234567890'] as $value) {
            self::assertSame(\gmp_perfect_square($value), Calculator::perfectSquare($value), "perfect_square($value)");
            self::assertSame(\gmp_perfect_power($value), Calculator::perfectPower($value), "perfect_power($value)");
        }
    }
}
