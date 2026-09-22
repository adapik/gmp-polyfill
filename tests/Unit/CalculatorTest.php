<?php

declare(strict_types=1);

namespace Adapik\Polyfill\Gmp\Tests\Unit;

use Adapik\Polyfill\Gmp\Calculator;
use Adapik\Polyfill\Gmp\Random;
use PHPUnit\Framework\TestCase;

/**
 * Direct tests of Calculator against known-correct expected values, independent of
 * whether ext-gmp is loaded (Calculator never touches the global GMP class - see its
 * class docblock). These are regression tests with fixed expectations, not oracle
 * comparisons; see tests/Differential for exhaustive comparison against native gmp_*().
 */
final class CalculatorTest extends TestCase
{
    protected function tearDown(): void
    {
        Random::resetForTesting();
    }

    public function testInitAndStrvalAndIntval(): void
    {
        self::assertSame('123', Calculator::init('123')->toBase(10));
        self::assertSame('-123', Calculator::init('-123')->toBase(10));
        self::assertSame('26', Calculator::init('0x1A')->toBase(10));
        self::assertSame('ff', Calculator::strval(Calculator::init(255)->toBase(10), 16));
        self::assertSame(123, Calculator::intval('123'));
    }

    public function testAcceptsAGmpInstanceAsAnOperand(): void
    {
        // gmp_init() works regardless of whether ext-gmp is loaded (native, or this
        // package's own stub): Calculator must accept the resulting GMP object the same
        // way it accepts a string or int.
        $gmp = \gmp_init(21);

        self::assertSame('42', Calculator::add($gmp, $gmp)->toBase(10));
        self::assertSame('441', Calculator::mul($gmp, '21')->toBase(10));
    }

    public function testArithmetic(): void
    {
        self::assertSame('30', Calculator::add(10, 20)->toBase(10));
        self::assertSame('-10', Calculator::sub(10, 20)->toBase(10));
        self::assertSame('200', Calculator::mul(10, 20)->toBase(10));
        self::assertSame('-10', Calculator::neg(10)->toBase(10));
        self::assertSame('10', Calculator::abs(-10)->toBase(10));
    }

    public function testDivisionRoundingModes(): void
    {
        // -7 / 2 = -3.5
        self::assertSame('-3', Calculator::divQ(-7, 2, 0)->toBase(10));  // GMP_ROUND_ZERO
        self::assertSame('-3', Calculator::divQ(-7, 2, 1)->toBase(10));  // GMP_ROUND_PLUSINF
        self::assertSame('-4', Calculator::divQ(-7, 2, 2)->toBase(10));  // GMP_ROUND_MINUSINF

        self::assertSame('-1', Calculator::divR(-7, 2)->toBase(10));

        [$q, $r] = Calculator::divQR(-7, 2);
        self::assertSame('-3', $q->toBase(10));
        self::assertSame('-1', $r->toBase(10));

        self::assertSame('4', Calculator::divexact(20, 5)->toBase(10));
    }

    public function testModIsEuclideanIgnoringDivisorSign(): void
    {
        self::assertSame('1', Calculator::mod(-7, 2)->toBase(10));
        self::assertSame('1', Calculator::mod(7, -2)->toBase(10));
        self::assertSame('0', Calculator::mod(6, 3)->toBase(10));
    }

    public function testDivisionByZeroThrows(): void
    {
        $this->expectException(\DivisionByZeroError::class);
        Calculator::divQ(1, 0);
    }

    public function testPowAndPowm(): void
    {
        self::assertSame('1024', Calculator::pow(2, 10)->toBase(10));
        self::assertSame('1', Calculator::powm(2, 10, 3)->toBase(10)); // 1024 mod 3 = 1
    }

    public function testSqrtAndSqrtrem(): void
    {
        self::assertSame('4', Calculator::sqrt(20)->toBase(10));

        [$sqrt, $rem] = Calculator::sqrtrem(20);
        self::assertSame('4', $sqrt->toBase(10));
        self::assertSame('4', $rem->toBase(10));
    }

    public function testRootAndRootrem(): void
    {
        self::assertSame('3', Calculator::root(27, 3)->toBase(10));
        self::assertSame('-3', Calculator::root(-27, 3)->toBase(10));

        [$root, $rem] = Calculator::rootrem(30, 3);
        self::assertSame('3', $root->toBase(10));
        self::assertSame('3', $rem->toBase(10));
    }

    public function testFactAndBinomial(): void
    {
        self::assertSame('120', Calculator::fact(5)->toBase(10));
        self::assertSame('10', Calculator::binomial(5, 2)->toBase(10));
        // C(n, k) = 0 for negative-n reflection landing out of range.
        self::assertSame('-4', Calculator::binomial(-2, 3)->toBase(10));
    }

