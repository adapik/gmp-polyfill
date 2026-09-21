<?php

declare(strict_types=1);

namespace Adapik\Polyfill\Gmp\Tests\Differential;

use PHPUnit\Framework\TestCase;

/**
 * Base class for tests that compare the polyfill's Calculator against native ext-gmp.
 *
 * These tests call Calculator methods directly (never the global gmp_*() functions,
 * which would resolve to the native extension here) and assert the results match what
 * the real gmp_*() functions produce for the same inputs.
 */
abstract class DifferentialTestCase extends TestCase
{
    protected function setUp(): void
    {
        if (!\extension_loaded('gmp')) {
            self::markTestSkipped('ext-gmp is not loaded; differential tests require it as the oracle.');
        }
    }

    protected static function randomBigIntString(int $maxDigits = 40): string
    {
        $digits = \random_int(1, $maxDigits);
        $value = (string) \random_int(1, 9);
        for ($i = 1; $i < $digits; $i++) {
            $value .= (string) \random_int(0, 9);
        }

        return \random_int(0, 1) === 1 ? '-' . $value : $value;
    }

    /**
     * Narrows a value returned by a native gmp_*() function to GMP. PHPStan's bundled
     * stubs for ext-gmp return plain `array` from the multi-value functions (div_qr,
     * sqrtrem, gcdext, ...), so destructuring them yields `mixed`; this makes the actual
     * runtime type (always GMP here) explicit for both the analyzer and the reader.
     */
    protected static function asGmp(mixed $value): \GMP
    {
        if (!$value instanceof \GMP) {
            throw new \LogicException('Expected a GMP instance from a native gmp_*() function.');
        }

        return $value;
    }
}
