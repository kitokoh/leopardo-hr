# Admin Dashboard CSRF/XSS Audit

Date: 2026-05-14

Scope: `front/admin-dashboard/src`, `front/admin-dashboard/e2e`, admin API client configuration, and backend CORS/Sanctum posture relevant to the admin dashboard.

## Executive Result

No active XSS sink was found in the reviewed admin dashboard source: no `v-html`, `innerHTML`, `outerHTML`, `document.write`, `eval`, or `new Function` usage was present in `src` or `e2e`.

Classic browser CSRF risk is low for the admin dashboard because the current admin API client sends a Bearer token in the `Authorization` header, not ambient cookies. Backend CORS also restricts credentialed origins through `config/cors.php`.

The main residual risk is token exposure if a future XSS sink is introduced, because the admin token is currently stored in `localStorage`. The immediate hardening is therefore to keep dangerous DOM/script APIs blocked in lint and to revisit token storage when platform auth moves to a cookie-backed session.

## Review Method

Primary local searches:

```powershell
Get-ChildItem -Path front\admin-dashboard\src,front\admin-dashboard\e2e -Recurse -Include *.vue,*.ts,*.js |
  Select-String -Pattern "v-html|innerHTML|outerHTML|dangerouslySetInnerHTML|document\.write|eval\(|new Function|sanitize|DOMPurify|csrf|xsrf|X-XSRF|withCredentials|axios" -Context 1,1
```

```powershell
Get-ChildItem -Path front\admin-dashboard\src -Recurse -Include *.vue |
  Select-String -SimpleMatch -Pattern "v-html","innerHTML","outerHTML","eval(","new Function","@submit","v-model" -Context 1,1
```

Note: an initial broad scan including `node_modules` timed out locally; the effective audit scope was narrowed to source and E2E files.

## Findings

| Area | Result | Notes |
|---|---|---|
| HTML injection sinks | No active sink found | Vue templates use mustache rendering and bindings; no `v-html` in reviewed source. |
| Script execution sinks | No active sink found | No `eval`, `new Function`, or `document.write` in reviewed source. |
| Admin forms | No CSRF form issue found | Forms use Vue `@submit.prevent` and call the API client; no browser-native cross-site form POST to cookie-auth endpoints. |
| API auth transport | CSRF low, XSS impact medium | `front/admin-dashboard/src/services/api.js` sends `Authorization: Bearer <token>` from `localStorage`. |
| CORS | Acceptable current posture | `api/config/cors.php` limits credentialed origins to `FRONTEND_URL` and `APP_URL`; `supports_credentials` is enabled for Sanctum-compatible flows. |
| Toast/error rendering | No sink found | API errors are passed as strings to toast; avoid rendering server messages as HTML in future UI libraries. |

## Hardening Added

`front/admin-dashboard/.eslintrc.cjs` now blocks the risky primitives that would reopen this class of issue:

- `vue/no-v-html`
- `no-eval`
- `no-implied-eval`
- `no-new-func`
- `no-script-url`

## Guardrails

- Do not introduce `v-html` without a documented sanitizer and security review.
- Do not store or render server-provided rich HTML in admin components.
- Keep admin API calls on explicit `Authorization` headers until a deliberate cookie-session migration is designed.
- If admin auth moves to cookies, add CSRF token acquisition, SameSite policy documentation, and Playwright/API regression coverage.
- Treat any XSS in admin as credential compromise while tokens remain in `localStorage`.

## Follow-Up

The current audit closes the Plan 14 CSRF/XSS verification item for the admin dashboard. A future auth-storage hardening lot should evaluate HttpOnly SameSite cookies for the platform admin token, but that requires coordinated backend/frontend auth changes rather than a lint-only patch.
