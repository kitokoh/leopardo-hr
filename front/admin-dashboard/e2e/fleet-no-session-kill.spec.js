import { expect, test } from '@playwright/test'

// Issue #4189 — le dashboard super-admin consommait des routes tenant
// (auth:sanctum + tenant + api.manager) qui répondent 401 à son token
// super_admin_api. Avant le correctif (#4170), l'intercepteur global
// (services/api.js) détruisait la session et redirigeait vers /login :
// ouvrir la page Flotte déconnectait le super-admin.
//
// Régression : ouvrir /fleet connecté → on RESTE sur /fleet, état d'erreur
// honnête affiché, jamais de redirect vers /login.
test.describe('Fleet page — no session kill on tenant 401', () => {
  test('super-admin stays authenticated when tenant routes return 401', async ({ page }) => {
    const pageErrors = []

    // Session super-admin (forme réelle du store auth)
    await page.addInitScript(() => {
      sessionStorage.setItem('admin_token', 'test-super-admin-token')
    })

    // Endpoints mockés
    await page.route(/\/api\/v1\/platform\/auth\/me(\?.*)?$/, async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          data: { id: 1, name: 'Super Admin', email: 'admin@leopardo-rh.com', role: 'super_admin' },
        }),
      })
    })

    // Route TENANT (tracking.php:25) : 401 réel pour un super-admin — le
    // correctif #4170/_skipAuthRedirect doit empêcher la déconnexion.
    await page.route(/\/api\/v1\/vehicles(\?.*)?$/, async (route) => {
      await route.fulfill({ status: 401, contentType: 'application/json', body: JSON.stringify({ message: 'Unauthenticated.' }) })
    })

    // Route admin réelle : les alertes de flotte fonctionnent.
    await page.route(/\/api\/v1\/admin\/fleet\/alerts(\?.*)?$/, async (route) => {
      await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: [] }) })
    })

    page.on('pageerror', (error) => pageErrors.push(error.message))

    await page.goto('/fleet')

    // CRITÈRE 1 : on reste sur /fleet — pas de redirect /login.
    await expect(page).toHaveURL(/\/fleet$/, { timeout: 10_000 })
    // La navigation est rendue (sidebar présente) — la session n'a pas été tuée.
    await expect(page.getByRole('navigation', { name: /Menu principal/i })).toBeVisible()

    // CRITÈRE 2 : l'onglet Liste affiche l'état d'erreur honnête (la vue par
    // défaut est la carte — l'erreur DataTable vit sur l'onglet liste).
    await page.getByRole('button', { name: /Liste/i }).click()
    await expect(page.getByText(/Impossible de charger les donnees de flotte/i)).toBeVisible()

    // CRITÈRE 3 : pas d'erreur JS non gérée (TypeError…).
    expect(pageErrors).toEqual([])
  })
})
