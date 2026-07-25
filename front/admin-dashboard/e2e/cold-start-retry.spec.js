import { expect, test } from '@playwright/test'

// PA2-QA-007 — Render cold-start: the free/starter API instance can return a
// transient 502/503/504 while it wakes up from idle. The Next.js vitrine
// (`front/web/src/lib/api-client.ts`) and the mobile apps
// (`leopardo_core/lib/core/api/api_client.dart`) already retry those
// specific statuses with a progressive backoff instead of surfacing the
// error immediately; the admin dashboard's axios client
// (`front/admin-dashboard/src/services/api.js`) previously only showed a
// "try again" toast on 502/503/504 with no automatic retry. This test
// exercises the retry end-to-end: the login call fails with a transient 503
// once, then succeeds, and the user should land on the dashboard without
// ever seeing an error toast for the retried attempt.
test('login transparently retries a transient 503 (Render cold-start) and succeeds', async ({ page }) => {
  let loginAttempts = 0

  await page.route('**/api/v1/platform/auth/login', async (route) => {
    loginAttempts += 1

    if (loginAttempts === 1) {
      await route.fulfill({
        status: 503,
        contentType: 'application/json',
        body: JSON.stringify({ message: 'Service Unavailable' }),
      })
      return
    }

    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        data: {
          id: 1,
          name: 'Super Administrateur',
          email: 'admin@leopardo-rh.com',
          role: 'super_admin',
          two_fa_enabled: false,
        },
        token: 'platform-admin-token',
        token_type: 'Bearer',
      }),
    })
  })

  await page.route(/\/api\/v1\/platform\/auth\/me(?:\?.*)?$/, async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        data: {
          id: 1,
          name: 'Super Administrateur',
          email: 'admin@leopardo-rh.com',
          role: 'super_admin',
          two_fa_enabled: false,
        },
      }),
    })
  })

  await page.goto('/login')
  await page.getByLabel(/Adresse email/i).fill('admin@leopardo-rh.com')
  await page.locator('#password').fill('password123')
  await page.getByRole('button', { name: /Se connecter/i }).click()

  await expect(page).not.toHaveURL(/\/login$/, { timeout: 15_000 })
  expect(loginAttempts).toBeGreaterThanOrEqual(2)
})

test('a non-transient error (e.g. 401) is never retried', async ({ page }) => {
  let loginAttempts = 0

  await page.route('**/api/v1/platform/auth/login', async (route) => {
    loginAttempts += 1
    await route.fulfill({
      status: 401,
      contentType: 'application/json',
      body: JSON.stringify({ message: 'Identifiants invalides.' }),
    })
  })

  await page.goto('/login')
  await page.getByLabel(/Adresse email/i).fill('admin@leopardo-rh.com')
  await page.locator('#password').fill('wrong-password')
  await page.getByRole('button', { name: /Se connecter/i }).click()

  await expect(page.locator('body')).toBeVisible()
  await page.waitForTimeout(500)

  expect(loginAttempts).toBe(1)
  await expect(page).toHaveURL(/\/login$/)
})
