<?php

declare(strict_types=1);

namespace Adapik\Polyfill\Gmp\Tests\Polyfill;

final class BootstrapTest extends PolyfillTestCase
{
    private const FUNCTIONS = [
        'gmp_abs', 'gmp_add', 'gmp_and', 'gmp_binomial', 'gmp_clrbit', 'gmp_cmp', 'gmp_com',
        'gmp_div', 'gmp_div_q', 'gmp_div_qr', 'gmp_div_r', 'gmp_divexact', 'gmp_export',
        'gmp_fact', 'gmp_gcd', 'gmp_gcdext', 'gmp_hamdist', 'gmp_import', 'gmp_init',
        'gmp_intval', 'gmp_invert', 'gmp_jacobi', 'gmp_kronecker', 'gmp_lcm', 'gmp_mod',
        'gmp_mul', 'gmp_neg', 'gmp_nextprime', 'gmp_or', 'gmp_perfect_power',
        'gmp_perfect_square', 'gmp_popcount', 'gmp_pow', 'gmp_powm', 'gmp_prob_prime',
        'gmp_random_bits', 'gmp_random_range', 'gmp_random_seed', 'gmp_root', 'gmp_rootrem',
        'gmp_scan0', 'gmp_scan1', 'gmp_setbit', 'gmp_sign', 'gmp_sqrt', 'gmp_sqrtrem',
        'gmp_strval', 'gmp_sub', 'gmp_testbit', 'gmp_xor',
    ];

    public function testAllFunctionsAreRegistered(): void
    {
        foreach (self::FUNCTIONS as $function) {
            self::assertTrue(\function_exists($function), "$function should be defined");
        }
    }

    public function testGmpClassAndConstantsAreDefined(): void
    {
        self::assertTrue(\class_exists('GMP'));
        self::assertTrue(\interface_exists('Stringable'));
        self::assertInstanceOf(\Stringable::class, \gmp_init(1));

        foreach (['GMP_ROUND_ZERO', 'GMP_ROUND_PLUSINF', 'GMP_ROUND_MINUSINF', 'GMP_MSW_FIRST', 'GMP_LSW_FIRST', 'GMP_LITTLE_ENDIAN', 'GMP_BIG_ENDIAN', 'GMP_NATIVE_ENDIAN', 'GMP_VERSION'] as $constant) {
            self::assertTrue(\defined($constant), "$constant should be defined");
        }
    }

    public function testEndToEndArithmeticThroughGlobalFunctions(): void
    {
        $a = \gmp_init('123456789012345678901234567890');
        $b = \gmp_init(42);

        self::assertSame('123456789012345678901234567932', \gmp_strval(\gmp_add($a, $b)));
        self::assertSame('123456789012345678901234567890', \gmp_strval($a));
        self::assertSame((string) $a, \gmp_strval($a));
    }

    public function testSerializationRoundTrip(): void
    {
        $original = \gmp_init('987654321098765432109876543210');
        $restored = \unserialize(\serialize($original));

        self::assertInstanceOf(\GMP::class, $restored);
        self::assertSame(\gmp_strval($original), \gmp_strval($restored));
    }
}
