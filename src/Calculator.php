<?php

declare(strict_types=1);

namespace Adapik\Polyfill\Gmp;

use Brick\Math\BigInteger;
use Brick\Math\Exception\MathException;
use Brick\Math\RoundingMode;

/**
 * Translates gmp_*() function calls into operations on {@see BigInteger}.
 *
 * This is the only place that talks to brick/math directly; the global gmp_*() shims in
 * bootstrap.php are thin wrappers around the methods below. Deliberately, this class never
 * constructs or reads the internals of the global {@see \GMP} class (that bridging only
 * exists on the polyfill's own stub, and would break when ext-gmp's native GMP class is
 * loaded instead) - it accepts GMP objects as opaque, stringable operands, and always
 * returns BigInteger. That keeps it fully testable in either extension state.
 *
 * @internal
 */
final class Calculator
{
    // --- Construction & conversion -----------------------------------------------------

    /**
     * @param string|int $num  The value to parse; see NumberParser for the string grammar.
     * @param int        $base 0 (auto-detect) or 2-62.
     *
     * @throws \ValueError If $base is out of range or $num is not a valid integer string.
     */
    public static function init(string|int $num, int $base = 0): BigInteger
    {
        if (\is_int($num)) {
            return BigInteger::of($num);
        }

        return NumberParser::parse($num, $base, 'gmp_init');
    }

    /**
     * For values that fit in a PHP int, this matches gmp_intval() exactly. For values that
     * don't, native GMP's own documentation calls the result "undefined" - and empirically
     * it is not a clean bit-truncation (e.g. native gmp_intval() maps 2^63, 2^64 and 2^128
     * all to 0). This implementation instead applies a well-defined two's-complement
     * truncation to 64 bits, which is a documented deviation, not a bug.
     *
     * @param \GMP|string|int $num The value to convert.
     *
     * @throws \ValueError If $num is a string that is not a valid integer string.
     */
    public static function intval(\GMP|string|int $num): int
    {
        $value = self::toBigInteger($num, 'gmp_intval');

        $modulus = BigInteger::of(2)->power(64);
        $wrapped = $value->mod($modulus);

        if ($wrapped->isGreaterThanOrEqualTo(BigInteger::of(2)->power(63))) {
            $wrapped = $wrapped->minus($modulus);
        }

        return $wrapped->toInt();
    }

    /**
     * @param \GMP|string|int $num  The value to convert.
     * @param int             $base 2-62 or -2..-36.
     *
     * @throws \ValueError If $base is out of range or $num is an invalid string.
     */
    public static function strval(\GMP|string|int $num, int $base = 10): string
    {
        return NumberParser::format(self::toBigInteger($num, 'gmp_strval'), $base, 'gmp_strval');
    }

    /**
     * @param \GMP|string|int $num      The value to export (its magnitude only).
     * @param int             $wordSize Bytes per word. Must be at least 1.
     * @param int             $flags    Word-order/endianness flags; see bootstrap.php's gmp_export().
     *
     * @throws \ValueError If $wordSize is less than 1, or $num is an invalid string.
     */
    public static function export(\GMP|string|int $num, int $wordSize = 1, int $flags = 1 | 16): string
    {
        if ($wordSize < 1) {
            throw new \ValueError('gmp_export(): Argument #2 ($word_size) must be greater than or equal to 1');
        }

        $magnitude = self::toBigInteger($num, 'gmp_export')->abs();

        if ($magnitude->isZero()) {
            return '';
        }

        $hex = $magnitude->toBase(16);
        if (\strlen($hex) % 2 !== 0) {
            $hex = '0' . $hex;
        }

        $bytes = (string) \hex2bin($hex);
        $totalLength = (int) (\ceil(\strlen($bytes) / $wordSize) * $wordSize);
        $bytes = \str_pad($bytes, $totalLength, "\0", \STR_PAD_LEFT);

        $words = \str_split($bytes, $wordSize);

        if (self::wordsAreLeastSignificantFirst($flags)) {
            $words = \array_reverse($words);
        }

        if (self::wordsAreLittleEndian($flags)) {
            $words = \array_map(static fn (string $word): string => \strrev($word), $words);
        }

        return \implode('', $words);
    }

    /**
     * @param string $data     The raw bytes to import.
     * @param int    $wordSize Bytes per word. Must divide strlen($data).
     * @param int    $flags    Word-order/endianness flags; see bootstrap.php's gmp_import().
     *
     * @throws \ValueError If $wordSize is less than 1 or does not divide strlen($data).
     */
    public static function import(string $data, int $wordSize = 1, int $flags = 1 | 16): BigInteger
    {
        if ($wordSize < 1) {
            throw new \ValueError('gmp_import(): Argument #2 ($word_size) must be greater than or equal to 1');
        }

        if ($data === '') {
            return BigInteger::zero();
        }

        if (\strlen($data) % $wordSize !== 0) {
            throw new \ValueError('gmp_import(): Argument #1 ($data) length must be a multiple of $word_size');
        }

        $words = \str_split($data, $wordSize);

        if (self::wordsAreLittleEndian($flags)) {
            $words = \array_map(static fn (string $word): string => \strrev($word), $words);
        }

        if (self::wordsAreLeastSignificantFirst($flags)) {
            $words = \array_reverse($words);
        }

        $bytes = \implode('', $words);
        $hex = \bin2hex($bytes);

        return BigInteger::fromBase($hex === '' ? '0' : $hex, 16);
    }

