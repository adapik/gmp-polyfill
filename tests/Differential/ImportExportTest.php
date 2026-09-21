<?php

declare(strict_types=1);

namespace Adapik\Polyfill\Gmp\Tests\Differential;

use Adapik\Polyfill\Gmp\Calculator;
use PHPUnit\Framework\Attributes\DataProvider;

final class ImportExportTest extends DifferentialTestCase
{
    /**
     * @return iterable<string, array{int, int}>
     */
    public static function wordSizeAndFlags(): iterable
    {
        $orders = [\GMP_MSW_FIRST, \GMP_LSW_FIRST];
        $endians = [\GMP_BIG_ENDIAN, \GMP_LITTLE_ENDIAN, \GMP_NATIVE_ENDIAN];

        foreach ([1, 2, 3, 4, 8] as $wordSize) {
            foreach ($orders as $order) {
                foreach ($endians as $endian) {
                    yield "word=$wordSize flags=" . ($order | $endian) => [$wordSize, $order | $endian];
                }
            }
        }
    }

    #[DataProvider('wordSizeAndFlags')]
    public function testExportMatchesNative(int $wordSize, int $flags): void
    {
        foreach (['0', '1', '255', '65535', '72623859790382856', '123456789012345678901234567890'] as $decimal) {
            $expected = \gmp_export(\gmp_init($decimal), $wordSize, $flags);
            $actual = Calculator::export($decimal, $wordSize, $flags);

            self::assertSame(\bin2hex($expected), \bin2hex($actual), "decimal=$decimal word=$wordSize flags=$flags");
        }
    }

    #[DataProvider('wordSizeAndFlags')]
    public function testImportMatchesNative(int $wordSize, int $flags): void
    {
        if ($wordSize < 1) {
            throw new \LogicException('wordSizeAndFlags() must only yield positive word sizes');
        }

        foreach ([1, 2, 3, 5, 10] as $wordCount) {
            $bytes = \random_bytes($wordSize * $wordCount);

            $expected = \gmp_strval(\gmp_import($bytes, $wordSize, $flags));
            $actual = Calculator::import($bytes, $wordSize, $flags)->toBase(10);

            self::assertSame($expected, $actual, 'word=' . $wordSize . ' flags=' . $flags . ' bytes=' . \bin2hex($bytes));
        }
    }

    public function testExportOfZeroIsEmptyString(): void
    {
        self::assertSame(\gmp_export(\gmp_init(0)), Calculator::export('0'));
    }

    public function testImportOfEmptyStringIsZero(): void
    {
        self::assertSame(\gmp_strval(\gmp_import('')), Calculator::import('')->toBase(10));
    }
}
