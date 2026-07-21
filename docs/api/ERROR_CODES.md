# API Error Codes — Leopardo RH

This page documents the custom, machine-readable error codes returned by the Leopardo RH API,
in addition to standard HTTP status codes. All error responses share the same JSON envelope:

```json
{
  "error": "RESOURCE_NOT_FOUND",
  "message": "RESOURCE_NOT_FOUND",
  "localized_message": "La ressource demandee est introuvable."
}
```

- `error` / `message` — the machine-readable error code (stable, safe to branch on in client code).
- `localized_message` — a human-readable message translated to the caller's active locale
  (`fr`, `en`, `ar`, `tr` — see [`api/lang/`](../../api/lang/)).
- Validation errors (HTTP 422 from a failed `FormRequest`) additionally include an `errors` object
  keyed by field name, in the standard Laravel validation-errors shape.

Source of truth in code:
- [`app/Shared/Enums/ApiError.php`](../../api/app/Shared/Enums/ApiError.php) — the canonical enum of
  API error codes with their HTTP status and default (English) message.
- [`app/Exceptions/DomainException.php`](../../api/app/Exceptions/DomainException.php) and its
  subclasses (in `app/Exceptions/` and `app/Modules/*/Domain/Exceptions/`) — domain-specific
  exceptions that carry their own error code + HTTP status, rendered by the global exception
  handler in [`bootstrap/app.php`](../../api/bootstrap/app.php).
- [`lang/{locale}/api_errors.php`](../../api/lang/fr/api_errors.php) and
  [`lang/{locale}/errors.php`](../../api/lang/fr/errors.php) — localized messages for each code.

## `ApiError` enum codes

These are the general-purpose codes defined in `App\Shared\Enums\ApiError`, used across
authentication, authorization, and generic validation/business-logic failures.

### Authentication (401)

| Code | Meaning |
| --- | --- |
| `INVALID_CREDENTIALS` | Email or password is incorrect on login. |
| `TOKEN_EXPIRED` | The Sanctum bearer token has expired. |
| `TOKEN_INVALID` | The Sanctum bearer token is malformed or unknown. |
| `UNAUTHENTICATED` | No valid credentials were provided for a protected endpoint. |
| `TWO_FA_REQUIRED` | Login succeeded but a 2FA code is required to complete authentication. |
| `TWO_FA_INVALID` | The submitted 2FA code is incorrect. |
| `ACCOUNT_LOCKED` | Account temporarily locked after too many failed login attempts. |
| `ACCOUNT_SUSPENDED` | Account has been suspended by an administrator. |

### Authorization (403)

