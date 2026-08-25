# Authenticated Playwright fixture

## Import

```ts
import { expect, test } from './fixtures/authenticated';
```

The `authenticatedPage` fixture installs a deterministic manager session before each test. It sets the `leopardo_token` httpOnly cookie on the configured `BASE_URL`, hydrates `auth_user`, mocks `GET /api/v1/auth/me`, and mocks the dashboard-wide `notifications`, `announcements`, `auth/login`, and `auth/logout` requests.

## Basic usage

```ts
import { expect, test } from './fixtures/authenticated';

test('manager can open payroll', async ({ authenticatedPage: page }) => {
  await page.route('**/api/v1/pay-slips**', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ data: [], meta: { total: 0 } }),
    });
  });

  await page.goto('/payroll', { waitUntil: 'domcontentloaded' });
  await expect(page).toHaveURL(/\/payroll$/);
});
```

## Feature-lock usage

For a locked module, override the user before the page is navigated to the target route. The default fixture is already installed when the test body starts, so tests that need a different user should call `installAuthenticatedSession` explicitly with `mockAuthMe: true` after registering any feature-specific routes, or use a dedicated fixture variant.

```ts
import { expect, test, installAuthenticatedSession } from './fixtures/authenticated';

test('locked payroll renders the upgrade panel', async ({ page }) => {
  await installAuthenticatedSession(page, {
    user: {
      capabilities: { employees: true, payroll: false },
      company: {
        id: 'company-1',
        name: 'TechCorp Algerie SARL',
        features: { employees: true, payroll: false },
      },
    },
  });

  await page.goto('/payroll', { waitUntil: 'domcontentloaded' });
  await expect(page.getByTestId('feature-locked-panel')).toBeVisible();
});
```

## Diagnostics

To diagnose a preview redirect without exposing credentials, install the optional diagnostics before navigation:

```ts
import { installAuthDiagnostics, test } from './fixtures/authenticated';

test('diagnostic example', async ({ page }) => {
  await installAuthDiagnostics(page);
  // ... installAuthenticatedSession(page), then navigate ...
});
```

The diagnostics report only API method, pathname and status for `401` responses, plus navigations to `/auth/login`. Tokens are never logged.

## Migration rule

Do not add a second ad-hoc session helper to individual specs. Use `authenticatedPage` for the default manager session, add only feature-specific API routes in the test body, and use `installAuthenticatedSession` for an explicit user override. Keep `BASE_URL` identical to Playwright’s configured `baseURL`; otherwise the httpOnly cookie will be scoped to the wrong origin.
