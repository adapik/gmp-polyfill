<?php

declare(strict_types=1);

namespace Adapik\Polyfill\Gmp\Tests\Polyfill;

/**
 * Exercises every gmp_*() shim registered by bootstrap.php through the actual global
 * function (not the internal Calculator), so the thin wrapper bodies themselves - not just
 * the logic they delegate to - are covered. Correctness is asserted per-function elsewhere
 * (see tests/Differential); this just proves each shim is wired up and doesn't fatal.
 */
final class AllFunctionsSmokeTest extends PolyfillTestCase
{
    public function testConstructionAndConversion(): void
    {
        $a = \gmp_init('123456789012345678901234567890');
        self::assertSame('123456789012345678901234567890', \gmp_strval($a));
        self::assertSame(123, \gmp_intval(\gmp_init(123)));

        $exported = \gmp_export(\gmp_init(255));
        self::assertSame('ff', \bin2hex($exported));
        self::assertSame('255', \gmp_strval(\gmp_import($exported)));
    }

    public function testArithmetic(): void
    {
        $a = \gmp_init(17);
        $b = \gmp_init(5);

        self::assertSame('22', \gmp_strval(\gmp_add($a, $b)));
        self::assertSame('12', \gmp_strval(\gmp_sub($a, $b)));
        self::assertSame('85', \gmp_strval(\gmp_mul($a, $b)));
        self::assertSame('-17', \gmp_strval(\gmp_neg($a)));
        self::assertSame('17', \gmp_strval(\gmp_abs(\gmp_init(-17))));

        self::assertSame('3', \gmp_strval(\gmp_div_q($a, $b)));
        self::assertSame('3', \gmp_strval(\gmp_div($a, $b)));
        self::assertSame('2', \gmp_strval(\gmp_div_r($a, $b)));

        [$q, $r] = \gmp_div_qr($a, $b);
        self::assertSame('3', \gmp_strval(self::asGmp($q)));
        self::assertSame('2', \gmp_strval(self::asGmp($r)));

        self::assertSame('4', \gmp_strval(\gmp_divexact(\gmp_init(20), \gmp_init(5))));
        self::assertSame('2', \gmp_strval(\gmp_mod($a, $b)));

        self::assertSame('289', \gmp_strval(\gmp_pow($a, 2)));
        self::assertSame('5', \gmp_strval(\gmp_powm($a, \gmp_init(1), \gmp_init(12))));

        self::assertSame('4', \gmp_strval(\gmp_sqrt(\gmp_init(20))));

        [$sqrt, $rem] = \gmp_sqrtrem(\gmp_init(20));
        self::assertSame('4', \gmp_strval(self::asGmp($sqrt)));
        self::assertSame('4', \gmp_strval(self::asGmp($rem)));

        self::assertSame('3', \gmp_strval(\gmp_root(\gmp_init(27), 3)));

        [$root, $rootRem] = \gmp_rootrem(\gmp_init(30), 3);
        self::assertSame('3', \gmp_strval(self::asGmp($root)));
        self::assertSame('3', \gmp_strval(self::asGmp($rootRem)));

        self::assertSame('120', \gmp_strval(\gmp_fact(5)));
        self::assertSame('10', \gmp_strval(\gmp_binomial(5, 2)));
    }

    public function testComparison(): void
    {
        self::assertSame(0, \gmp_cmp(\gmp_init(5), \gmp_init(5)));
        self::assertSame(1, \gmp_sign(\gmp_init(5)));
    }

    public function testBitwise(): void
    {
        $a = \gmp_init(12);
        $b = \gmp_init(10);

        self::assertSame('8', \gmp_strval(\gmp_and($a, $b)));
        self::assertSame('14', \gmp_strval(\gmp_or($a, $b)));
        self::assertSame('6', \gmp_strval(\gmp_xor($a, $b)));
        self::assertSame('-13', \gmp_strval(\gmp_com($a)));

        $mutable = \gmp_init(4);
        \gmp_setbit($mutable, 0);
        self::assertSame('5', \gmp_strval($mutable));
        \gmp_clrbit($mutable, 2);
        self::assertSame('1', \gmp_strval($mutable));

        self::assertTrue(\gmp_testbit(\gmp_init(5), 0));
        self::assertSame(1, \gmp_scan1(\gmp_init(6), 0));
        self::assertSame(0, \gmp_scan0(\gmp_init(6), 0));
        self::assertSame(2, \gmp_popcount(\gmp_init(6)));
        self::assertSame(2, \gmp_hamdist(\gmp_init(6), \gmp_init(0)));
    }

    public function testNumberTheory(): void
    {
        self::assertSame('6', \gmp_strval(\gmp_gcd(\gmp_init(12), \gmp_init(18))));
        self::assertSame('36', \gmp_strval(\gmp_lcm(\gmp_init(12), \gmp_init(18))));

        $gcdext = \gmp_gcdext(\gmp_init(12), \gmp_init(18));
        self::assertSame('6', \gmp_strval(self::asGmp($gcdext['g'])));

        $inverse = \gmp_invert(\gmp_init(3), \gmp_init(4));
        self::assertSame('3', \gmp_strval(self::asGmp($inverse)));
        self::assertFalse(\gmp_invert(\gmp_init(2), \gmp_init(4)));

        self::assertSame(1, \gmp_jacobi(\gmp_init(1), \gmp_init(3)));
        self::assertSame(1, \gmp_legendre(\gmp_init(1), \gmp_init(3)));
        self::assertSame(1, \gmp_kronecker(\gmp_init(1), \gmp_init(3)));

        self::assertSame('11', \gmp_strval(\gmp_nextprime(\gmp_init(7))));
        self::assertGreaterThan(0, \gmp_prob_prime(\gmp_init(11)));
        self::assertTrue(\gmp_perfect_square(\gmp_init(9)));
        self::assertTrue(\gmp_perfect_power(\gmp_init(8)));
    }

    public function testRandom(): void
    {
        $bits = \gmp_random_bits(8);
        self::assertGreaterThanOrEqual(0, \gmp_cmp($bits, \gmp_init(0)));
        self::assertLessThan(256, \gmp_intval($bits));

        $range = \gmp_random_range(\gmp_init(1), \gmp_init(10));
        self::assertGreaterThanOrEqual(1, \gmp_intval($range));
        self::assertLessThan(10, \gmp_intval($range));
    }

    public function testRandomSeedIsANoOpThatDoesNotThrow(): void
    {
        $this->expectNotToPerformAssertions();
        \gmp_random_seed(42);
    }
}
