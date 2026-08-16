# Feature Specification: /i18n/catalog — bucket rate-limit dédié (Closes #4501)

**Issue**: #4501 (P3, api, perf) — le catalogue i18n pré-login partageait le bucket auth-sensitive
10/min avec le login (self-DoS derrière NAT : échecs de login → UI sans traductions).

## Fix
- Routes /i18n/catalog[/{locale}] déplacées du groupe `throttle:auth-sensitive` vers
  `throttle:public-registry` (60/min/IP, indépendant).

## Tests
- `test_catalog_has_its_own_rate_limit_bucket` : bucket auth épuisé (429) → catalogue 200.