| Code | Meaning |
| --- | --- |
| `FORBIDDEN` | Generic "not allowed to perform this action" (also returned for Laravel's `AuthorizationException`). |
| `MANAGER_REQUIRED` | Endpoint requires manager-level access; caller is a plain employee. |
| `INSUFFICIENT_ROLE` | Caller's role does not grant access to this resource. |
| `TENANT_MISMATCH` | The resource belongs to a different company/tenant than the caller's. |
| `SUPER_ADMIN_REQUIRED` | Endpoint requires platform (super-admin) access, not just company-manager access. |
| `POLICY_DENIED` | A Laravel policy explicitly denied the action on this resource. |

### Not Found (404)

| Code | Meaning |
| --- | --- |
| `RESOURCE_NOT_FOUND` | Generic not-found (also returned for Eloquent's `ModelNotFoundException` and any 404 `HttpException`). |
| `EMPLOYEE_NOT_FOUND` | The referenced employee does not exist (or is outside the caller's tenant). |
| `COMPANY_NOT_FOUND` | The referenced company does not exist. |
| `USER_NOT_FOUND` | The referenced platform user does not exist. |

### Validation (422)

| Code | Meaning |
| --- | --- |
| `VALIDATION_FAILED` | Generic validation failure outside of a `FormRequest` (see also `VALIDATION_ERROR` below for the `FormRequest`/`ValidationException` path). |
| `INVALID_STATUS_TRANSITION` | The requested status change is not a valid transition for this resource's state machine. |
| `DUPLICATE_ENTRY` | A conflicting/duplicate record already exists (also mapped to HTTP 409, see below). |
| `INVALID_DATE_RANGE` | `start_date`/`end_date` (or similar) form an invalid range. |
| `INSUFFICIENT_BALANCE` | Not enough balance (e.g. leave balance, loan balance) for the requested operation. |

### Business logic (409 or 422 depending on code — see table)

| Code | HTTP | Meaning |
| --- | --- | --- |
| `ALREADY_APPROVED` | 409 | The request/record has already been approved; the action is not repeatable. |
| `ALREADY_REJECTED` | 409 | The request/record has already been rejected. |
| `ALREADY_LINKED` | 409 | The link/association being created already exists. |
| `ALREADY_ENABLED` | 409 | The feature/toggle is already enabled. |
| `CONTRACT_EXPIRED` | 422 | The employee's contract has expired and blocks this operation. |
| `PAYROLL_ALREADY_VALIDATED` | 422 | The payroll run has already been validated and can no longer be modified. |
| `SUBSCRIPTION_INACTIVE` | 422 | The company's subscription/plan is not active. |
| `TRIAL_EXPIRED` | 422 | The company's trial period has ended. |
| `INVITATION_ALREADY_ACCEPTED` | 422 | The invitation link has already been used. |
| `TOO_MANY_PENDING_REQUESTS` | 422 | Caller has reached the limit of concurrent pending requests of this type. |
| `SHARE_EXPIRED` | 422 | A shared/public link (e.g. document share) has expired. |

### Passwords (422)

| Code | Meaning |
| --- | --- |
| `INVALID_CURRENT_PASSWORD` | The current password supplied when changing password is incorrect. |
| `INVALID_PASSWORD` | The new password does not meet the required policy. |
| `SETUP_REQUIRED` | Initial account/company setup must be completed before this action. |

### Rate limiting (429)

| Code | Meaning |
| --- | --- |
| `RATE_LIMITED` | Too many requests; see the response `Retry-After` header (standard Laravel throttle response for `429`). Distinct named limiters exist per surface (`api`, `api-plan`, `auth-sensitive`, `privacy-sensitive`, `payroll-sensitive`, `platform-sensitive`, `ai-sensitive`, `client-analytics`, `webhooks-inbound`) — see `App\Providers\AppServiceProvider::boot()`. |

### Server errors (500)

| Code | Meaning |
| --- | --- |
| `INTERNAL_ERROR` | Unhandled server-side error. |
| `SERVICE_UNAVAILABLE` | A dependency (queue, cache, third-party service) is temporarily unavailable. |
| `PDF_GENERATION_FAILED` | PDF rendering (contracts, pay slips, reports) failed. |
| `EXPORT_FAILED` | A CSV/XLSX export job failed. |
| `EXTERNAL_SERVICE_ERROR` | An upstream third-party API call failed (payment provider, SMS/email gateway, etc). |

## Framework-level codes (not in the `ApiError` enum)

Returned directly by the global exception handler in `bootstrap/app.php` for framework-level
exceptions, before any domain logic runs:

| Code | HTTP | Trigger |
| --- | --- | --- |
| `VALIDATION_ERROR` | 422 | Laravel's `ValidationException` (a `FormRequest` failed validation). Response includes an `errors` object keyed by field. Also returned (with a fixed `file` field error) when `PostTooLargeException` is thrown for an oversized upload. |
| `RESOURCE_NOT_FOUND` | 404 | Eloquent `ModelNotFoundException`, or any `HttpExceptionInterface` with status 404 (e.g. an undefined route). |
| `FORBIDDEN` | 403 | Laravel's `AuthorizationException` (a Gate/Policy check failed via `$this->authorize(...)`). |
| *(exception message)* | varies | Any other `HttpExceptionInterface` (e.g. `abort(422, 'Custom message')`) — `error`/`message` fall back to the exception's message, or `HTTP_ERROR` if empty. |
| `UNSUPPORTED_API_VERSION` | 400 | `ApiVersionMiddleware` — the `X-API-Version` header (or route version segment) requests an API version that isn't `v1`. Response also includes `supported_versions` and `requested_version`. |

## Domain-specific codes (`DomainException` subclasses)

Feature modules throw dedicated exceptions extending `App\Exceptions\DomainException`, each
carrying its own error code and HTTP status. These are rendered by the same `DomainException`
handler in `bootstrap/app.php` and use the same JSON envelope as the `ApiError` codes above.
Non-exhaustive list of the ones with a stable, documented code (grep `App\Exceptions\*Exception`
and `App\Modules\*\Domain\Exceptions\*Exception` in the codebase for the full, evolving list —
several module-specific exceptions currently rely on their raw exception message rather than a
dedicated code, and are candidates for future codes as they're standardized):

| Code | HTTP | Module | Meaning |
| --- | --- | --- | --- |
| `ACCOUNT_LOCKED` | 423 | Auth | Account locked until a specific time after repeated failed logins (includes lock expiry in the message). |
| `ACCOUNT_SUSPENDED` | 403 | Auth | Employee account has been suspended. |
| `EMPLOYEE_NOT_ACTIVE` | 403 | HR | The employee account exists but is not in `active` status. |
| `COMPANY_NOT_FOUND` | 403 | Tenant | Company could not be resolved for the current request context. |
| `INVALID_CREDENTIALS` | 401 | Auth | Login failed (domain-level variant of the `ApiError` code above). |
| `EMPLOYEE_NOT_FOUND` | 404 | HR | Employee lookup by ID failed. |
| `ABSENCE_NOT_PENDING` | 422 | Planning | Attempted to approve/reject an absence request that isn't `pending`. |
| `ABSENCE_DATE_CONFLICT` | 422 | Planning | Requested absence dates overlap an existing approved absence. |
| `INSUFFICIENT_LEAVE_BALANCE` | 422 | Planning | Employee does not have enough leave balance for the requested absence. |
| `ALREADY_CHECKED_IN` | 422 | Attendance | Employee already has an open check-in for the day. |
| `MISSING_CHECK_IN` | 422 | Attendance | Attempted to check out without a prior check-in. |
| `PUNCH_PHOTO_REQUIRED` | 422 | Attendance | Company policy requires a photo on punch, and none was provided. |
| `PAYROLL_ALREADY_VALIDATED` | 422 | Payroll | Payroll run has already been validated; further edits are blocked. |
| `PAYROLL_PERIOD_CONFLICT` | 422 | Payroll | A payroll run already exists for the requested period. |
| `ADVANCE_EXCEEDS_SALARY` | 422 | Payroll | Requested salary advance exceeds the employee's eligible salary amount. |
| `ADVANCE_NOT_PENDING` | 422 | Payroll | Attempted to approve/reject a salary advance that isn't `pending`. |
| `APPLICANT_NOT_FOUND` | 404 | Recruitment | Job applicant lookup by ID failed. |
| `JOB_POSTING_NOT_FOUND` | 404 | Recruitment | Job posting lookup by ID failed. |

## Adding a new error code

1. Prefer adding a case to `App\Shared\Enums\ApiError` for generic, cross-module codes — it
   already wires up status code, default English message, and localized message lookup.
2. For module-specific business rules, extend `App\Exceptions\DomainException` (or a module's
   existing base exception) and pass a stable `SCREAMING_SNAKE_CASE` code to the parent
   constructor (or override `errorCode()`), matching the pattern in the table above.
3. Add the code's localized message to `lang/{fr,en,ar,tr}/api_errors.php` or `errors.php`
   (whichever the exception's handler reads from) so all supported locales stay in sync.
4. Update this file with the new code, its HTTP status, and its meaning.
