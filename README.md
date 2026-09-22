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

## Restrictions

Everything below is a known, deliberate, documented deviation from native GMP — not a bug.
Each is covered in detail further down; this is the checklist to read before depending on
this package in place of the real extension.

- **Performance.** Two to three orders of magnitude slower than native for large inputs.
  See [Performance](#performance) above.
- **`gmp_intval()` overflow is well-defined here, but differently from native's undefined
  behavior.** See [below](#gmp_intval-on-overflow).
- **`gmp_random_seed()` makes randomness deterministic *and* global for the rest of the
  process — not just for the call that follows it.** Calling it anywhere disables the
  CSPRNG for every later `gmp_random_bits()`/`gmp_random_range()` call in that process,
  and in worker-mode SAPIs (Swoole, RoadRunner, Laravel Octane, ReactPHP) "that process"
  can span many unrelated requests. See [below](#gmp_random_seed-reproducibility).
- **`gmp_random_seed()`'s sequence is reproducible only within this polyfill**, not
  bit-compatible with native GMP's seeded output. Fixtures recorded against native GMP with
  a given seed will not reproduce against this package, or vice versa.
- **`gmp_prob_prime()`/`gmp_nextprime()` use a hand-written Miller-Rabin test**, not GMP's
  exact algorithm. Results agree on primality, but the "definitely prime" (`2`) vs.
  "probably prime" (`1`) classification threshold may differ from native for a given input.
- **`gmp_jacobi()`/`gmp_legendre()`/`gmp_kronecker()` are hand-implemented** (`brick/math`
  has no built-in number-theoretic symbol support). Verified against native across a wide
  differential test sweep, but without the decades of production hardening native GMP has.
- **`gmp_random()` is not implemented** — it has no native counterpart in any PHP version
  this package supports (removed in PHP 8.0). See [below](#not-implemented).
- **No cryptographic acceleration, no side-channel hardening.** `brick/math`'s arithmetic
  is not constant-time. Do not use this package as a drop-in for native GMP in
  timing-sensitive cryptographic code paths, regardless of which is faster.

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
| `gmp_random_bits`, `gmp_random_range` | ✅ | Sourced from `random_bytes()`/PHP's CSPRNG via `brick/math`, never `mt_rand()` — unless seeded, see below. |
| `gmp_random_seed` | ⚠️ Implemented, not native-compatible | Makes subsequent random calls reproducible *within this polyfill only*, and globally for the rest of the process. See [below](#gmp_random_seed-reproducibility). |
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

### `gmp_random_seed()` reproducibility

By default, `gmp_random_bits()`/`gmp_random_range()` source randomness from `random_bytes()`
(a CSPRNG) — never `mt_rand()`. A CSPRNG cannot be meaningfully seeded to a reproducible
sequence without compromising the guarantee that makes it suitable for cryptographic use, so
`gmp_random_seed()` switches to a **different, hand-written generator**: SHA-256 in counter
mode, seeded from the value you pass in
([`Adapik\Polyfill\Gmp\Random`](src/Random.php)). This is deliberately not `mt_rand()` (per
this project's requirement to never use it) and not a reimplementation of native GMP's
Mersenne-Twister generator — it's a third algorithm, written for this package, whose only
property is being reproducible from a seed.

Concretely, calling `gmp_random_seed($seed)`:

- Makes every subsequent `gmp_random_bits()`/`gmp_random_range()` call in the process
  produce the same sequence for the same seed — call `gmp_random_seed(42)` twice, each
  followed by `gmp_random_bits(256)`, and you get identical output both times.
- Does **not** reproduce native GMP's output for the same seed. There is no bit-for-bit
  compatibility between this generator and native GMP's Mersenne Twister, and none is
  claimed — fixtures recorded against one will not reproduce against the other.
- Is **global and sticky for the rest of the process**, exactly like native GMP: it is not
  scoped to a function, a request, or a test. Every later call to
  `gmp_random_bits()`/`gmp_random_range()` anywhere in that process — including in
  unrelated code you don't control — becomes deterministic and **no longer
  cryptographically secure**. There is no `gmp_random_unseed()` in native GMP, and none
  here either. If you need real randomness again in the same process after seeding, you
  currently cannot get it through the `gmp_random_*()` API at all.
- **In worker-mode SAPIs (Swoole, RoadRunner, Laravel Octane, ReactPHP, etc.) "the rest of
  the process" can span many unrelated HTTP requests**, since the PHP process — and this
  package's static state — outlives any single request. Seeding during one request leaves
  every later request handled by that same worker with deterministic, non-cryptographic
  randomness until the worker restarts. This does not affect traditional per-request SAPIs
  (PHP-FPM, mod_php, CLI), where static state resets at the end of each request.

**Only call `gmp_random_seed()` for reproducible tests or simulations, never in a process
that also needs `gmp_random_bits()`/`gmp_random_range()` to stay cryptographically secure.**

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

1. **Unit tests** (`tests/Unit`) — test `Calculator`, `NumberParser`, and `Random` directly
   against known-correct fixed expectations, independent of whether `ext-gmp` is loaded
   (these classes never touch the global `GMP` class - see
   [How to extend](#how-to-extend)).
2. **Differential tests** (`tests/Differential`) — run on any machine with `ext-gmp`
   installed. They call `Calculator` directly (never the global `gmp_*()` functions, which
   would resolve to the native extension) and assert identical results against the real
   `gmp_*()` functions, across edge cases (zero, negatives, division/modulo sign behavior,
   multi-thousand-bit numbers, every supported base, invalid input) and randomized fuzzing.
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
wrapping a `BigInteger`. [`Adapik\Polyfill\Gmp\Random`](src/Random.php) is the one piece of
mutable global state in the package - the seeded generator behind `gmp_random_seed()`.

To add or fix a function:

1. Add/fix the logic in `Calculator` (pure `BigInteger` in, `BigInteger`/scalar out).
2. Add or update the one-line shim in `bootstrap.php`.
3. Add a `tests/Unit` case with a fixed, known-correct expectation (runs regardless of
   `ext-gmp`) and differential test cases in `tests/Differential` comparing against native
   `gmp_*()`.
4. If the function's behavior is genuinely native-GMP-version-dependent or undefined (like
   `gmp_intval()` overflow), document the deviation in this README's feature table and the
   [Restrictions](#restrictions) section rather than chasing an inconsistent target.

## License

MIT. See [LICENSE](LICENSE).
