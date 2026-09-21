<?php

declare(strict_types=1);

namespace Adapik\Polyfill\Gmp\Tests\Differential;

use Adapik\Polyfill\Gmp\Calculator;
use PHPUnit\Framework\Attributes\DataProvider;

final class ConstructionConversionTest extends DifferentialTestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function decimalValues(): iterable
    {
        yield 'zero' => ['0'];
        yield 'one' => ['1'];
        yield 'negative one' => ['-1'];
        yield 'small positive' => ['12345'];
        yield 'small negative' => ['-12345'];
        yield 'leading zeros' => ['00042'];
        yield 'whitespace padded' => [' 42 '];
        yield 'huge positive' => ['123456789012345678901234567890123456789012345678901234567890'];
        yield 'huge negative' => ['-123456789012345678901234567890123456789012345678901234567890'];
        yield 'php int max' => [(string) \PHP_INT_MAX];
        yield 'php int min' => [(string) \PHP_INT_MIN];
        yield 'beyond php int max' => ['99999999999999999999999999'];
        yield 'beyond php int min' => ['-99999999999999999999999999'];
    }

    #[DataProvider('decimalValues')]
    public function testInitAndStrvalMatchNative(string $decimal): void
    {
        $expected = \gmp_strval(\gmp_init($decimal));
        $actual = Calculator::init($decimal)->toBase(10);

        self::assertSame($expected, $actual);
    }

    #[DataProvider('decimalValues')]
    public function testIntvalMatchesNative(string $decimal): void
    {
        // GMP's own docs say gmp_intval() on a value too big for a signed long is
        // "undefined" - confirmed empirically (e.g. 2^63, 2^64 and 2^128 all truncate to
        // 0 natively, which rules out any clean bit-truncation rule). We only assert
        // equality where the value actually fits, and rely on our own well-defined
        // truncation (documented on Calculator::intval()) otherwise.
        if (\gmp_cmp($decimal, \PHP_INT_MAX) > 0 || \gmp_cmp($decimal, \PHP_INT_MIN) < 0) {
            self::markTestSkipped('gmp_intval() overflow truncation is undefined native behavior.');
        }

        self::assertSame(\gmp_intval(\gmp_init($decimal)), Calculator::intval($decimal));
    }

    public function testInitRejectsPlusSign(): void
    {
        // Unlike most PHP number parsing, GMP rejects a leading '+'.
        try {
            $discarded = \gmp_init('+42');
            self::fail('native gmp_init should reject a leading +');
        } catch (\ValueError) {
        }

        $this->expectException(\ValueError::class);
        Calculator::init('+42');
    }

    public function testInitAutoDetectsPrefixes(): void
    {
        foreach (['0x1A', '0X1a', '0b101', '0B101', '0755', '-0x1A'] as $value) {
            $expected = \gmp_strval(\gmp_init($value));
            $actual = Calculator::init($value)->toBase(10);
            self::assertSame($expected, $actual, "value: $value");
        }
    }

    public function testInitWithExplicitBase(): void
    {
        foreach ([2, 8, 10, 16, 36, 62] as $base) {
            foreach (['0', '1', '10', 'Z', 'zZ9'] as $digits) {
                try {
                    $expected = \gmp_strval(\gmp_init($digits, $base));
                } catch (\ValueError) {
                    $expected = null;
                }

                try {
                    $actual = Calculator::init($digits, $base)->toBase(10);
                } catch (\ValueError) {
                    $actual = null;
                }

                self::assertSame($expected, $actual, "digits=$digits base=$base");
            }
        }
    }

    public function testInitRejectsInvalidStrings(): void
    {
        foreach (['', 'abc', '--1', '1.5', ' ', '0x', '1_000'] as $invalid) {
            try {
                $discarded = \gmp_init($invalid);
                self::fail("native gmp_init should have rejected: $invalid");
            } catch (\ValueError) {
            }

            $this->expectException(\ValueError::class);
            Calculator::init($invalid);
        }
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function strvalBases(): iterable
    {
        foreach ([2, 8, 10, 16, 36, 37, 62, -2, -16, -36] as $base) {
            yield "base $base" => [$base];
        }
    }

    #[DataProvider('strvalBases')]
    public function testStrvalMatchesNativeAcrossBases(int $base): void
    {
        foreach (['0', '1', '-1', '1234567890', '-1234567890', '123456789012345678901234567890'] as $decimal) {
            $expected = \gmp_strval(\gmp_init($decimal), $base);
            $actual = Calculator::strval($decimal, $base);
            self::assertSame($expected, $actual, "decimal=$decimal base=$base");
        }
    }

    public function testStrvalRejectsOutOfRangeBase(): void
    {
        foreach ([0, 1, -1, 63, -37] as $base) {
            try {
                $discarded = \gmp_strval(\gmp_init('1'), $base);
                self::fail("native should reject base $base");
            } catch (\ValueError) {
            }

            $this->expectException(\ValueError::class);
            Calculator::strval('1', $base);
        }
    }

    public function testFuzzRoundTripAcrossBases(): void
    {
        foreach ([2, 8, 10, 16, 36, 62, -2, -16, -36] as $base) {
            for ($i = 0; $i < 25; $i++) {
                $decimal = self::randomBigIntString(30);
                $expected = \gmp_strval(\gmp_init($decimal), $base);
                $actual = Calculator::strval($decimal, $base);
                self::assertSame($expected, $actual, "decimal=$decimal base=$base");
            }
        }
    }
}
