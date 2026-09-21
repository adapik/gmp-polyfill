<?php

declare(strict_types=1);

/*
 * Registers the gmp_*() global functions and the GMP class, but only when ext-gmp is not
 * already loaded. When the native extension is available, its functions and class take
 * precedence and this file is a no-op (standard polyfill convention).
 *
 * Every shim here is a one-line call into Adapik\Polyfill\Gmp\Calculator, which returns
 * plain Brick\Math\BigInteger values; GMP::fromBigInteger() wraps them for the return type.
 * This file is the only place allowed to do that wrapping, and only ever runs when GMP
 * below is the polyfill's own stub class (see the extension_loaded() guard above), so the
 * call is always safe here even though Calculator itself must never make it.
 */

use Adapik\Polyfill\Gmp\Calculator;

if (\extension_loaded('gmp')) {
    return;
}

if (!\class_exists(GMP::class, false)) {
    require __DIR__ . '/Resources/stubs/GMP.php';
}

if (!\defined('GMP_ROUND_ZERO')) {
    \define('GMP_ROUND_ZERO', 0);
    \define('GMP_ROUND_PLUSINF', 1);
    \define('GMP_ROUND_MINUSINF', 2);
    \define('GMP_MSW_FIRST', 1);
    \define('GMP_LSW_FIRST', 2);
    \define('GMP_LITTLE_ENDIAN', 4);
    \define('GMP_BIG_ENDIAN', 8);
    \define('GMP_NATIVE_ENDIAN', 16);
    \define('GMP_VERSION', '0.0.0-adapik-gmp-polyfill');
}

if (!\function_exists('gmp_init')) {
    /**
     * Creates a GMP number from an integer or a numeric string.
     *
     * @param string|int $num  The initial value. Strings are parsed with the same rules as
     *                         the C standard library's strtol(): base 0 auto-detects a
     *                         "0x"/"0b"/leading-zero prefix, otherwise decimal.
     * @param int        $base The base to parse a string $num in: 0 for auto-detect, or 2-62.
     *
     * @throws \ValueError If $base is out of range, or $num is not a valid integer string.
     */
    function gmp_init(string|int $num, int $base = 0): GMP
    {
        return GMP::fromBigInteger(Calculator::init($num, $base));
    }
}

if (!\function_exists('gmp_intval')) {
    /**
     * Converts a GMP number to a native PHP int.
     *
     * Values outside the range of a PHP int are truncated; see the README for how this
     * polyfill's truncation differs from native GMP's undefined overflow behavior.
     *
     * @param GMP|string|int $num The value to convert.
     */
    function gmp_intval(GMP|string|int $num): int
    {
        return Calculator::intval($num);
    }
}

if (!\function_exists('gmp_strval')) {
    /**
     * Converts a GMP number to a string in the given base.
     *
     * @param GMP|string|int $num  The value to convert.
     * @param int             $base The output base: 2-62 (lower-case digits up to 36,
     *                              mixed-case above) or -2..-36 (upper-case digits, sign
     *                              carried as a separate leading '-').
     *
     * @throws \ValueError If $base is out of range.
     */
    function gmp_strval(GMP|string|int $num, int $base = 10): string
    {
        return Calculator::strval($num, $base);
    }
}

if (!\function_exists('gmp_import')) {
    /**
     * Imports a GMP number from a binary string, always returning a non-negative value.
     *
     * @param string $data      The raw bytes to import.
     * @param int    $word_size Number of bytes per word. Must divide strlen($data).
     * @param int    $flags     A bitwise-OR of one word-order flag (GMP_MSW_FIRST or
     *                          GMP_LSW_FIRST) and one endianness flag (GMP_BIG_ENDIAN,
     *                          GMP_LITTLE_ENDIAN, or GMP_NATIVE_ENDIAN).
     *
     * @throws \ValueError If $word_size is less than 1, or does not divide strlen($data).
     */
    function gmp_import(string $data, int $word_size = 1, int $flags = GMP_MSW_FIRST | GMP_NATIVE_ENDIAN): GMP
    {
        return GMP::fromBigInteger(Calculator::import($data, $word_size, $flags));
    }
}

