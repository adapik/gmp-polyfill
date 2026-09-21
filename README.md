# adapik/gmp-polyfill

[![CI](https://github.com/adapik/gmp-polyfill/actions/workflows/ci.yml/badge.svg)](https://github.com/adapik/gmp-polyfill/actions/workflows/ci.yml)
[![Packagist Version](https://img.shields.io/packagist/v/adapik/gmp-polyfill.svg)](https://packagist.org/packages/adapik/gmp-polyfill)
[![codecov](https://codecov.io/gh/adapik/gmp-polyfill/branch/main/graph/badge.svg)](https://codecov.io/gh/adapik/gmp-polyfill)
[![License](https://img.shields.io/packagist/l/adapik/gmp-polyfill.svg)](LICENSE)

A pure-PHP polyfill for the [GMP extension](https://www.php.net/manual/en/book.gmp.php),
built on top of [`brick/math`](https://github.com/brick/math). It provides the `gmp_*()`
function API and the `GMP` class for environments where `ext-gmp` cannot be installed
(shared hosting, restricted containers, etc).

Like `symfony/polyfill-*`, this package is a *fallback*: if `ext-gmp` is already loaded,
this package registers nothing and the native extension is used, untouched. There is no
runtime cost and no behavioral difference when the real extension is present.

## Why

Some environments can't install PHP extensions at all. If your library or application
depends on `ext-gmp` for arbitrary-precision integer arithmetic, requiring this package
alongside it means your code keeps working — slower, but correctly — on those environments
too, without an `if (extension_loaded('gmp'))` branch in application code.

## Installation

```bash
composer require adapik/gmp-polyfill
```

Nothing else is required. The moment `ext-gmp` is not loaded, `gmp_*()` functions and the
`GMP` class become available, backed by this package.

## Performance

**This is a correctness-first fallback, not a performance target.** `brick/math` is pure
PHP; it does not use GMP or BCMath internally. Heavy cryptographic workloads — multi-kilobit
`gmp_powm()` for RSA, large factorials, big `gmp_nextprime()` searches — will be
**significantly slower** than the native extension, potentially by two to three orders of
magnitude for large inputs. If `ext-gmp` can be installed in your target environment, that
is always the better choice; use this package only where it genuinely cannot be.

## Feature coverage

All function signatures, argument types, and error types (`\ValueError`,
`\DivisionByZeroError`) match native GMP as closely as PHP allows. Differences are called
out explicitly below; anything not listed here behaves identically to native GMP as
verified by this package's differential test suite (see [Testing](#testing)).

| Function | Status | Notes |
|---|---|---|
| `gmp_init`, `gmp_intval`, `gmp_strval` | ✅ | Bases 0 (auto-detect), 2–62 for input; 2–62 and -2..-36 for output. See [`gmp_intval` caveat](#gmp_intval-on-overflow) below. |
| `gmp_import`, `gmp_export` | ✅ | Full word-size/endianness flag support (`GMP_MSW_FIRST`, `GMP_LSW_FIRST`, `GMP_BIG_ENDIAN`, `GMP_LITTLE_ENDIAN`, `GMP_NATIVE_ENDIAN`). |
| `gmp_add`, `gmp_sub`, `gmp_mul`, `gmp_neg`, `gmp_abs` | ✅ | |
| `gmp_div_q`, `gmp_div_r`, `gmp_div_qr`, `gmp_div`, `gmp_divexact`, `gmp_mod` | ✅ | All three `GMP_ROUND_*` modes supported; `gmp_mod()` always returns a non-negative result (Euclidean), matching native. |
| `gmp_pow`, `gmp_powm` | ✅ | |
| `gmp_sqrt`, `gmp_sqrtrem` | ✅ | |
| `gmp_root`, `gmp_rootrem` | ✅ | |
| `gmp_fact`, `gmp_binomial` | ✅ | `gmp_binomial()` supports negative `n` via the standard reflection identity. |
| `gmp_cmp`, `gmp_sign` | ✅ | |
| `gmp_and`, `gmp_or`, `gmp_xor`, `gmp_com` | ✅ | Two's-complement semantics for negative operands, matching native. |
| `gmp_setbit`, `gmp_clrbit`, `gmp_testbit` | ✅ | `GMP` objects have reference semantics like native (mutating a `GMP` via `gmp_setbit()` is visible through every reference to that object), matching native GMP. |
| `gmp_scan0`, `gmp_scan1`, `gmp_popcount`, `gmp_hamdist` | ✅ | `gmp_popcount()` of a negative number returns -1 (undefined), matching native. |
| `gmp_gcd`, `gmp_lcm`, `gmp_gcdext`, `gmp_invert` | ✅ | `gmp_gcdext()` returns the same `['g' => ..., 's' => ..., 't' => ...]` shape as native. |
| `gmp_jacobi`, `gmp_legendre`, `gmp_kronecker` | ✅ | Hand-implemented (brick/math has no number-theoretic symbol support); verified against native for a wide range of small/negative/zero inputs. |
| `gmp_nextprime`, `gmp_prob_prime` | ✅ | Hand-implemented Miller-Rabin (trial division + configurable rounds). May classify a composite as "probably prime" with the native-documented small probability; deterministic ("2", definitely prime) only for numbers with small factors. |
| `gmp_perfect_square`, `gmp_perfect_power` | ✅ | |
| `gmp_random_bits`, `gmp_random_range` | ✅ | Sourced from `random_bytes()`/PHP's CSPRNG via `brick/math`, never `mt_rand()`. |
| `gmp_random_seed` | ⚠️ Accepted, no-op | See [Random number caveat](#gmp_random_seed-is-a-no-op) below. |
| `gmp_random` | ❌ Not implemented | Removed from PHP itself (superseded by `gmp_random_bits()`/`gmp_random_range()`); not part of the current PHP manual. |
| `GMP` class (`__construct`, `__toString`, `serialize`/`unserialize`) | ✅ | Directly constructible with `new GMP($num, $base)`, matching native's actual (public) constructor. |

### `gmp_intval()` on overflow

PHP's own manual and native GMP's behavior agree that converting a `GMP` value too large
for a PHP `int` is **undefined** — and empirically, native GMP's result isn't even a
consistent bit-truncation (`2^63`, `2^64`, and `2^128` all convert to `0` natively on the
GMP 6.3 / PHP 8.5 combination this was verified against). This package instead applies a
well-defined two's-complement truncation to 64 bits. Code that relies on `gmp_intval()` for
values within `PHP_INT_MIN`..`PHP_INT_MAX` is unaffected; code relying on a specific
overflow result was already relying on undefined behavior.

### `gmp_random_seed()` is a no-op

Native GMP seeds a Mersenne Twister PRNG, making subsequent `gmp_random_*()` calls
reproducible. This package sources randomness from `random_bytes()` (a CSPRNG) via
`brick/math`, by design — per this project's requirement to never use `mt_rand()` or another
non-cryptographic source. A CSPRNG cannot be meaningfully reseeded to a reproducible
sequence without compromising the guarantee that makes it suitable for cryptographic use, so
`gmp_random_seed()` is accepted (for API compatibility, so it doesn't fatal) but has no
effect. **Do not rely on this package for reproducible/seeded random sequences.**

### Not implemented

- `gmp_random()` — removed from the PHP manual (superseded by `gmp_random_bits()` /
  `gmp_random_range()`); not implemented here either.

## Requirements

- PHP 8.1+ (the range `brick/math`'s own `composer.json` supports across its `^0.12` and
  `^1.0` lines; composer will resolve the right `brick/math` version for your PHP version
  automatically). CI covers PHP 8.1–8.5.
- No PHP extensions required.

## Testing

This package is tested three ways:

1. **Unit tests** (`tests/Unit`) — test the internal number parser/formatter directly,
   independent of whether `ext-gmp` is loaded.
2. **Differential tests** (`tests/Differential`) — run on any machine with `ext-gmp`
   installed. They call this polyfill's internal `Calculator` class directly (never the
   global `gmp_*()` functions, which would resolve to the native extension) and assert
   identical results against the real `gmp_*()` functions, across edge cases (zero,
   negatives, division/modulo sign behavior, multi-thousand-bit numbers, every supported
   base, invalid input) and randomized fuzzing.
3. **Polyfill tests** (`tests/Polyfill`) — run with `ext-gmp` *disabled*, exercising the
   actual global `gmp_*()` functions and the `GMP` class as application code would see them.

```bash
composer install

# Everything applicable to your current PHP setup (tests for the "other" extension
# state self-skip):
composer test

# Explicitly run a suite:
vendor/bin/phpunit --testsuite unit,polyfill      # with ext-gmp disabled
vendor/bin/phpunit --testsuite unit,differential  # with ext-gmp enabled (the oracle)

composer phpstan   # static analysis, level max
composer cs-check   # PSR-12 style check
```

CI runs the full matrix (PHP 8.1–8.5, with and without `ext-gmp`), a `--prefer-lowest`
dependency job, PHPStan at max level, code style, `composer validate`, and
`composer audit`.

## How to extend

All GMP-specific logic (parsing, formatting, bitwise/number-theory algorithms) lives in
[`Adapik\Polyfill\Gmp\Calculator`](src/Calculator.php), which works purely in terms of
`Brick\Math\BigInteger` — it never touches the global `GMP` class directly, which is what
keeps it testable in both extension states. The global `gmp_*()` functions in
[`src/bootstrap.php`](src/bootstrap.php) are one-line wrappers around `Calculator`, each
guarded by `function_exists()`. The `GMP` class itself
([`src/Resources/stubs/GMP.php`](src/Resources/stubs/GMP.php)) is a thin value object
wrapping a `BigInteger`.

To add or fix a function:

1. Add/fix the logic in `Calculator` (pure `BigInteger` in, `BigInteger`/scalar out).
2. Add or update the one-line shim in `bootstrap.php`.
3. Add differential test cases in `tests/Differential` comparing against native `gmp_*()`.
4. If the function's behavior is genuinely native-GMP-version-dependent or undefined (like
   `gmp_intval()` overflow), document the deviation in this README's feature table rather
   than chasing an inconsistent target.

## License

MIT. See [LICENSE](LICENSE).
