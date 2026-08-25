import { expect, test } from '@playwright/test'

test.describe('Recruitment flow', () => {
  test.setTimeout(60_000)
  test.describe.configure({ retries: 3 })

  test('removed tenant route /recruitment renders the 404 page for an authenticated super-admin', async ({ page }) => {
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

    // Issue #4106 : la route /recruitment (vue tenant morte) a été retirée
    // du routeur par #3837 — un super-admin qui y accède par URL tombe sur
    // la NotFoundView (état honnête), jamais sur une vue cassée ni un 401
    // muet. Le cas « vue tenant existante » est couvert par exports-flow.
    await page.goto('/recruitment')

    await expect(page).toHaveURL(/\/recruitment$/, { timeout: 10_000 })
    await expect(page.getByRole('heading', { name: /Page non trouvée/i })).toBeVisible()
    await expect(page.getByText(/Fonctionnalité entreprise/i)).toHaveCount(0)
  })
})