if (!\function_exists('gmp_export')) {
    /**
     * Exports the magnitude of a GMP number to a binary string. The sign is discarded;
     * exporting zero returns an empty string.
     *
     * @param GMP|string|int $num       The value to export.
     * @param int             $word_size Number of bytes per word. Must be at least 1.
     * @param int             $flags     A bitwise-OR of one word-order flag (GMP_MSW_FIRST
     *                                  or GMP_LSW_FIRST) and one endianness flag
     *                                  (GMP_BIG_ENDIAN, GMP_LITTLE_ENDIAN, or
     *                                  GMP_NATIVE_ENDIAN).
     *
     * @throws \ValueError If $word_size is less than 1.
     */
    function gmp_export(GMP|string|int $num, int $word_size = 1, int $flags = GMP_MSW_FIRST | GMP_NATIVE_ENDIAN): string
    {
        return Calculator::export($num, $word_size, $flags);
    }
}

if (!\function_exists('gmp_add')) {
    /**
     * Adds two numbers.
     *
     * @param GMP|string|int $num1 The augend.
     * @param GMP|string|int $num2 The addend.
     *
     * @throws \ValueError If a string operand is not a valid integer string.
     */
    function gmp_add(GMP|string|int $num1, GMP|string|int $num2): GMP
    {
        return GMP::fromBigInteger(Calculator::add($num1, $num2));
    }
}

if (!\function_exists('gmp_sub')) {
    /**
     * Subtracts one number from another.
     *
     * @param GMP|string|int $num1 The minuend.
     * @param GMP|string|int $num2 The subtrahend.
     *
     * @throws \ValueError If a string operand is not a valid integer string.
     */
    function gmp_sub(GMP|string|int $num1, GMP|string|int $num2): GMP
    {
        return GMP::fromBigInteger(Calculator::sub($num1, $num2));
    }
}

if (!\function_exists('gmp_mul')) {
    /**
     * Multiplies two numbers.
     *
     * @param GMP|string|int $num1 The multiplicand.
     * @param GMP|string|int $num2 The multiplier.
     *
     * @throws \ValueError If a string operand is not a valid integer string.
     */
    function gmp_mul(GMP|string|int $num1, GMP|string|int $num2): GMP
    {
        return GMP::fromBigInteger(Calculator::mul($num1, $num2));
    }
}

if (!\function_exists('gmp_neg')) {
    /**
     * Negates a number.
     *
     * @param GMP|string|int $num The value to negate.
     *
     * @throws \ValueError If $num is a string that is not a valid integer string.
     */
    function gmp_neg(GMP|string|int $num): GMP
    {
        return GMP::fromBigInteger(Calculator::neg($num));
    }
}

if (!\function_exists('gmp_abs')) {
    /**
     * Returns the absolute value of a number.
     *
     * @param GMP|string|int $num The value.
     *
     * @throws \ValueError If $num is a string that is not a valid integer string.
     */
    function gmp_abs(GMP|string|int $num): GMP
    {
        return GMP::fromBigInteger(Calculator::abs($num));
    }
}

if (!\function_exists('gmp_div_q')) {
    /**
     * Divides two numbers, returning the quotient.
     *
     * @param GMP|string|int $num1          The dividend.
     * @param GMP|string|int $num2          The divisor.
     * @param int             $rounding_mode One of GMP_ROUND_ZERO (truncate, the default),
     *                                      GMP_ROUND_PLUSINF (round toward +infinity), or
     *                                      GMP_ROUND_MINUSINF (round toward -infinity).
     *
     * @throws \ValueError          If a string operand is invalid, or $rounding_mode is unknown.
     * @throws \DivisionByZeroError If $num2 is zero.
     */
    function gmp_div_q(GMP|string|int $num1, GMP|string|int $num2, int $rounding_mode = GMP_ROUND_ZERO): GMP
    {
        return GMP::fromBigInteger(Calculator::divQ($num1, $num2, $rounding_mode));
    }
}

if (!\function_exists('gmp_div')) {
    /**
     * Alias of gmp_div_q().
     *
     * @param GMP|string|int $num1          The dividend.
     * @param GMP|string|int $num2          The divisor.
     * @param int             $rounding_mode See gmp_div_q().
     *
     * @throws \ValueError          If a string operand is invalid, or $rounding_mode is unknown.
     * @throws \DivisionByZeroError If $num2 is zero.
     */
    function gmp_div(GMP|string|int $num1, GMP|string|int $num2, int $rounding_mode = GMP_ROUND_ZERO): GMP
    {
        return GMP::fromBigInteger(Calculator::divQ($num1, $num2, $rounding_mode));
    }
}

