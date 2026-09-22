<?php

declare(strict_types=1);

namespace Adapik\Polyfill\Gmp\Tests\Unit;

use Adapik\Polyfill\Gmp\Random;
use Brick\Math\BigInteger;
use PHPUnit\Framework\TestCase;

final class RandomTest extends TestCase
{
    protected function tearDown(): void
    {
        Random::resetForTesting();
    }

    public function testUnseededByDefault(): void
    {
        self::assertFalse(Random::isSeeded());
    }

    public function testSeedingMarksAsSeeded(): void
    {
        Random::seed(BigInteger::of(42));

        self::assertTrue(Random::isSeeded());
    }

    public function testSameSeedProducesSameBytes(): void
    {
        Random::seed(BigInteger::of(42));
        $first = Random::bytes(32);

        Random::seed(BigInteger::of(42));
        $second = Random::bytes(32);

        self::assertSame($first, $second);
    }

    public function testDifferentSeedsProduceDifferentBytes(): void
    {
        Random::seed(BigInteger::of(42));
        $first = Random::bytes(32);

        Random::seed(BigInteger::of(43));
        $second = Random::bytes(32);

        self::assertNotSame($first, $second);
    }

    public function testConsecutiveCallsAdvanceTheStream(): void
    {
        Random::seed(BigInteger::of(42));
        $first = Random::bytes(16);
        $second = Random::bytes(16);

        self::assertNotSame($first, $second);
    }

    public function testBytesReturnsRequestedLength(): void
    {
        Random::seed(BigInteger::of(1));

        foreach ([1, 16, 32, 33, 100] as $length) {
            self::assertSame($length, \strlen(Random::bytes($length)));
        }
    }

    public function testHugeSeedIsAccepted(): void
    {
        Random::seed(BigInteger::of('123456789012345678901234567890123456789012345678901234567890'));

        self::assertSame(16, \strlen(Random::bytes(16)));
    }

    public function testBytesBeforeSeedingThrows(): void
    {
        $this->expectException(\LogicException::class);
        Random::bytes(16);
    }

    public function testResetForTestingClearsSeededState(): void
    {
        Random::seed(BigInteger::of(42));
        self::assertTrue(Random::isSeeded());

        Random::resetForTesting();

        self::assertFalse(Random::isSeeded());
    }
}
