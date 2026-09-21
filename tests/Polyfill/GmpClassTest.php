<?php

declare(strict_types=1);

namespace Adapik\Polyfill\Gmp\Tests\Polyfill;

final class GmpClassTest extends PolyfillTestCase
{
    public function testDirectConstruction(): void
    {
        self::assertSame('123', \gmp_strval(new \GMP('123')));
        self::assertSame('0', \gmp_strval(new \GMP()));
        self::assertSame('26', \gmp_strval(new \GMP('1A', 16)));
    }

    public function testConstructorRejectsInvalidInput(): void
    {
        $this->expectException(\ValueError::class);
        new \GMP('not a number');
    }

    public function testObjectsHaveHandleSemantics(): void
    {
        // GMP objects are shared by handle, like ordinary PHP objects: gmp_setbit() mutates
        // the object in place and the mutation is visible through every reference to it.
        $a = \gmp_init(4);
        $b = $a;

        \gmp_setbit($a, 0);

        self::assertSame('5', \gmp_strval($a));
        self::assertSame('5', \gmp_strval($b));
    }

    public function testMutationVisibleAcrossFunctionBoundary(): void
    {
        $value = \gmp_init(4);

        (function (\GMP $x): void {
            \gmp_setbit($x, 1);
        })($value);

        self::assertSame('6', \gmp_strval($value));
    }
}