if (!\function_exists('gmp_div_r')) {
    /**
     * Divides two numbers, returning the remainder (consistent with the same rounding
     * mode's quotient, i.e. num1 = quotient * num2 + remainder).
     *
     * @param GMP|string|int $num1          The dividend.
     * @param GMP|string|int $num2          The divisor.
     * @param int             $rounding_mode See gmp_div_q().
     *
     * @throws \ValueError          If a string operand is invalid, or $rounding_mode is unknown.
     * @throws \DivisionByZeroError If $num2 is zero.
     */
    function gmp_div_r(GMP|string|int $num1, GMP|string|int $num2, int $rounding_mode = GMP_ROUND_ZERO): GMP
    {
        return GMP::fromBigInteger(Calculator::divR($num1, $num2, $rounding_mode));
    }
}

if (!\function_exists('gmp_div_qr')) {
    /**
     * Divides two numbers, returning both the quotient and the remainder.
     *
     * @param GMP|string|int $num1          The dividend.
     * @param GMP|string|int $num2          The divisor.
     * @param int             $rounding_mode See gmp_div_q().
     *
     * @return array{0: GMP, 1: GMP} [quotient, remainder].
     *
     * @throws \ValueError          If a string operand is invalid, or $rounding_mode is unknown.
     * @throws \DivisionByZeroError If $num2 is zero.
     */
    function gmp_div_qr(GMP|string|int $num1, GMP|string|int $num2, int $rounding_mode = GMP_ROUND_ZERO): array
    {
        [$q, $r] = Calculator::divQR($num1, $num2, $rounding_mode);

        return [GMP::fromBigInteger($q), GMP::fromBigInteger($r)];
    }
}

if (!\function_exists('gmp_divexact')) {
    /**
     * Divides two numbers, assuming the division is exact (num1 is a multiple of num2).
     * Behavior is unspecified if it is not.
     *
     * @param GMP|string|int $num1 The dividend.
     * @param GMP|string|int $num2 The divisor.
     *
     * @throws \ValueError          If a string operand is invalid.
     * @throws \DivisionByZeroError If $num2 is zero.
     */
    function gmp_divexact(GMP|string|int $num1, GMP|string|int $num2): GMP
    {
        return GMP::fromBigInteger(Calculator::divexact($num1, $num2));
    }
}

if (!\function_exists('gmp_mod')) {
    /**
     * Computes the Euclidean modulo of two numbers: the result is always non-negative,
     * regardless of the sign of either operand.
     *
     * @param GMP|string|int $num1 The dividend.
     * @param GMP|string|int $num2 The divisor (its sign is ignored).
     *
     * @throws \ValueError          If a string operand is invalid.
     * @throws \DivisionByZeroError If $num2 is zero.
     */
    function gmp_mod(GMP|string|int $num1, GMP|string|int $num2): GMP
    {
        return GMP::fromBigInteger(Calculator::mod($num1, $num2));
    }
}

if (!\function_exists('gmp_pow')) {
    /**
     * Raises a number to a non-negative integer power.
     *
     * @param GMP|string|int $num      The base.
     * @param int             $exponent The exponent. Must be non-negative.
     *
     * @throws \ValueError If $num is an invalid string, or $exponent is negative.
     */
    function gmp_pow(GMP|string|int $num, int $exponent): GMP
    {
        return GMP::fromBigInteger(Calculator::pow($num, $exponent));
    }
}

if (!\function_exists('gmp_powm')) {
    /**
     * Raises a number to a power, reduced modulo another number.
     *
     * @param GMP|string|int $num      The base.
     * @param GMP|string|int $exponent The exponent. Must be non-negative.
     * @param GMP|string|int $modulus  The modulus (its sign is ignored).
     *
     * @throws \ValueError          If an operand is an invalid string, or $exponent is negative.
     * @throws \DivisionByZeroError If $modulus is zero, or $num has no inverse modulo $modulus
     *                              (only possible when $exponent was negative).
     */
    function gmp_powm(GMP|string|int $num, GMP|string|int $exponent, GMP|string|int $modulus): GMP
    {
        return GMP::fromBigInteger(Calculator::powm($num, $exponent, $modulus));
    }
}

if (!\function_exists('gmp_sqrt')) {
    /**
     * Returns the integer (floor) square root of a non-negative number.
     *
     * @param GMP|string|int $num The value. Must be non-negative.
     *
     * @throws \ValueError If $num is negative or an invalid string.
     */
    function gmp_sqrt(GMP|string|int $num): GMP
    {
        return GMP::fromBigInteger(Calculator::sqrt($num));
    }
}

