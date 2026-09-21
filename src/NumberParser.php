<?php

declare(strict_types=1);

namespace Adapik\Polyfill\Gmp;

use Brick\Math\BigInteger;
use Brick\Math\Exception\MathException;

/**
 * Parses and formats integer strings using the same conventions as the native GMP extension.
 *
 * GMP supports bases 0 (auto-detect) and 2 through 62 for parsing, and 2 through 62 plus
 * -2 through -36 for formatting. Bases up to 36 are case-insensitive and use lower case
 * digits on output; bases 37-62 use '0'-'9', 'A'-'Z', then 'a'-'z' (case sensitive); negative
 * bases use the same digit set as their positive counterpart but render upper case letters
 * with the sign carried as a separate leading '-'.
 *
 * @internal
 */
final class NumberParser
{
    private const ALPHABET_62 = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';

    private const ALPHABET_UPPER = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';

    /**
     * Parses a GMP-formatted integer string, mirroring gmp_init()'s argument validation.
     *
     * @throws \ValueError If the base is out of range or the string is not a valid integer.
     */
    public static function parse(string $number, int $base = 0, string $function = 'gmp_init', string $argument = '#1 ($num)'): BigInteger
    {
        if ($base !== 0 && ($base < 2 || $base > 62)) {
            throw new \ValueError(\sprintf('%s(): Argument #2 ($base) must be 0 or between 2 and 62', $function));
        }

        $trimmed = \trim($number);

        // Unlike most PHP number parsing, GMP accepts a leading '-' but rejects a leading
        // '+' (Brick\Math\BigInteger::fromBase() would silently accept and strip it, so
        // this has to be checked explicitly).
        if ($trimmed !== '' && $trimmed[0] === '+') {
            throw self::notAnIntegerString($function, $argument);
        }

        $sign = 1;
        if ($trimmed !== '' && $trimmed[0] === '-') {
            $sign = -1;
            $trimmed = \substr($trimmed, 1);
        }

        // Reject a second sign character (e.g. "--1"): fromBase()/fromArbitraryBase() would
        // otherwise silently strip a leading '-' of their own, double-negating it back to
        // a spurious positive result instead of raising an error like native GMP does.
        if ($trimmed !== '' && ($trimmed[0] === '-' || $trimmed[0] === '+')) {
            throw self::notAnIntegerString($function, $argument);
        }

        $detectedBase = $base;

        if ($base === 0) {
            if (self::hasPrefix($trimmed, '0x') || self::hasPrefix($trimmed, '0X')) {
                $detectedBase = 16;
                $trimmed = \substr($trimmed, 2);
            } elseif (self::hasPrefix($trimmed, '0b') || self::hasPrefix($trimmed, '0B')) {
                $detectedBase = 2;
                $trimmed = \substr($trimmed, 2);
            } elseif ($trimmed !== '' && $trimmed[0] === '0' && \strlen($trimmed) > 1) {
                $detectedBase = 8;
                $trimmed = \substr($trimmed, 1);
            } else {
                $detectedBase = 10;
            }
        } elseif ($base === 16 && (self::hasPrefix($trimmed, '0x') || self::hasPrefix($trimmed, '0X'))) {
            $trimmed = \substr($trimmed, 2);
        } elseif ($base === 2 && (self::hasPrefix($trimmed, '0b') || self::hasPrefix($trimmed, '0B'))) {
            $trimmed = \substr($trimmed, 2);
        }

        if ($trimmed === '') {
            throw self::notAnIntegerString($function, $argument);
        }

        if ($detectedBase < 2) {
            // Unreachable in practice ($base is validated above and every branch that can
            // set $detectedBase picks a value in [2, 62]); this guard exists to give static
            // analysis a hard bound to narrow on.
            throw new \LogicException('Unreachable: detected base out of range');
        }

        try {
            if ($detectedBase <= 36) {
                $magnitude = BigInteger::fromBase($trimmed, $detectedBase);
            } else {
                $alphabet = \substr(self::ALPHABET_62, 0, $detectedBase);
                $magnitude = BigInteger::fromArbitraryBase($trimmed, $alphabet);
            }
        } catch (MathException|\InvalidArgumentException $e) {
            throw self::notAnIntegerString($function, $argument);
        }

        return $sign < 0 ? $magnitude->negated() : $magnitude;
    }

    /**
     * Formats a BigInteger using GMP's string-base conventions, mirroring gmp_strval().
     *
     * @throws \ValueError If the base is out of the supported range.
     */
    public static function format(BigInteger $value, int $base = 10, string $function = 'gmp_strval'): string
    {
        if ($base > 62 || $base < -36 || ($base > -2 && $base < 2)) {
            throw new \ValueError(\sprintf('%s(): Argument #2 ($base) must be between 2 and 62, or -2 and -36', $function));
        }

        $negative = $value->isNegative();
        $magnitude = $value->abs();

        if ($base >= 2 && $base <= 36) {
            $digits = $magnitude->toBase($base);
        } elseif ($base >= 37) {
            $alphabet = \substr(self::ALPHABET_62, 0, $base);
            $digits = self::toArbitraryBaseOrZero($magnitude, $alphabet);
        } else {
            $alphabet = \substr(self::ALPHABET_UPPER, 0, -$base);
            $digits = self::toArbitraryBaseOrZero($magnitude, $alphabet);
        }

        return $negative ? '-' . $digits : $digits;
    }

    /**
     * @param non-empty-string $alphabet
     */
    private static function toArbitraryBaseOrZero(BigInteger $magnitude, string $alphabet): string
    {
        if ($magnitude->isZero()) {
            return $alphabet[0];
        }

        return $magnitude->toArbitraryBase($alphabet);
    }

    private static function hasPrefix(string $value, string $prefix): bool
    {
        return \substr($value, 0, \strlen($prefix)) === $prefix;
    }

    private static function notAnIntegerString(string $function, string $argument): \ValueError
    {
        return new \ValueError(\sprintf('%s(): Argument %s is not an integer string', $function, $argument));
    }
}
