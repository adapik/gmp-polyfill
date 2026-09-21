<?php

declare(strict_types=1);

use Adapik\Polyfill\Gmp\NumberParser;
use Brick\Math\BigInteger;

/**
 * Userland replacement for the native GMP class, used when ext-gmp is not loaded.
 *
 * Wraps an immutable {@see BigInteger} instance. Instances are normally produced by the
 * gmp_* function shims, but direct construction is supported for parity with the native
 * GMP class, whose constructor accepts the same (string|int $num = 0, int $base = 0)
 * signature.
 */
final class GMP implements Stringable
{
    private BigInteger $value;

    /**
     * @param string|int $num  The initial value, parsed with the same rules as gmp_init().
     * @param int        $base The base to parse $num in (0 for auto-detect, or 2-62).
     *
     * @throws \ValueError If $base is out of range or $num is not a valid integer string.
     */
    public function __construct(string|int $num = 0, int $base = 0)
    {
        $this->value = NumberParser::parse((string) $num, $base, 'GMP::__construct');
    }

    /**
     * @internal Bridges the polyfill's function shims to the wrapped BigInteger value.
     */
    public static function fromBigInteger(BigInteger $value): self
    {
        $gmp = new self();
        $gmp->value = $value;

        return $gmp;
    }

    /**
     * @internal Bridges the polyfill's function shims to the wrapped BigInteger value.
     */
    public function toBigInteger(): BigInteger
    {
        return $this->value;
    }

    /**
     * @internal Used only by gmp_setbit()/gmp_clrbit(), which mutate their $num argument
     * in place to match native GMP's reference semantics.
     */
    public function internalSet(BigInteger $value): void
    {
        $this->value = $value;
    }

    public function __toString(): string
    {
        return $this->value->toBase(10);
    }

    /**
     * @return array{0: string}
     */
    public function __serialize(): array
    {
        return [$this->value->toBase(10)];
    }

    /**
     * @param array{0: string} $data
     */
    public function __unserialize(array $data): void
    {
        $this->value = BigInteger::of($data[0]);
    }
}
