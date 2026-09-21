<?php

declare(strict_types=1);

namespace Adapik\Polyfill\Gmp\Tests\Differential;

use Adapik\Polyfill\Gmp\Calculator;

final class ComparisonTest extends DifferentialTestCase
{
    public function testCmpAndSign(): void
    {
        $values = ['0', '1', '-1', '5', '-5', '123456789012345678901234567890', '-123456789012345678901234567890'];

        foreach ($values as $a) {
            self::assertSame(\gmp_sign(\gmp_init($a)), Calculator::sign($a), "sign($a)");

            foreach ($values as $b) {
                $expected = \gmp_cmp($a, $b);
                $actual = Calculator::cmp($a, $b);

                // GMP only guarantees the sign of cmp(), not the magnitude.
                self::assertSame($expected <=> 0, $actual <=> 0, "cmp($a,$b)");
            }
        }
    }
}
