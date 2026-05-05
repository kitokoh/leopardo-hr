# 🛡️ Sentinel Security Journal

## 2026-05-01 - SQL Injection in PostgreSQL search_path
**Vulnerability:** Raw string interpolation of `$company->schema_name` into `DB::statement("SET search_path TO ...")` calls across multiple controllers and requests.
**Learning:** Even internal metadata like schema names can become an injection vector if not properly sanitized, especially in multi-tenant architectures where schema names might be derived from user-controlled or less-trusted fields. Since schema identifiers cannot be parameterized in standard SQL, they must be rigorously whitelisted and quoted.
**Prevention:** Always use a central, secure helper like `Company::getSafeSearchPath()` which enforces a strict alphanumeric/underscore whitelist and applies double-quoting to identifiers. Never trust model fields in raw `DB::statement` calls.

## 2026-05-04 - IDOR in Multi-tenant Index Filters
**Vulnerability:** Many index endpoints (Absences, Payroll, etc.) allowed filtering by `employee_id` without validating that the target employee belonged to the requester's company.
**Learning:** Global scopes (like `BelongsToCompany`) protect the *results* of a query, but they don't necessarily prevent validation-layer probing. An attacker could potentially confirm the existence of an ID in another tenant if the validator doesn't scope its `exists` checks.
**Prevention:** Always scope foreign key validation in Request classes. Use `Rule::exists('table', 'id')->where('company_id', $user->company_id)` for any user-provided IDs used in filters or commands.
