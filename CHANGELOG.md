# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Initial implementation of the `gmp_*()` function API and the `GMP` class as a pure-PHP
  polyfill backed by `brick/math`, registered only when `ext-gmp` is not loaded.
- Construction and conversion: `gmp_init`, `gmp_intval`, `gmp_strval`, `gmp_import`,
  `gmp_export` (bases 0 and 2–62 for input; 2–62 and -2..-36 for output; full
  word-size/endianness support for import/export).
- Arithmetic: `gmp_add`, `gmp_sub`, `gmp_mul`, `gmp_div_q`, `gmp_div_r`, `gmp_div_qr`,
  `gmp_div`, `gmp_divexact`, `gmp_mod`, `gmp_pow`, `gmp_powm`, `gmp_neg`, `gmp_abs`,
  `gmp_sqrt`, `gmp_sqrtrem`, `gmp_fact`, `gmp_binomial`, `gmp_root`, `gmp_rootrem`.
- Comparison: `gmp_cmp`, `gmp_sign`.
- Bitwise: `gmp_and`, `gmp_or`, `gmp_xor`, `gmp_com`, `gmp_setbit`, `gmp_clrbit`,
  `gmp_testbit`, `gmp_scan0`, `gmp_scan1`, `gmp_popcount`, `gmp_hamdist`.
- Number theory: `gmp_gcd`, `gmp_gcdext`, `gmp_lcm`, `gmp_invert`, `gmp_jacobi`,
  `gmp_legendre`, `gmp_kronecker`, `gmp_nextprime`, `gmp_prob_prime`,
  `gmp_perfect_square`, `gmp_perfect_power`.
- Random: `gmp_random_bits`, `gmp_random_range`, `gmp_random_seed` (accepted, no-op;
  see README), sourced from `random_bytes()`.
- Differential test suite comparing every function against native `ext-gmp` output across
  edge cases and randomized fuzzing.
- GitHub Actions CI: PHP 8.1–8.5 matrix with and without `ext-gmp`, a `--prefer-lowest`
  job, PHPStan at max level, PSR-12 style check, `composer validate`, `composer audit`.

[Unreleased]: https://github.com/adapik/gmp-polyfill/commits/main