    private static function wordsAreLeastSignificantFirst(int $flags): bool
    {
        return ($flags & 2) !== 0;
    }

    private static function wordsAreLittleEndian(int $flags): bool
    {
        if (($flags & 4) !== 0) {
            return true;
        }

        if (($flags & 8) !== 0) {
            return false;
        }

        return self::isHostLittleEndian();
    }

    private static function isHostLittleEndian(): bool
    {
        $unpacked = \unpack('S', "\x01\x00");

        return $unpacked !== false && $unpacked[1] === 1;
    }

    // --- Arithmetic ----------------------------------------------------------------------

    /**
     * @param \GMP|string|int $a The augend.
     * @param \GMP|string|int $b The addend.
     *
     * @throws \ValueError If an operand is an invalid string.
     */
    public static function add(\GMP|string|int $a, \GMP|string|int $b): BigInteger
    {
        return self::opA($a, 'gmp_add')->plus(self::opB($b, 'gmp_add'));
    }

    /**
     * @param \GMP|string|int $a The minuend.
     * @param \GMP|string|int $b The subtrahend.
     *
     * @throws \ValueError If an operand is an invalid string.
     */
    public static function sub(\GMP|string|int $a, \GMP|string|int $b): BigInteger
    {
        return self::opA($a, 'gmp_sub')->minus(self::opB($b, 'gmp_sub'));
    }

    /**
     * @param \GMP|string|int $a The multiplicand.
     * @param \GMP|string|int $b The multiplier.
     *
     * @throws \ValueError If an operand is an invalid string.
     */
    public static function mul(\GMP|string|int $a, \GMP|string|int $b): BigInteger
    {
        return self::opA($a, 'gmp_mul')->multipliedBy(self::opB($b, 'gmp_mul'));
    }

    /**
     * @param \GMP|string|int $num The value to negate.
     *
     * @throws \ValueError If $num is an invalid string.
     */
    public static function neg(\GMP|string|int $num): BigInteger
    {
        return self::toBigInteger($num, 'gmp_neg')->negated();
    }

    /**
     * @param \GMP|string|int $num The value.
     *
     * @throws \ValueError If $num is an invalid string.
     */
    public static function abs(\GMP|string|int $num): BigInteger
    {
        return self::toBigInteger($num, 'gmp_abs')->abs();
    }

    /**
     * @return array{0: BigInteger, 1: BigInteger}
     */
    private static function divOperands(\GMP|string|int $a, \GMP|string|int $b, string $function): array
    {
        $dividend = self::opA($a, $function);
        $divisor = self::opB($b, $function);

        if ($divisor->isZero()) {
            throw new \DivisionByZeroError(\sprintf('%s(): Argument #2 ($num2) Division by zero', $function));
        }

        return [$dividend, $divisor];
    }

    private static function roundingModeFor(int $gmpRoundingMode, string $function): RoundingMode
    {
        return match ($gmpRoundingMode) {
            0 => self::roundingMode('DOWN'),     // GMP_ROUND_ZERO
            1 => self::roundingMode('CEILING'),  // GMP_ROUND_PLUSINF
            2 => self::roundingMode('FLOOR'),    // GMP_ROUND_MINUSINF
            default => throw new \ValueError(\sprintf('%s(): Argument #3 ($rounding_mode) is not a valid rounding mode', $function)),
        };
    }

    /**
     * Looks up a {@see RoundingMode} case by name, case-insensitively.
     *
     * brick/math renamed its RoundingMode enum cases from SCREAMING_CASE (0.x) to
     * PascalCase (1.x); this keeps the polyfill working against either, which matters for
     * the --prefer-lowest CI job.
     *
     * @var array<string, RoundingMode>|null
     */
    private static ?array $roundingModesByUpperName = null;

    private static function roundingMode(string $upperCaseName): RoundingMode
    {
        if (self::$roundingModesByUpperName === null) {
            self::$roundingModesByUpperName = [];
            foreach (RoundingMode::cases() as $case) {
                self::$roundingModesByUpperName[\strtoupper($case->name)] = $case;
            }
        }

        return self::$roundingModesByUpperName[$upperCaseName];
    }

    /**
     * @param \GMP|string|int $a            The dividend.
     * @param \GMP|string|int $b            The divisor.
     * @param int             $roundingMode GMP_ROUND_ZERO, GMP_ROUND_PLUSINF, or GMP_ROUND_MINUSINF.
     *
     * @throws \ValueError          If an operand is invalid or $roundingMode is unknown.
     * @throws \DivisionByZeroError If $b is zero.
     */
    public static function divQ(\GMP|string|int $a, \GMP|string|int $b, int $roundingMode = 0): BigInteger
    {
        [$dividend, $divisor] = self::divOperands($a, $b, 'gmp_div_q');

        return $dividend->dividedBy($divisor, self::roundingModeFor($roundingMode, 'gmp_div_q'));
    }