if (!\function_exists('gmp_sqrtrem')) {
    /**
     * Returns the integer square root of a non-negative number, along with the remainder
     * (num - sqrt^2).
     *
     * @param GMP|string|int $num The value. Must be non-negative.
     *
     * @return array{0: GMP, 1: GMP} [sqrt, remainder].
     *
     * @throws \ValueError If $num is negative or an invalid string.
     */
    function gmp_sqrtrem(GMP|string|int $num): array
    {
        [$sqrt, $rem] = Calculator::sqrtrem($num);

        return [GMP::fromBigInteger($sqrt), GMP::fromBigInteger($rem)];
    }
}

if (!\function_exists('gmp_root')) {
    /**
     * Returns the integer (truncated toward zero) nth root of a number.
     *
     * @param GMP|string|int $num The value. Must be non-negative if $nth is even.
     * @param int             $nth The root's degree. Must be positive.
     *
     * @throws \ValueError If $nth is not positive, or $num is negative and $nth is even.
     */
    function gmp_root(GMP|string|int $num, int $nth): GMP
    {
        return GMP::fromBigInteger(Calculator::root($num, $nth));
    }
}

if (!\function_exists('gmp_rootrem')) {
    /**
     * Returns the integer nth root of a number, along with the remainder (num - root^nth).
     *
     * @param GMP|string|int $num The value. Must be non-negative if $nth is even.
     * @param int             $nth The root's degree. Must be positive.
     *
     * @return array{0: GMP, 1: GMP} [root, remainder].
     *
     * @throws \ValueError If $nth is not positive, or $num is negative and $nth is even.
     */
    function gmp_rootrem(GMP|string|int $num, int $nth): array
    {
        [$root, $rem] = Calculator::rootrem($num, $nth);

        return [GMP::fromBigInteger($root), GMP::fromBigInteger($rem)];
    }
}

if (!\function_exists('gmp_fact')) {
    /**
     * Computes the factorial of a non-negative number.
     *
     * @param GMP|string|int $num The value. Must be non-negative.
     *
     * @throws \ValueError If $num is negative or an invalid string.
     */
    function gmp_fact(GMP|string|int $num): GMP
    {
        return GMP::fromBigInteger(Calculator::fact($num));
    }
}

if (!\function_exists('gmp_binomial')) {
    /**
     * Computes the binomial coefficient "n choose k". Negative $n is supported via the
     * standard reflection identity C(n,k) = (-1)^k * C(k-n-1, k).
     *
     * @param GMP|string|int $n The upper value.
     * @param int             $k The lower value. Must be non-negative.
     *
     * @throws \ValueError If $k is negative, or $n is an invalid string.
     */
    function gmp_binomial(GMP|string|int $n, int $k): GMP
    {
        return GMP::fromBigInteger(Calculator::binomial($n, $k));
    }
}

if (!\function_exists('gmp_cmp')) {
    /**
     * Compares two numbers.
     *
     * @param GMP|string|int $num1 The first value.
     * @param GMP|string|int $num2 The second value.
     *
     * @return int A value with the same sign as (num1 - num2): negative, zero, or positive.
     *
     * @throws \ValueError If a string operand is not a valid integer string.
     */
    function gmp_cmp(GMP|string|int $num1, GMP|string|int $num2): int
    {
        return Calculator::cmp($num1, $num2);
    }
}

if (!\function_exists('gmp_sign')) {
    /**
     * Returns the sign of a number.
     *
     * @param GMP|string|int $num The value.
     *
     * @return int -1 if negative, 0 if zero, 1 if positive.
     *
     * @throws \ValueError If $num is a string that is not a valid integer string.
     */
    function gmp_sign(GMP|string|int $num): int
    {
        return Calculator::sign($num);
    }
}

if (!\function_exists('gmp_and')) {
    /**
     * Computes the bitwise AND of two numbers, using two's-complement semantics for
     * negative operands.
     *
     * @param GMP|string|int $num1 The first operand.
     * @param GMP|string|int $num2 The second operand.
     *
     * @throws \ValueError If a string operand is not a valid integer string.
     */
    function gmp_and(GMP|string|int $num1, GMP|string|int $num2): GMP
    {
        return GMP::fromBigInteger(Calculator::bitAnd($num1, $num2));
    }
}

