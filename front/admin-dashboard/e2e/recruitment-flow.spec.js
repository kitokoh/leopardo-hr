import { expect, test } from '@playwright/test'

test.describe('Recruitment flow', () => {
  test.setTimeout(60_000)
  test.describe.configure({ retries: 3 })

  test('tenant-scoped recruitment view redirects the super-admin to the dashboard', async ({ page }) => {
    const corsHeaders = {
      // `*` plutôt qu'une origine figée : le port d'origine du webServer
      // (4173) peut être 127.0.0.1 ou localhost selon l'environnement CI.
      'Access-Control-Allow-Origin': '*',
      'Access-Control-Allow-Headers': 'authorization, content-type, accept',
      'Access-Control-Allow-Methods': 'GET, POST, PATCH, OPTIONS',
    }
    const fulfillJson = (route, body, status = 200) => route.fulfill({
      status,
      contentType: 'application/json',
      headers: corsHeaders,
      body: JSON.stringify(body),
    })
    const handleOptions = (route) => {
      if (route.request().method() === 'OPTIONS') {
        route.fulfill({ status: 204, headers: corsHeaders })
        return true
      }
      return false
    }

    await page.route(/\/platform\/auth\/login(?:\?.*)?$/, async (route) => {
      if (handleOptions(route)) return
      await fulfillJson(route, {
          token: 'playwright-admin-token',
          data: {
            id: 1,
            name: 'Admin',
            email: 'admin@example.test',
            role: 'super_admin',
          },
      })
    })

    await page.route(/\/platform\/auth\/me(?:\?.*)?$/, async (route) => {
      if (handleOptions(route)) return
      await fulfillJson(route, {
          data: {
            id: 1,
            name: 'Admin',
            email: 'admin@example.test',
            role: 'super_admin',
          },
      })
    })

    await page.addInitScript(() => {
      sessionStorage.setItem('admin_token', 'playwright-admin-token')
    })

    // Issue #2272 : la console super-admin n'a pas de contexte tenant —
    // l'accès direct par URL à une vue tenant redirige vers le dashboard,
    // jamais une page qui échoue en 401 muet.
    await page.goto('/recruitment')

    await expect(page).toHaveURL(/\/$/, { timeout: 10_000 })
    await expect(page.getByText(/Fonctionnalité entreprise/i)).toBeVisible()
  })
})