    /**
     * @param \GMP|string|int $a            The dividend.
     * @param \GMP|string|int $b            The divisor.
     * @param int             $roundingMode GMP_ROUND_ZERO, GMP_ROUND_PLUSINF, or GMP_ROUND_MINUSINF.
     *
     * @throws \ValueError          If an operand is invalid or $roundingMode is unknown.
     * @throws \DivisionByZeroError If $b is zero.
     */
    public static function divR(\GMP|string|int $a, \GMP|string|int $b, int $roundingMode = 0): BigInteger
    {
        [$dividend, $divisor] = self::divOperands($a, $b, 'gmp_div_r');
        $quotient = $dividend->dividedBy($divisor, self::roundingModeFor($roundingMode, 'gmp_div_r'));

        return $dividend->minus($quotient->multipliedBy($divisor));
    }

    /**
     * @param \GMP|string|int $a            The dividend.
     * @param \GMP|string|int $b            The divisor.
     * @param int             $roundingMode GMP_ROUND_ZERO, GMP_ROUND_PLUSINF, or GMP_ROUND_MINUSINF.
     *
     * @return array{0: BigInteger, 1: BigInteger} [quotient, remainder].
     *
     * @throws \ValueError          If an operand is invalid or $roundingMode is unknown.
     * @throws \DivisionByZeroError If $b is zero.
     */
    public static function divQR(\GMP|string|int $a, \GMP|string|int $b, int $roundingMode = 0): array
    {
        [$dividend, $divisor] = self::divOperands($a, $b, 'gmp_div_qr');
        $quotient = $dividend->dividedBy($divisor, self::roundingModeFor($roundingMode, 'gmp_div_qr'));
        $remainder = $dividend->minus($quotient->multipliedBy($divisor));

        return [$quotient, $remainder];
    }

    /**
     * @param \GMP|string|int $a The dividend, assumed to be an exact multiple of $b.
     * @param \GMP|string|int $b The divisor.
     *
     * @throws \ValueError          If an operand is invalid.
     * @throws \DivisionByZeroError If $b is zero.
     */
    public static function divexact(\GMP|string|int $a, \GMP|string|int $b): BigInteger
    {
        [$dividend, $divisor] = self::divOperands($a, $b, 'gmp_divexact');

        return $dividend->quotient($divisor);
    }

    /**
     * @param \GMP|string|int $a The dividend.
     * @param \GMP|string|int $b The divisor (its sign is ignored).
     *
     * @throws \ValueError          If an operand is invalid.
     * @throws \DivisionByZeroError If $b is zero.
     */
    public static function mod(\GMP|string|int $a, \GMP|string|int $b): BigInteger
    {
        $dividend = self::opA($a, 'gmp_mod');
        $divisor = self::opB($b, 'gmp_mod')->abs();

        if ($divisor->isZero()) {
            throw new \DivisionByZeroError('gmp_mod(): Argument #2 ($num2) Modulo by zero');
        }

        return $dividend->mod($divisor);
    }

    /**
     * @param \GMP|string|int $num      The base.
     * @param int             $exponent Must be non-negative.
     *
     * @throws \ValueError If $num is invalid or $exponent is negative.
     */
    public static function pow(\GMP|string|int $num, int $exponent): BigInteger
    {
        if ($exponent < 0) {
            throw new \ValueError('gmp_pow(): Argument #2 ($exponent) must be greater than or equal to 0');
        }

        return self::toBigInteger($num, 'gmp_pow')->power($exponent);
    }

    /**
     * @param \GMP|string|int $num      The base.
     * @param \GMP|string|int $exponent Must be non-negative.
     * @param \GMP|string|int $modulus  The modulus (its sign is ignored).
     *
     * @throws \ValueError          If an operand is invalid or $exponent is negative.
     * @throws \DivisionByZeroError If $modulus is zero.
     */
    public static function powm(\GMP|string|int $num, \GMP|string|int $exponent, \GMP|string|int $modulus): BigInteger
    {
        $base = self::toBigInteger($num, 'gmp_powm');
        $exp = self::toBigInteger($exponent, 'gmp_powm');
        $mod = self::toBigInteger($modulus, 'gmp_powm');

        if ($mod->isZero()) {
            throw new \DivisionByZeroError('gmp_powm(): Argument #3 ($modulus) Modulo by zero');
        }

        if ($exp->isNegative()) {
            throw new \ValueError('gmp_powm(): Argument #2 ($exponent) must be greater than or equal to 0');
        }

        $mod = $mod->abs();

        if ($mod->isEqualTo(1)) {
            return BigInteger::zero();
        }

        return $base->mod($mod)->modPow($exp, $mod);
    }

    /**
     * @param \GMP|string|int $num Must be non-negative.
     *
     * @throws \ValueError If $num is negative or an invalid string.
     */
    public static function sqrt(\GMP|string|int $num): BigInteger
    {
        $value = self::toBigInteger($num, 'gmp_sqrt');

        if ($value->isNegative()) {
            throw new \ValueError('gmp_sqrt(): Argument #1 ($num) must be greater than or equal to 0');
        }

        return $value->sqrt(self::roundingMode('DOWN'));
    }

