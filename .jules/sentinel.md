## 2026-05-21 - PostgreSQL search_path SQL Injection Fix
**Vulnerability:** SQL Injection via PostgreSQL `SET search_path` statements. Multiple locations in the codebase were concatenating `schema_name` directly into SQL statements.
**Learning:** PostgreSQL identifiers (like schema names) cannot be parameterized in `DB::statement`. While some parts of the app attempted to sanitize inputs using `preg_replace`, many others were completely unprotected, and there was no centralized standard for safe schema switching.
**Prevention:** Use the centralized `Company::getSafeSearchPath(string $schema)` helper for all `SET search_path` calls. It ensures identifiers are properly double-quoted and internal double-quotes are escaped.
