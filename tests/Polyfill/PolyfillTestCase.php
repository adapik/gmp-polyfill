<?php

declare(strict_types=1);

namespace Adapik\Polyfill\Gmp\Tests\Polyfill;

use PHPUnit\Framework\TestCase;

/**
 * Base class for tests that exercise the polyfill through the actual global gmp_*()
 * functions and the GMP class. These only register when ext-gmp is absent, so this suite
 * must run under a PHP process without the extension loaded (e.g. `php -n`).
 */
abstract class PolyfillTestCase extends TestCase
{
    protected function setUp(): void
    {
        if (\extension_loaded('gmp')) {
            self::markTestSkipped('ext-gmp is loaded; run this suite with ext-gmp disabled (e.g. php -n) to exercise the polyfill.');
        }
    }

    /**
     * Narrows a value returned by a gmp_*() function to GMP. When ext-gmp is loaded (e.g.
     * during static analysis of this suite), PHPStan's bundled stubs for the multi-value
     * functions (div_qr, sqrtrem, gcdext, ...) return plain `array`/`GMP|false`, so element
     * access yields `mixed`; this makes the actual runtime type explicit.
     */
    protected static function asGmp(mixed $value): \GMP
    {
        if (!$value instanceof \GMP) {
            throw new \LogicException('Expected a GMP instance from a gmp_*() function.');
        }

        return $value;
    }
}