    /**
     * @param \GMP|string|int $num Must be non-negative.
     *
     * @return array{0: BigInteger, 1: BigInteger} [sqrt, remainder].
     *
     * @throws \ValueError If $num is negative or an invalid string.
     */
    public static function sqrtrem(\GMP|string|int $num): array
    {
        $value = self::toBigInteger($num, 'gmp_sqrtrem');

        if ($value->isNegative()) {
            throw new \ValueError('gmp_sqrtrem(): Argument #1 ($num) must be greater than or equal to 0');
        }

        $sqrt = $value->sqrt(self::roundingMode('DOWN'));

        return [$sqrt, $value->minus($sqrt->multipliedBy($sqrt))];
    }

    /**
     * @param \GMP|string|int $num Must be non-negative if $nth is even.
     * @param int             $nth Must be positive.
     *
     * @throws \ValueError If $nth is not positive, or $num is negative with an even $nth.
     */
    public static function root(\GMP|string|int $num, int $nth): BigInteger
    {
        return self::nthRootSigned($num, $nth, 'gmp_root');
    }

    /**
     * @param \GMP|string|int $num Must be non-negative if $nth is even.
     * @param int             $nth Must be positive.
     *
     * @return array{0: BigInteger, 1: BigInteger} [root, remainder].
     *
     * @throws \ValueError If $nth is not positive, or $num is negative with an even $nth.
     */
    public static function rootrem(\GMP|string|int $num, int $nth): array
    {
        if ($nth <= 0) {
            throw new \ValueError('gmp_rootrem(): Argument #2 ($nth) must be greater than 0');
        }

        $value = self::toBigInteger($num, 'gmp_rootrem');
        $root = self::nthRootSigned($num, $nth, 'gmp_rootrem');

        return [$root, $value->minus($root->power($nth))];
    }

    private static function nthRootSigned(\GMP|string|int $num, int $nth, string $function): BigInteger
    {
        if ($nth <= 0) {
            throw new \ValueError(\sprintf('%s(): Argument #2 ($nth) must be greater than 0', $function));
        }

        $value = self::toBigInteger($num, $function);

        if ($value->isNegative()) {
            if ($nth % 2 === 0) {
                throw new \ValueError(\sprintf('%s(): Argument #1 ($num) must be greater than or equal to 0', $function));
            }

            return self::integerNthRoot($value->abs(), $nth)->negated();
        }

        return self::integerNthRoot($value, $nth);
    }

    private static function integerNthRoot(BigInteger $n, int $k): BigInteger
    {
        if ($k < 1) {
            throw new \InvalidArgumentException('$k must be greater than or equal to 1');
        }

        if ($n->isZero() || $k === 1) {
            return $n;
        }

        $bitLength = $n->getBitLength();
        $initialExponent = \max(0, \intdiv($bitLength, $k) + 1);
        $x = BigInteger::of(2)->power($initialExponent);

        while (true) {
            $xkm1 = $x->power($k - 1);
            $y = BigInteger::of($k - 1)->multipliedBy($x)->plus($n->quotient($xkm1))->quotient($k);

            if ($y->isGreaterThanOrEqualTo($x)) {
                break;
            }

            $x = $y;
        }

        while ($x->power($k)->isGreaterThan($n)) {
            $x = $x->minus(1);
        }

        while ($x->plus(1)->power($k)->isLessThanOrEqualTo($n)) {
            $x = $x->plus(1);
        }

        return $x;
    }

    /**
     * @param \GMP|string|int $num Must be non-negative.
     *
     * @throws \ValueError If $num is negative or an invalid string.
     */
    public static function fact(\GMP|string|int $num): BigInteger
    {
        $value = self::toBigInteger($num, 'gmp_fact');

        if ($value->isNegative()) {
            throw new \ValueError('gmp_fact(): Argument #1 ($num) must be greater than or equal to 0');
        }

        $result = BigInteger::one();
        $n = $value->toInt();

        for ($i = 2; $i <= $n; $i++) {
            $result = $result->multipliedBy($i);
        }

        return $result;
    }

    /**
     * @param \GMP|string|int $n The upper value.
     * @param int             $k The lower value. Must be non-negative.
     *
     * @throws \ValueError If $k is negative, or $n is an invalid string.
     */
    public static function binomial(\GMP|string|int $n, int $k): BigInteger
    {
        if ($k < 0) {
            throw new \ValueError('gmp_binomial(): Argument #2 ($k) must be greater than or equal to 0');
        }

        $nValue = self::toBigInteger($n, 'gmp_binomial');

        if ($nValue->isNegative()) {
            // C(n, k) = (-1)^k * C(k - n - 1, k) for negative n.
            $reflected = BigInteger::of($k)->minus($nValue)->minus(1);
            $result = self::binomialNonNegative($reflected, $k);

            return $k % 2 === 0 ? $result : $result->negated();
        }

        return self::binomialNonNegative($nValue, $k);
    }

    private static function binomialNonNegative(BigInteger $n, int $k): BigInteger
    {
        if (BigInteger::of($k)->isGreaterThan($n)) {
            return BigInteger::zero();
        }

        $kBig = BigInteger::of($k);
        $complement = $n->minus($kBig);
        if ($complement->isLessThan($kBig)) {
            $k = $complement->toInt();
        }

        $result = BigInteger::one();
        for ($i = 0; $i < $k; $i++) {
            $result = $result->multipliedBy($n->minus($i))->quotient($i + 1);
        }

        return $result;
    }

    // --- Comparison ------------------------------------------------------------------------

