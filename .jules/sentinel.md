# 🛡️ Sentinel Security Journal

## 2026-05-01 - SQL Injection in PostgreSQL search_path
**Vulnerability:** Raw string interpolation of `$company->schema_name` into `DB::statement("SET search_path TO ...")` calls across multiple controllers and requests.
**Learning:** Even internal metadata like schema names can become an injection vector if not properly sanitized, especially in multi-tenant architectures where schema names might be derived from user-controlled or less-trusted fields. Since schema identifiers cannot be parameterized in standard SQL, they must be rigorously whitelisted and quoted.
**Prevention:** Always use a central, secure helper like `Company::getSafeSearchPath()` which enforces a strict alphanumeric/underscore whitelist and applies double-quoting to identifiers. Never trust model fields in raw `DB::statement` calls.

## 2026-05-03 - Cross-Tenant Identity Hijacking via Email Collision
**Vulnerability:** The platform used a global `user_lookups` table for authentication routing (email -> company_id) but only enforced email uniqueness at the local company level. This allowed an attacker in one tenant to "claim" an email already used in another tenant, overwriting the routing record and hijacking the authentication flow.
**Learning:** In multi-tenant systems with centralized authentication registries, uniqueness must be enforced globally across the registry, not just within isolated tenant silos. Validation rules must check the global registry, and the registry synchronization logic must have defensive checks against overwriting records belonging to different internal IDs.
**Prevention:** Use a `GlobalEmailUnique` validation rule that queries the centralized lookup table. Implement "defense in depth" in model observers/sync methods to ensure a tenant-specific update cannot affect a global record owned by another tenant.
