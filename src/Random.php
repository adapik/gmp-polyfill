<?php

declare(strict_types=1);

namespace Adapik\Polyfill\Gmp;

use Brick\Math\BigInteger;

/**
 * Backs gmp_random_seed(): a deterministic byte generator, switched to once a seed has
 * been set, so that gmp_random_bits()/gmp_random_range() become reproducible - matching
 * native GMP's seedable-PRNG behavior, without using mt_rand().
 *
 * This is SHA-256 in counter mode, not a reimplementation of GMP's Mersenne-Twister
 * generator: the exact byte sequence for a given seed will not match native GMP's, only
 * this polyfill's own output is reproducible across calls. See the README.
 *
 * Deliberately not cryptographically secure once seeded (by design - that's what makes it
 * reproducible); gmp_random_bits()/gmp_random_range() only use this after gmp_random_seed()
 * has been called, and fall back to random_bytes() (a CSPRNG) otherwise.
 *
 * @internal
 */
final class Random
{
    private static ?string $state = null;

    private static int $counter = 0;

    public static function seed(BigInteger $seed): void
    {
        self::$state = \hash('sha256', $seed->toBase(10), true);
        self::$counter = 0;
    }

    public static function isSeeded(): bool
    {
        return self::$state !== null;
    }

    public static function bytes(int $length): string
    {
        if (self::$state === null) {
            throw new \LogicException('Random::bytes() called before Random::seed(); callers must check isSeeded() first.');
        }

        $state = self::$state;
        $output = '';
        while (\strlen($output) < $length) {
            $output .= \hash('sha256', $state . \pack('N', self::$counter), true);
            self::$counter++;
        }

        return \substr($output, 0, $length);
    }

    /**
     * Not part of the gmp_*() API: resets to the unseeded (CSPRNG) state. Used by this
     * package's own test suite to keep tests isolated from each other; native GMP has no
     * equivalent because it has no concept of "unseeding" either.
     */
    public static function resetForTesting(): void
    {
        self::$state = null;
        self::$counter = 0;
    }
}