    /**
     * @param \GMP|string|int $a The first value.
     * @param \GMP|string|int $b The second value.
     *
     * @return int Negative, zero, or positive, matching the sign of (a - b).
     *
     * @throws \ValueError If an operand is an invalid string.
     */
    public static function cmp(\GMP|string|int $a, \GMP|string|int $b): int
    {
        return self::opA($a, 'gmp_cmp')->compareTo(self::opB($b, 'gmp_cmp'));
    }

    /**
     * @param \GMP|string|int $num The value.
     *
     * @return int -1, 0, or 1.
     *
     * @throws \ValueError If $num is an invalid string.
     */
    public static function sign(\GMP|string|int $num): int
    {
        return self::toBigInteger($num, 'gmp_sign')->getSign();
    }

    // --- Bitwise ---------------------------------------------------------------------------

    /**
     * @param \GMP|string|int $a The first operand.
     * @param \GMP|string|int $b The second operand.
     *
     * @throws \ValueError If an operand is an invalid string.
     */
    public static function bitAnd(\GMP|string|int $a, \GMP|string|int $b): BigInteger
    {
        return self::opA($a, 'gmp_and')->and(self::opB($b, 'gmp_and'));
    }

    /**
     * @param \GMP|string|int $a The first operand.
     * @param \GMP|string|int $b The second operand.
     *
     * @throws \ValueError If an operand is an invalid string.
     */
    public static function bitOr(\GMP|string|int $a, \GMP|string|int $b): BigInteger
    {
        return self::opA($a, 'gmp_or')->or(self::opB($b, 'gmp_or'));
    }

    /**
     * @param \GMP|string|int $a The first operand.
     * @param \GMP|string|int $b The second operand.
     *
     * @throws \ValueError If an operand is an invalid string.
     */
    public static function bitXor(\GMP|string|int $a, \GMP|string|int $b): BigInteger
    {
        return self::opA($a, 'gmp_xor')->xor(self::opB($b, 'gmp_xor'));
    }

    /**
     * @param \GMP|string|int $num The value.
     *
     * @throws \ValueError If $num is an invalid string.
     */
    public static function com(\GMP|string|int $num): BigInteger
    {
        return self::toBigInteger($num, 'gmp_com')->not();
    }

    /**
     * Pure computation behind gmp_setbit()/gmp_clrbit(): given the current value, returns
     * the value with bit $index set to $value. The caller (bootstrap.php) is responsible
     * for writing the result back into the mutable GMP wrapper, since this class never
     * touches the global GMP class.
     *
     * @param BigInteger $current The current value.
     * @param int        $index   The zero-based bit index. Must be non-negative.
     * @param bool       $value   True to set the bit, false to clear it.
     *
     * @throws \ValueError If $index is negative.
     */
    public static function withBit(BigInteger $current, int $index, bool $value): BigInteger
    {
        if ($index < 0) {
            throw new \ValueError('gmp_setbit(): Argument #2 ($index) must be greater than or equal to 0');
        }

        if (self::bitIsSet($current, $index) === $value) {
            return $current;
        }

        $bit = BigInteger::one()->shiftedLeft($index);

        return $value ? $current->or($bit) : $current->and($bit->not());
    }

    /**
     * @param \GMP|string|int $num   The value.
     * @param int             $index The zero-based bit index.
     *
     * @throws \ValueError If $num is an invalid string.
     */
    public static function testbit(\GMP|string|int $num, int $index): bool
    {
        if ($index < 0) {
            return false;
        }

        return self::bitIsSet(self::toBigInteger($num, 'gmp_testbit'), $index);
    }

    /**
     * @param \GMP|string|int $num   The value.
     * @param int             $start The zero-based bit index to start scanning from.
     *
     * @return int The bit index found, or -1 if there is no such bit.
     *
     * @throws \ValueError If $num is an invalid string.
     */
    public static function scan0(\GMP|string|int $num, int $start): int
    {
        return self::scanBit(self::toBigInteger($num, 'gmp_scan0'), $start, false);
    }

    /**
     * @param \GMP|string|int $num   The value.
     * @param int             $start The zero-based bit index to start scanning from.
     *
     * @return int The bit index found, or -1 if there is no such bit.
     *
     * @throws \ValueError If $num is an invalid string.
     */
    public static function scan1(\GMP|string|int $num, int $start): int
    {
        return self::scanBit(self::toBigInteger($num, 'gmp_scan1'), $start, true);
    }

    /**
     * Tests whether bit $index is set, using two's-complement semantics.
     *
     * Not delegated to BigInteger::isBitSet(): that method is named testBit() in
     * brick/math 0.12.x and was renamed to isBitSet() in 1.x, so it's implemented here in
     * terms of shiftedRight()/isOdd() (stable across both) to stay compatible with the
     * --prefer-lowest dependency range.
     */
    private static function bitIsSet(BigInteger $value, int $index): bool
    {
        if ($index < 0) {
            throw new \InvalidArgumentException('$index must be non-negative');
        }

        return $value->shiftedRight($index)->isOdd();
    }

    private static function scanBit(BigInteger $value, int $start, bool $target): int
    {
        if ($start < 0) {
            $start = 0;
        }

        $bound = $value->abs()->getBitLength() + 2;

        for ($i = $start; $i < $start + $bound; $i++) {
            if (self::bitIsSet($value, $i) === $target) {
                return $i;
            }
        }

        return -1;
    }