if (!\function_exists('gmp_or')) {
    /**
     * Computes the bitwise OR of two numbers, using two's-complement semantics for
     * negative operands.
     *
     * @param GMP|string|int $num1 The first operand.
     * @param GMP|string|int $num2 The second operand.
     *
     * @throws \ValueError If a string operand is not a valid integer string.
     */
    function gmp_or(GMP|string|int $num1, GMP|string|int $num2): GMP
    {
        return GMP::fromBigInteger(Calculator::bitOr($num1, $num2));
    }
}

if (!\function_exists('gmp_xor')) {
    /**
     * Computes the bitwise XOR of two numbers, using two's-complement semantics for
     * negative operands.
     *
     * @param GMP|string|int $num1 The first operand.
     * @param GMP|string|int $num2 The second operand.
     *
     * @throws \ValueError If a string operand is not a valid integer string.
     */
    function gmp_xor(GMP|string|int $num1, GMP|string|int $num2): GMP
    {
        return GMP::fromBigInteger(Calculator::bitXor($num1, $num2));
    }
}

if (!\function_exists('gmp_com')) {
    /**
     * Computes the two's-complement (bitwise NOT) of a number: gmp_com($n) === -$n - 1.
     *
     * @param GMP|string|int $num The value.
     *
     * @throws \ValueError If $num is a string that is not a valid integer string.
     */
    function gmp_com(GMP|string|int $num): GMP
    {
        return GMP::fromBigInteger(Calculator::com($num));
    }
}

if (!\function_exists('gmp_setbit')) {
    /**
     * Sets or clears a bit in $num, mutating it in place. Like all GMP objects, $num has
     * reference semantics: this mutation is visible through every reference to it.
     *
     * @param GMP  $num   The value to mutate.
     * @param int  $index The zero-based bit index. Must be non-negative.
     * @param bool $value True to set the bit, false to clear it.
     *
     * @throws \ValueError If $index is negative.
     */
    function gmp_setbit(GMP $num, int $index, bool $value = true): void
    {
        $num->internalSet(Calculator::withBit($num->toBigInteger(), $index, $value));
    }
}

if (!\function_exists('gmp_clrbit')) {
    /**
     * Clears a bit in $num, mutating it in place. Equivalent to gmp_setbit($num, $index, false).
     *
     * @param GMP $num   The value to mutate.
     * @param int $index The zero-based bit index. Must be non-negative.
     *
     * @throws \ValueError If $index is negative.
     */
    function gmp_clrbit(GMP $num, int $index): void
    {
        $num->internalSet(Calculator::withBit($num->toBigInteger(), $index, false));
    }
}

if (!\function_exists('gmp_testbit')) {
    /**
     * Tests whether a bit is set, using two's-complement semantics for negative numbers.
     *
     * @param GMP|string|int $num   The value.
     * @param int             $index The zero-based bit index.
     *
     * @throws \ValueError If $num is a string that is not a valid integer string.
     */
    function gmp_testbit(GMP|string|int $num, int $index): bool
    {
        return Calculator::testbit($num, $index);
    }
}

if (!\function_exists('gmp_scan0')) {
    /**
     * Finds the lowest-order zero bit at or after $start, using two's-complement
     * semantics.
     *
     * @param GMP|string|int $num   The value.
     * @param int             $start The zero-based bit index to start scanning from.
     *
     * @return int The bit index found, or -1 if there is no such bit.
     *
     * @throws \ValueError If $num is a string that is not a valid integer string.
     */
    function gmp_scan0(GMP|string|int $num, int $start): int
    {
        return Calculator::scan0($num, $start);
    }
}

if (!\function_exists('gmp_scan1')) {
    /**
     * Finds the lowest-order one bit at or after $start, using two's-complement semantics.
     *
     * @param GMP|string|int $num   The value.
     * @param int             $start The zero-based bit index to start scanning from.
     *
     * @return int The bit index found, or -1 if there is no such bit.
     *
     * @throws \ValueError If $num is a string that is not a valid integer string.
     */
    function gmp_scan1(GMP|string|int $num, int $start): int
    {
        return Calculator::scan1($num, $start);
    }
}

if (!\function_exists('gmp_popcount')) {
    /**
     * Counts the number of set bits in a non-negative number.
     *
     * @param GMP|string|int $num The value.
     *
     * @return int The population count, or -1 if $num is negative (undefined, matching
     *             native GMP).
     *
     * @throws \ValueError If $num is a string that is not a valid integer string.
     */
    function gmp_popcount(GMP|string|int $num): int
    {
        return Calculator::popcount($num);
    }
}

