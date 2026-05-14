# SQL Injection Audit

Date: 2026-05-14

Scope: Laravel API route surfaces under `api/app`, `api/routes`, and security/feature tests. This audit focuses on endpoints with query parameters, filters, sort-like inputs, IDs, public tokens, and raw SQL helpers.

## Executive Result

No direct SQL injection defect was found in the reviewed API surfaces.

The reviewed controllers and services use Eloquent query binding for user-controlled filters (`where`, `whereBetween`, `whereHas`, `paginate`, validated FormRequests) and static raw SQL fragments for aggregates or database introspection. The remaining risk is not an active injection finding; it is maintainability: future dynamic sorting/filtering must stay allowlisted before reaching `orderBy`, `selectRaw`, `whereRaw`, or `DB::statement`.

## Review Method

Primary local command:

```powershell
Get-ChildItem -Path api\app,api\routes,api\tests -Recurse -Include *.php |
  Select-String -Pattern "whereRaw|orWhereRaw|orderByRaw|selectRaw|DB::raw|DB::statement|DB::select|request\(|query\(|input\(|orderBy\(" -Context 1,1
```

`rg` was attempted first, but the local Windows environment refused execution with `Erişim engellendi`; `Select-String` was used as the fallback.

## Findings

| Area | Inputs reviewed | Result | Notes |
|---|---|---|---|
| Auth and onboarding | email, password, token, locale | No injection finding | Auth uses service/FormRequest flow and token-bound routes. |
| Employee and RH indexes | `per_page`, IDs, status, month/year filters | No injection finding | IDs are cast/validated; tenant-scoped validation tests already exist for cross-tenant probing. |
| Attendance reports | `employee_id`, `date_from`, `date_to`, `month`, `per_page` | No injection finding | Queries use Eloquent bindings; date parsing can still be tightened as validation work, but not raw SQL injection. |
| Payroll and salary modules | route IDs, run IDs, self payslip IDs | No injection finding | Route params use numeric constraints; sensitive surface also uses `throttle:payroll-sensitive`. |
| Audit logs | `action`, `auditable_type`, `auditable_id`, `user_id`, `from`, `to` | No injection finding | Values are passed through bound `where` clauses; future date FormRequest would improve error shape. |
| Platform super-admin | company UUID, numeric request IDs, subscription/features payloads | No injection finding | Protected by `auth:super_admin_api`; route params are constrained where numeric. |
| AI employee search | tool `query` argument | No injection finding | `ilike` search is parameter-bound through Eloquent, not interpolated into raw SQL. |
| Webhooks and public tokens | Stripe/Chargily payloads, camera/kiosk tokens | No injection finding | No raw SQL construction from payload tokens observed in route layer. |
| Reports and dashboards | aggregate filters | No injection finding | `selectRaw` usage is static aggregate SQL, not built from request input. |
| Tenant search path | company schema, previous path | No injection finding | `Company::getSafeSearchPath()` is the invariant sanitizer for schema names; keep it mandatory. |

## Raw SQL Inventory

| Pattern | Current use | Risk classification | Required rule |
|---|---|---|---|
| `selectRaw(...)` | Static aggregate projections in reports/dashboard/feature stats. | Low | Never concatenate request input into raw projections. |
| `DB::raw(...)` | Static defaults/counts in schema helpers and aggregate stats. | Low | Static SQL only. |
| `DB::select("select to_regclass(...)")` | Public table existence checks. | Low | Static SQL only. |
| `DB::statement('SET search_path TO ...')` | Tenant switching and test schema setup. | Medium by impact, currently controlled | Only use sanitized `Company::getSafeSearchPath()` or internal fixed schema names. |
| `orderByRaw(...)` | Static CASE ordering for biometric requests. | Low | Keep static; dynamic sorting must use an allowlist map. |

## Guardrails

- Dynamic sort fields must be mapped from public names to hardcoded column names before `orderBy`.
- Dynamic report dimensions must be mapped from an allowlist before `selectRaw`, `groupBy`, or `orderByRaw`.
- Route IDs should keep `whereNumber(...)` or typed route model binding plus tenant policy checks.
- Date and status filters should prefer FormRequests for consistent `422` responses instead of database-level errors.
- No `DB::statement` may receive request input. Tenant schema switching must keep using `Company::getSafeSearchPath()`.

## Follow-Up Tests To Add When Code Changes

| Trigger | Required regression |
|---|---|
| A new endpoint accepts `sort`, `order`, `field`, `group_by`, or `select` | Test malicious sort strings and assert `422`, not SQL execution. |
| A new raw SQL report is added | Test that only allowlisted dimensions are accepted. |
| A new public token endpoint is added | Test quote/comment payloads in token fields and assert no 500 / no tenant leakage. |
| Tenant schema switching changes | Test malicious `schema_name` cannot change `search_path` beyond the sanitized tenant schema. |

## Conclusion

The Plan 14 SQL injection audit item is closed for the current codebase state. Future changes that introduce dynamic sorting, grouping, filtering, or raw SQL must update this file and add a targeted security regression test in `api/tests/Feature/Security/`.