    /**
     * @param \GMP|string|int $num The value.
     *
     * @return int The population count, or -1 if $num is negative (undefined).
     *
     * @throws \ValueError If $num is an invalid string.
     */
    public static function popcount(\GMP|string|int $num): int
    {
        $value = self::toBigInteger($num, 'gmp_popcount');

        if ($value->isNegative()) {
            return -1;
        }

        return \substr_count($value->toBase(2), '1');
    }

    /**
     * @param \GMP|string|int $a The first value.
     * @param \GMP|string|int $b The second value.
     *
     * @return int The Hamming distance, or -1 if it is undefined.
     *
     * @throws \ValueError If an operand is an invalid string.
     */
    public static function hamdist(\GMP|string|int $a, \GMP|string|int $b): int
    {
        $x = self::opA($a, 'gmp_hamdist');
        $y = self::opB($b, 'gmp_hamdist');

        // Well-defined whenever the two's-complement XOR has a finite number of set bits,
        // i.e. whenever a and b have the same sign (their infinite sign-extension tails
        // cancel out); popcount() already returns -1 for a negative (infinite-tail) result.
        return self::popcount($x->xor($y)->toBase(10));
    }

    // --- Number theory -----------------------------------------------------------------

    /**
     * @param \GMP|string|int $a The first value.
     * @param \GMP|string|int $b The second value.
     *
     * @throws \ValueError If an operand is an invalid string.
     */
    public static function gcd(\GMP|string|int $a, \GMP|string|int $b): BigInteger
    {
        return self::opA($a, 'gmp_gcd')->gcd(self::opB($b, 'gmp_gcd'));
    }

    /**
     * @param \GMP|string|int $a The first value.
     * @param \GMP|string|int $b The second value.
     *
     * @throws \ValueError If an operand is an invalid string.
     */
    public static function lcm(\GMP|string|int $a, \GMP|string|int $b): BigInteger
    {
        // Not delegated to BigInteger::lcm(): that method doesn't exist in brick/math 0.12.x
        // (only added later), so it's computed here to stay compatible with the
        // --prefer-lowest dependency range.
        $aValue = self::opA($a, 'gmp_lcm');
        $bValue = self::opB($b, 'gmp_lcm');

        if ($aValue->isZero() || $bValue->isZero()) {
            return BigInteger::zero();
        }

        return $aValue->quotient($aValue->gcd($bValue))->multipliedBy($bValue)->abs();
    }

    /**
     * @param \GMP|string|int $a The first value.
     * @param \GMP|string|int $b The second value.
     *
     * @return array{0: BigInteger, 1: BigInteger, 2: BigInteger} [g, s, t] such that
     *         a*s + b*t = g = gcd(a, b).
     *
     * @throws \ValueError If an operand is an invalid string.
     */
    public static function gcdext(\GMP|string|int $a, \GMP|string|int $b): array
    {
        $r0 = self::opA($a, 'gmp_gcdext');
        $r1 = self::opB($b, 'gmp_gcdext');
        $s0 = BigInteger::one();
        $s1 = BigInteger::zero();
        $t0 = BigInteger::zero();
        $t1 = BigInteger::one();

        while (!$r1->isZero()) {
            $quotient = $r0->quotient($r1);

            [$r0, $r1] = [$r1, $r0->minus($quotient->multipliedBy($r1))];
            [$s0, $s1] = [$s1, $s0->minus($quotient->multipliedBy($s1))];
            [$t0, $t1] = [$t1, $t0->minus($quotient->multipliedBy($t1))];
        }

        if ($r0->isNegative()) {
            $r0 = $r0->negated();
            $s0 = $s0->negated();
            $t0 = $t0->negated();
        }

        return [$r0, $s0, $t0];
    }

    /**
     * @param \GMP|string|int $a The value to invert.
     * @param \GMP|string|int $b The modulus (its sign is ignored).
     *
     * @return BigInteger|false The inverse, or false if it does not exist.
     *
     * @throws \ValueError          If an operand is an invalid string.
     * @throws \DivisionByZeroError If $b is zero.
     */
    public static function invert(\GMP|string|int $a, \GMP|string|int $b): BigInteger|false
    {
        $modulus = self::opB($b, 'gmp_invert')->abs();

        if ($modulus->isZero()) {
            throw new \DivisionByZeroError('gmp_invert(): Argument #2 ($num2) Division by zero');
        }

        try {
            return self::opA($a, 'gmp_invert')->mod($modulus)->modInverse($modulus);
        } catch (MathException) {
            // brick/math 1.x throws the more specific NoInverseException (which implements
            // MathException); 0.x throws MathException directly. Catching the common
            // ancestor/interface keeps this correct against both (see the --prefer-lowest
            // CI job).
            return false;
        }
    }

    /**
     * @param \GMP|string|int $num The value.
     *
     * @throws \ValueError If $num is an invalid string.
     */
    public static function nextprime(\GMP|string|int $num): BigInteger
    {
        $value = self::toBigInteger($num, 'gmp_nextprime');

        if ($value->isLessThan(2)) {
            return BigInteger::of(2);
        }

        $candidate = $value->plus(1);
        if ($candidate->isEven()) {
            $candidate = $candidate->plus(1);
        }

        while (!self::isProbablyPrime($candidate, 30)) {
            $candidate = $candidate->plus(2);
        }

        return $candidate;
    }

