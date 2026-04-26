# Scout Learnings

## Security Regression Guards
- **Middleware Stale State**: When performing security checks in a Middleware (like `TenantMiddleware`), never rely on the `$request->user()` object's properties without calling `fresh()`. Authenticated user objects are often cached in memory for the duration of the request or across some sessions, which can lead to security holes if the user's status (e.g., `archived` or `suspended`) changed after the token was issued but before the current request.
- **Priority Areas**: Always prioritize regression tests for:
    1. Tenant isolation (data leakage).
    2. Authentication guardrails (blocking of inactive accounts).
    3. RBAC (unauthorized access to manager/admin routes).

## Test Environment Stability
- **SQLite Compatibility**: In monorepos or environments using SQLite for fast feature testing, PostgreSQL-specific commands like `SET search_path` must always be guarded with `if (DB::getDriverName() === 'pgsql')`. Failure to do so will break the test suite in CI or local development.
- **Bootstrap Requirements**: Backend tests often require a valid `.env` file even when using in-memory databases. Always ensure `api/.env` is present (copy from `.env.example` if missing) before running `php artisan test`.