    public function testComparisonAndSign(): void
    {
        self::assertSame(0, Calculator::cmp(5, 5));
        self::assertLessThan(0, Calculator::cmp(4, 5));
        self::assertGreaterThan(0, Calculator::cmp(6, 5));

        self::assertSame(1, Calculator::sign(5));
        self::assertSame(-1, Calculator::sign(-5));
        self::assertSame(0, Calculator::sign(0));
    }

    public function testBitwise(): void
    {
        self::assertSame('8', Calculator::bitAnd(12, 10)->toBase(10));
        self::assertSame('14', Calculator::bitOr(12, 10)->toBase(10));
        self::assertSame('6', Calculator::bitXor(12, 10)->toBase(10));
        self::assertSame('-13', Calculator::com(12)->toBase(10));
    }

    public function testWithBitSetsAndClearsBits(): void
    {
        $value = Calculator::init(4);

        $value = Calculator::withBit($value, 0, true);
        self::assertSame('5', $value->toBase(10));

        $value = Calculator::withBit($value, 2, false);
        self::assertSame('1', $value->toBase(10));

        // Setting an already-set bit (or clearing an already-clear one) is a no-op.
        self::assertSame('1', Calculator::withBit($value, 0, true)->toBase(10));
    }

    public function testTestbitScan0Scan1(): void
    {
        self::assertTrue(Calculator::testbit(5, 0));
        self::assertFalse(Calculator::testbit(5, 1));

        self::assertSame(1, Calculator::scan1(6, 0));
        self::assertSame(0, Calculator::scan0(6, 0));
        self::assertSame(-1, Calculator::scan1(0, 0));
    }

    public function testPopcountAndHamdist(): void
    {
        self::assertSame(2, Calculator::popcount(6));
        self::assertSame(-1, Calculator::popcount(-6));
        self::assertSame(2, Calculator::hamdist(6, 0));
        self::assertSame(0, Calculator::hamdist(-1, -1));
    }

    public function testGcdLcmGcdextInvert(): void
    {
        self::assertSame('6', Calculator::gcd(12, 18)->toBase(10));
        self::assertSame('36', Calculator::lcm(12, 18)->toBase(10));

        [$g, $s, $t] = Calculator::gcdext(12, 18);
        self::assertSame('6', $g->toBase(10));
        // Bezout identity: 12*s + 18*t == g.
        $lhs = Calculator::add(
            Calculator::mul(12, $s->toBase(10))->toBase(10),
            Calculator::mul(18, $t->toBase(10))->toBase(10),
        );
        self::assertSame('6', $lhs->toBase(10));

        $inverse = Calculator::invert(3, 4); // 3*3 = 9 = 2*4 + 1
        self::assertNotFalse($inverse);
        self::assertSame('3', $inverse->toBase(10));

        self::assertFalse(Calculator::invert(2, 4));
    }

    public function testJacobiLegendreKronecker(): void
    {
        self::assertSame(1, Calculator::jacobi(1, 3));
        self::assertSame(-1, Calculator::jacobi(2, 3));
        self::assertSame(1, Calculator::legendre(1, 3));
        self::assertSame(0, Calculator::kronecker(0, 4)); // both even -> 0
    }

    public function testPrimalityAndPerfectChecks(): void
    {
        self::assertSame('11', Calculator::nextprime(7)->toBase(10));
        self::assertGreaterThan(0, Calculator::probPrime(11));
        self::assertSame(0, Calculator::probPrime(4));

        self::assertTrue(Calculator::perfectSquare(9));
        self::assertFalse(Calculator::perfectSquare(10));
        self::assertTrue(Calculator::perfectPower(8));
        self::assertFalse(Calculator::perfectPower(10));
    }

    public function testRandomBitsAndRangeRespectBounds(): void
    {
        $bits = Calculator::randomBits(8);
        self::assertTrue($bits->isGreaterThanOrEqualTo(0));
        self::assertTrue($bits->isLessThan(256));

        $range = Calculator::randomRange(1, 10);
        self::assertTrue($range->isGreaterThanOrEqualTo(1));
        self::assertTrue($range->isLessThan(10));
    }

    public function testRandomSeedMakesRandomBitsReproducible(): void
    {
        Calculator::randomSeed(42);
        $first = Calculator::randomBits(256)->toBase(10);

        Calculator::randomSeed(42);
        $second = Calculator::randomBits(256)->toBase(10);

        self::assertSame($first, $second);
    }
}