if (!\function_exists('gmp_hamdist')) {
    /**
     * Computes the Hamming distance (number of differing bits) between two numbers.
     *
     * @param GMP|string|int $num1 The first value.
     * @param GMP|string|int $num2 The second value.
     *
     * @return int The Hamming distance, or -1 if it is undefined (the two's-complement XOR
     *             of $num1 and $num2 has an infinite number of set bits).
     *
     * @throws \ValueError If a string operand is not a valid integer string.
     */
    function gmp_hamdist(GMP|string|int $num1, GMP|string|int $num2): int
    {
        return Calculator::hamdist($num1, $num2);
    }
}

if (!\function_exists('gmp_gcd')) {
    /**
     * Computes the greatest common divisor of two numbers. The result is always
     * non-negative.
     *
     * @param GMP|string|int $num1 The first value.
     * @param GMP|string|int $num2 The second value.
     *
     * @throws \ValueError If a string operand is not a valid integer string.
     */
    function gmp_gcd(GMP|string|int $num1, GMP|string|int $num2): GMP
    {
        return GMP::fromBigInteger(Calculator::gcd($num1, $num2));
    }
}

if (!\function_exists('gmp_gcdext')) {
    /**
     * Computes the extended GCD: g = gcd(num1, num2), along with s and t such that
     * num1*s + num2*t = g (a Bezout identity; s and t are not unique).
     *
     * @param GMP|string|int $num1 The first value.
     * @param GMP|string|int $num2 The second value.
     *
     * @return array{g: GMP, s: GMP, t: GMP}
     *
     * @throws \ValueError If a string operand is not a valid integer string.
     */
    function gmp_gcdext(GMP|string|int $num1, GMP|string|int $num2): array
    {
        [$g, $s, $t] = Calculator::gcdext($num1, $num2);

        return ['g' => GMP::fromBigInteger($g), 's' => GMP::fromBigInteger($s), 't' => GMP::fromBigInteger($t)];
    }
}

if (!\function_exists('gmp_lcm')) {
    /**
     * Computes the least common multiple of two numbers. The result is always
     * non-negative.
     *
     * @param GMP|string|int $num1 The first value.
     * @param GMP|string|int $num2 The second value.
     *
     * @throws \ValueError If a string operand is not a valid integer string.
     */
    function gmp_lcm(GMP|string|int $num1, GMP|string|int $num2): GMP
    {
        return GMP::fromBigInteger(Calculator::lcm($num1, $num2));
    }
}

if (!\function_exists('gmp_invert')) {
    /**
     * Computes the modular multiplicative inverse of num1 modulo num2.
     *
     * @param GMP|string|int $num1 The value to invert.
     * @param GMP|string|int $num2 The modulus (its sign is ignored).
     *
     * @return GMP|false The inverse, or false if it does not exist (gcd(num1, num2) != 1).
     *
     * @throws \ValueError          If a string operand is not a valid integer string.
     * @throws \DivisionByZeroError If $num2 is zero.
     */
    function gmp_invert(GMP|string|int $num1, GMP|string|int $num2): GMP|false
    {
        $result = Calculator::invert($num1, $num2);

        return $result === false ? false : GMP::fromBigInteger($result);
    }
}

if (!\function_exists('gmp_jacobi')) {
    /**
     * Computes the Jacobi symbol (num1/num2).
     *
     * @param GMP|string|int $num1 The numerator.
     * @param GMP|string|int $num2 The denominator. Must be odd and positive.
     *
     * @return int -1, 0, or 1.
     *
     * @throws \ValueError If $num2 is not odd and positive, or an operand is an invalid string.
     */
    function gmp_jacobi(GMP|string|int $num1, GMP|string|int $num2): int
    {
        return Calculator::jacobi($num1, $num2);
    }
}

if (!\function_exists('gmp_legendre')) {
    /**
     * Computes the Legendre symbol (num1/num2). Implemented via the Jacobi/Kronecker
     * symbol, which agrees with the Legendre symbol whenever num2 is an odd prime.
     *
     * @param GMP|string|int $num1 The numerator.
     * @param GMP|string|int $num2 The denominator. Should be an odd prime.
     *
     * @return int -1, 0, or 1.
     *
     * @throws \ValueError If $num2 is not positive, or an operand is an invalid string.
     */
    function gmp_legendre(GMP|string|int $num1, GMP|string|int $num2): int
    {
        return Calculator::legendre($num1, $num2);
    }
}