    /**
     * @param \GMP|string|int $num          The value to test.
     * @param int             $repetitions  Number of Miller-Rabin rounds.
     *
     * @return int 0 (composite), 1 (probably prime), or 2 (definitely prime).
     *
     * @throws \ValueError If $num is an invalid string.
     */
    public static function probPrime(\GMP|string|int $num, int $repetitions = 10): int
    {
        // GMP tests the magnitude: gmp_prob_prime(-5) is "definitely prime" (2), same as 5.
        $value = self::toBigInteger($num, 'gmp_prob_prime')->abs();

        if ($value->isLessThan(2)) {
            return 0;
        }

        foreach (self::SMALL_PRIMES as $p) {
            $prime = BigInteger::of($p);
            if ($value->isEqualTo($prime)) {
                return 2;
            }
            if ($value->mod($prime)->isZero()) {
                return 0;
            }
        }

        return self::isProbablyPrime($value, \max($repetitions, 1)) ? 1 : 0;
    }

    private const SMALL_PRIMES = [2, 3, 5, 7, 11, 13, 17, 19, 23, 29, 31, 37, 41, 43, 47];

    private static function isProbablyPrime(BigInteger $n, int $rounds): bool
    {
        if ($n->isLessThan(2)) {
            return false;
        }

        foreach (self::SMALL_PRIMES as $p) {
            $prime = BigInteger::of($p);
            if ($n->isEqualTo($prime)) {
                return true;
            }
            if ($n->mod($prime)->isZero()) {
                return false;
            }
        }

        // Miller-Rabin
        $d = $n->minus(1);
        $r = 0;
        while ($d->isEven()) {
            $d = $d->quotient(2);
            $r++;
        }

        $nMinus1 = $n->minus(1);
        $nMinus3 = $n->minus(3);

        for ($i = 0; $i < $rounds; $i++) {
            if ($nMinus3->isLessThan(1)) {
                $a = BigInteger::of(2);
            } else {
                $a = BigInteger::randomRange(BigInteger::of(2), $nMinus1->isLessThan(2) ? BigInteger::of(2) : $nMinus1);
            }

            $x = $a->modPow($d, $n);

            if ($x->isEqualTo(1) || $x->isEqualTo($nMinus1)) {
                continue;
            }

            $composite = true;
            for ($j = 0; $j < $r - 1; $j++) {
                $x = $x->modPow(2, $n);
                if ($x->isEqualTo($nMinus1)) {
                    $composite = false;
                    break;
                }
            }

            if ($composite) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param \GMP|string|int $num The value.
     *
     * @throws \ValueError If $num is an invalid string.
     */
    public static function perfectSquare(\GMP|string|int $num): bool
    {
        $value = self::toBigInteger($num, 'gmp_perfect_square');

        if ($value->isNegative()) {
            return false;
        }

        $sqrt = $value->sqrt(self::roundingMode('DOWN'));

        return $sqrt->multipliedBy($sqrt)->isEqualTo($value);
    }

    /**
     * @param \GMP|string|int $num The value.
     *
     * @throws \ValueError If $num is an invalid string.
     */
    public static function perfectPower(\GMP|string|int $num): bool
    {
        $value = self::toBigInteger($num, 'gmp_perfect_power');

        if ($value->isZero() || $value->isEqualTo(1) || $value->isEqualTo(-1)) {
            return true;
        }

        $magnitude = $value->abs();
        $bitLength = $magnitude->getBitLength();

        for ($k = 2; $k <= $bitLength; $k++) {
            if ($value->isNegative() && $k % 2 === 0) {
                continue;
            }

            $root = self::integerNthRoot($magnitude, $k);
            if ($root->power($k)->isEqualTo($magnitude) && $root->isGreaterThan(1)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param \GMP|string|int $a The numerator.
     * @param \GMP|string|int $b The denominator.
     *
     * @return int -1, 0, or 1.
     *
     * @throws \ValueError If an operand is an invalid string.
     */
    public static function kronecker(\GMP|string|int $a, \GMP|string|int $b): int
    {
        return self::kroneckerSymbol(self::opA($a, 'gmp_kronecker'), self::opB($b, 'gmp_kronecker'));
    }

    /**
     * @param \GMP|string|int $a The numerator.
     * @param \GMP|string|int $b The denominator. Must be odd and positive.
     *
     * @return int -1, 0, or 1.
     *
     * @throws \ValueError If $b is not odd and positive, or an operand is an invalid string.
     */
    public static function jacobi(\GMP|string|int $a, \GMP|string|int $b): int
    {
        $bValue = self::opB($b, 'gmp_jacobi');

        if ($bValue->isNegativeOrZero() || $bValue->isEven()) {
            throw new \ValueError('gmp_jacobi(): Argument #2 ($num2) must be an odd, positive number');
        }

        return self::kroneckerSymbol(self::opA($a, 'gmp_jacobi'), $bValue);
    }

    /**
     * @param \GMP|string|int $a The numerator.
     * @param \GMP|string|int $b The denominator. Should be an odd prime.
     *
     * @return int -1, 0, or 1.
     *
     * @throws \ValueError If $b is not positive, or an operand is an invalid string.
     */
    public static function legendre(\GMP|string|int $a, \GMP|string|int $b): int
    {
        $bValue = self::opB($b, 'gmp_legendre');

        if ($bValue->isNegativeOrZero()) {
            throw new \ValueError('gmp_legendre(): Argument #2 ($num2) must be an odd prime');
        }

        return self::kroneckerSymbol(self::opA($a, 'gmp_legendre'), $bValue);
    }

    private static function kroneckerSymbol(BigInteger $a, BigInteger $b): int
    {
        if ($b->isZero()) {
            return $a->abs()->isEqualTo(1) ? 1 : 0;
        }

        if ($a->isEven() && $b->isEven()) {
            return 0;
        }

        $k = 1;

        if ($b->isNegative()) {
            $b = $b->negated();
            if ($a->isNegative()) {
                $k = -$k;
            }
        }

        $v = 0;
        while ($b->isEven()) {
            $b = $b->quotient(2);
            $v++;
        }

        if ($v % 2 === 1) {
            $k *= self::table2($a);
        }

        if ($a->isNegative()) {
            $a = $a->negated();
            if ($b->mod(BigInteger::of(4))->isEqualTo(3)) {
                $k = -$k;
            }
        }

        while (!$a->isZero()) {
            $v = 0;
            while ($a->isEven()) {
                $a = $a->quotient(2);
                $v++;
            }

            if ($v % 2 === 1) {
                $k *= self::table2($b);
            }

            if ($a->mod(BigInteger::of(4))->isEqualTo(3) && $b->mod(BigInteger::of(4))->isEqualTo(3)) {
                $k = -$k;
            }

            $r = $a;
            $a = $b->mod($a);
            $b = $r;
        }

        if ($b->isEqualTo(1)) {
            return $k;
        }

        return 0;
    }

    /**
     * (2/n) for odd n, using n mod 8.
     */
    private static function table2(BigInteger $n): int
    {
        $residue = $n->mod(BigInteger::of(8))->toInt();

        return match ($residue) {
            1, 7 => 1,
            3, 5 => -1,
            default => 0,
        };
    }

    // --- Random --------------------------------------------------------------------------

    /**
     * @param int $bits Number of bits. Must be at least 1.
     *
     * @throws \ValueError If $bits is less than 1.
     */
    public static function randomBits(int $bits): BigInteger
    {
        if ($bits < 1) {
            throw new \ValueError('gmp_random_bits(): Argument #1 ($bits) must be greater than or equal to 1');
        }

        return BigInteger::randomBits($bits);
    }

    /**
     * @param \GMP|string|int $min The inclusive lower bound.
     * @param \GMP|string|int $max The exclusive upper bound. Must be greater than $min.
     *
     * @throws \ValueError If $min is not less than $max, or an operand is invalid.
     */
    public static function randomRange(\GMP|string|int $min, \GMP|string|int $max): BigInteger
    {
        $minValue = self::opA($min, 'gmp_random_range');
        $maxValue = self::opB($max, 'gmp_random_range');

        if ($minValue->isGreaterThanOrEqualTo($maxValue)) {
            throw new \ValueError('gmp_random_range(): Argument #1 ($min) must be less than Argument #2 ($max)');
        }

        return BigInteger::randomRange($minValue, $maxValue->minus(1));
    }

    /**
     * Accepted for API compatibility, but a no-op: unlike native GMP's Mersenne Twister,
     * this polyfill sources randomness from random_bytes() (a CSPRNG), which is
     * deliberately not reproducible from a seed. See the README for details.
     *
     * @param \GMP|string|int $seed Ignored.
     *
     * @throws \ValueError If $seed is a string that is not a valid integer string.
     */
    public static function randomSeed(\GMP|string|int $seed): void
    {
        self::toBigInteger($seed, 'gmp_random_seed');
    }

    // --- Helpers ---------------------------------------------------------------------------

    private static function opA(\GMP|string|int $value, string $function): BigInteger
    {
        return self::toBigInteger($value, $function, '#1 ($num1)');
    }

    private static function opB(\GMP|string|int $value, string $function): BigInteger
    {
        return self::toBigInteger($value, $function, '#2 ($num2)');
    }

    /**
     * Converts an operand to a BigInteger. GMP instances (native or polyfill) are read via
     * string casting only, so this class never depends on the polyfill's internal bridging
     * methods and works correctly regardless of whether ext-gmp is loaded.
     *
     * @param \GMP|string|int $value    The operand to convert.
     * @param string          $function The calling gmp_*() function name, used in error messages.
     * @param string          $argument The argument descriptor (e.g. "#1 ($num)"), used in error messages.
     *
     * @throws \ValueError If $value is a string that is not a valid integer string.
     */
    public static function toBigInteger(\GMP|string|int $value, string $function, string $argument = '#1 ($num)'): BigInteger
    {
        if ($value instanceof \GMP) {
            return BigInteger::of((string) $value);
        }

        if (\is_int($value)) {
            return BigInteger::of($value);
        }

        return NumberParser::parse($value, 0, $function, $argument);
    }
}
