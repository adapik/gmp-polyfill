<?php

declare(strict_types=1);

namespace Adapik\Polyfill\Gmp\Tests\Unit;

use Adapik\Polyfill\Gmp\NumberParser;
use Brick\Math\BigInteger;
use PHPUnit\Framework\TestCase;

/**
 * Standalone tests for the number parser/formatter that do not depend on ext-gmp being
 * present or absent; they encode expectations verified against native GMP 6.3.0 / PHP 8.5.
 */
final class NumberParserTest extends TestCase
{
    public function testParsesDecimal(): void
    {
        self::assertSame('123', NumberParser::parse('123')->toBase(10));
        self::assertSame('-123', NumberParser::parse('-123')->toBase(10));
        self::assertSame('0', NumberParser::parse('0')->toBase(10));
        self::assertSame('0', NumberParser::parse('-0')->toBase(10));
        self::assertSame('123', NumberParser::parse(' 123 ')->toBase(10));
    }

    public function testRejectsLeadingPlusSign(): void
    {
        // Unlike most PHP number parsing, GMP rejects a leading '+' (verified against
        // native gmp_init(), which throws ValueError for "+42").
        $this->expectException(\ValueError::class);
        NumberParser::parse('+123');
    }

    public function testAutoDetectsBaseFromPrefix(): void
    {
        self::assertSame('26', NumberParser::parse('0x1A')->toBase(10));
        self::assertSame('26', NumberParser::parse('0X1a')->toBase(10));
        self::assertSame('5', NumberParser::parse('0b101')->toBase(10));
        self::assertSame('493', NumberParser::parse('0755')->toBase(10));
        self::assertSame('0', NumberParser::parse('00')->toBase(10));
    }

    public function testExplicitBaseStripsMatchingPrefix(): void
    {
        self::assertSame('26', NumberParser::parse('0x1A', 16)->toBase(10));
        self::assertSame('5', NumberParser::parse('0b101', 2)->toBase(10));
    }

    public function testBase62IsCaseSensitive(): void
    {
        self::assertSame('140563', NumberParser::parse('aZ9', 62)->toBase(10));
    }

    public function testRejectsInvalidInput(): void
    {
        foreach (['', 'abc', '--1', '1.5', ' ', '0x', '1_000', '+', '-'] as $invalid) {
            try {
                NumberParser::parse($invalid);
                self::fail("expected ValueError for: $invalid");
            } catch (\ValueError $e) {
                self::assertStringContainsString('is not an integer string', $e->getMessage());
            }
        }
    }

    public function testRejectsBaseOutOfRange(): void
    {
        foreach ([1, 63, -1, -2, -36] as $base) {
            try {
                NumberParser::parse('1', $base);
                self::fail("expected ValueError for base $base");
            } catch (\ValueError $e) {
                self::assertStringContainsString('$base', $e->getMessage());
            }
        }
    }

    public function testFormatDecimal(): void
    {
        self::assertSame('123', NumberParser::format(BigInteger::of(123)));
        self::assertSame('-123', NumberParser::format(BigInteger::of(-123)));
        self::assertSame('0', NumberParser::format(BigInteger::zero()));
    }

    public function testFormatNegativeBaseUsesUppercaseWithSignPrefix(): void
    {
        self::assertSame('-FF', NumberParser::format(BigInteger::of(-255), -16));
        self::assertSame('1111101000', NumberParser::format(BigInteger::of(1000), -2));
    }

    public function testFormatBase62(): void
    {
        self::assertSame('aZ9', NumberParser::format(BigInteger::of(140563), 62));
    }

    public function testFormatRejectsOutOfRangeBase(): void
    {
        foreach ([0, 1, -1, 63, -37] as $base) {
            try {
                NumberParser::format(BigInteger::one(), $base);
                self::fail("expected ValueError for base $base");
            } catch (\ValueError $e) {
                self::assertStringContainsString('$base', $e->getMessage());
            }
        }
    }

    public function testRoundTripAcrossBases(): void
    {
        // Negative bases are output-only for gmp_init(): the formatted digits use the
        // upper-case alphabet, which doesn't round-trip through auto-detected base-0
        // parsing, so this only checks the positive (parseable) bases.
        foreach ([2, 8, 10, 16, 36, 37, 62] as $base) {
            foreach ([0, 1, -1, 255, -255, '123456789012345678901234567890'] as $value) {
                $big = BigInteger::of($value);
                $formatted = NumberParser::format($big, $base);
                $parsed = NumberParser::parse($formatted, $base);

                self::assertSame($big->toBase(10), $parsed->toBase(10), "base=$base value=$value");
            }
        }
    }
}