if (!\function_exists('gmp_kronecker')) {
    /**
     * Computes the Kronecker symbol (num1/num2), the generalization of the Jacobi symbol
     * to any integer denominator (including even and negative).
     *
     * @param GMP|string|int $num1 The numerator.
     * @param GMP|string|int $num2 The denominator.
     *
     * @return int -1, 0, or 1.
     *
     * @throws \ValueError If an operand is an invalid string.
     */
    function gmp_kronecker(GMP|string|int $num1, GMP|string|int $num2): int
    {
        return Calculator::kronecker($num1, $num2);
    }
}

if (!\function_exists('gmp_nextprime')) {
    /**
     * Finds the next prime strictly greater than $num, using a probabilistic primality
     * test (trial division followed by 30 rounds of Miller-Rabin).
     *
     * @param GMP|string|int $num The value.
     *
     * @throws \ValueError If $num is a string that is not a valid integer string.
     */
    function gmp_nextprime(GMP|string|int $num): GMP
    {
        return GMP::fromBigInteger(Calculator::nextprime($num));
    }
}

if (!\function_exists('gmp_prob_prime')) {
    /**
     * Checks whether a number is (probably) prime, via trial division by small primes
     * followed by Miller-Rabin.
     *
     * @param GMP|string|int $num          The value to test.
     * @param int             $repetitions The number of Miller-Rabin rounds to run.
     *
     * @return int 0 if definitely composite, 1 if probably prime, 2 if definitely prime
     *             (only returned for numbers with small factors, which are checked
     *             deterministically).
     *
     * @throws \ValueError If $num is a string that is not a valid integer string.
     */
    function gmp_prob_prime(GMP|string|int $num, int $repetitions = 10): int
    {
        return Calculator::probPrime($num, $repetitions);
    }
}

if (!\function_exists('gmp_perfect_square')) {
    /**
     * Checks whether a number is a perfect square.
     *
     * @param GMP|string|int $num The value.
     *
     * @throws \ValueError If $num is a string that is not a valid integer string.
     */
    function gmp_perfect_square(GMP|string|int $num): bool
    {
        return Calculator::perfectSquare($num);
    }
}

if (!\function_exists('gmp_perfect_power')) {
    /**
     * Checks whether a number is a perfect power (n = a^b for integers a >= 1, b >= 2).
     * 0, 1, and -1 are considered perfect powers; negative numbers can only be odd powers.
     *
     * @param GMP|string|int $num The value.
     *
     * @throws \ValueError If $num is a string that is not a valid integer string.
     */
    function gmp_perfect_power(GMP|string|int $num): bool
    {
        return Calculator::perfectPower($num);
    }
}

if (!\function_exists('gmp_random_bits')) {
    /**
     * Generates a cryptographically random number with exactly $bits bits, sourced from
     * random_bytes().
     *
     * @param int $bits The number of bits. Must be at least 1.
     *
     * @throws \ValueError If $bits is less than 1.
     */
    function gmp_random_bits(int $bits): GMP
    {
        return GMP::fromBigInteger(Calculator::randomBits($bits));
    }
}

if (!\function_exists('gmp_random_range')) {
    /**
     * Generates a cryptographically random number in the range [$min, $max), sourced from
     * random_bytes().
     *
     * @param GMP|string|int $min The inclusive lower bound.
     * @param GMP|string|int $max The exclusive upper bound. Must be greater than $min.
     *
     * @throws \ValueError If $min is not less than $max, or an operand is an invalid string.
     */
    function gmp_random_range(GMP|string|int $min, GMP|string|int $max): GMP
    {
        return GMP::fromBigInteger(Calculator::randomRange($min, $max));
    }
}

if (!\function_exists('gmp_random_seed')) {
    /**
     * Accepted for API compatibility, but a no-op: this polyfill sources randomness from
     * random_bytes() (a CSPRNG), which is deliberately not reproducible from a seed. See
     * the README for details.
     *
     * @param GMP|string|int $seed Ignored.
     *
     * @throws \ValueError If $seed is a string that is not a valid integer string.
     */
    function gmp_random_seed(GMP|string|int $seed): void
    {
        Calculator::randomSeed($seed);
    }
}
