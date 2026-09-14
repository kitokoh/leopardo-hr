import { expect, test } from '@playwright/test'

// #4415 : creds de test via env — jamais de littéral prod dans le dépôt.
const E2E_ADMIN_PASSWORD = process.env.E2E_ADMIN_PASSWORD || 'e2e-fixture-password'

// Mock helpers — les GET du client portent un cache-buster `_t=<ts>`.
const withQuery = (path) => new RegExp(`\\/api\\/v1\\/${path}(?:\\?.*)?$`)
const json = (body) => ({ status: 200, contentType: 'application/json', body: JSON.stringify(body) })

/**
 * #7302 — « Companies » doit être utilisable SANS attendre le scoring.
 *
 * `GET /platform/companies/health` score tout le portefeuille (~27 s pour
 * 43 sociétés). La vue charge désormais d'abord l'annuaire
 * (`GET /platform/companies`, < 1 s), affiche les lignes, puis hydrate les
 * scores en tâche de fond en annonçant explicitement le calcul.
 *
 * Ce test verrouille les deux temps : les lignes apparaissent AVANT que
 * `health` ne réponde (réponse retardée artificiellement), puis les scores
 * s'affichent une fois qu'il a répondu.
 */
test('la liste des clients est utilisable avant la fin du scoring du portefeuille', async ({ page }) => {
  test.setTimeout(60_000)

  await page.route('**/api/v1/platform/auth/login', (route) =>
    route.fulfill(json({
      data: { id: 1, name: 'Super Administrateur', email: 'admin@leopardo-rh.com', role: 'super_admin', two_fa_enabled: false },
      token: 'platform-admin-token',
      token_type: 'Bearer',
    })))

  await page.route(withQuery('platform/auth/me'), (route) =>
    route.fulfill(json({
      data: { id: 1, name: 'Super Administrateur', email: 'admin@leopardo-rh.com', role: 'super_admin', two_fa_enabled: false },
    })))

  // Bruit du layout (DashboardLayout) : mocks neutres.
  for (const path of ['admin/dashboard/stats', 'admin/dashboard/activities', 'admin/dashboard/alerts', 'notifications', 'platform/company-requests']) {
    await page.route(withQuery(path), (route) => route.fulfill(json({ data: [], meta: { total: 0 } })))
  }
  await page.route(withQuery('platform/metrics/overview'), (route) => route.fulfill(json({ data: {} })))

  // 1) Annuaire : réponse immédiate, 2 sociétés. On capture l'URL pour
  // verrouiller `per_page=100` : l'endpoint est paginé (20 par défaut) et sans
  // ce paramètre la vue afficherait moins de sociétés que l'ancien `health`.
  let directoryUrl = ''
  await page.route(withQuery('platform/companies'), (route) => {
    directoryUrl = route.request().url()
    return route.fulfill(json({
      data: [
        { id: 'c1', name: 'Alpha SARL', status: 'active', country: 'DZ', currency: 'DZD' },
        { id: 'c2', name: 'Beta SARL', status: 'trial', country: 'MA', currency: 'MAD' },
      ],
    }))
  })

  // 2) Scoring : réponse RETARDÉE de 4 s (simule l'endpoint coûteux).
  let healthResolved = false
  await page.route(withQuery('platform/companies/health'), async (route) => {
    await new Promise((r) => setTimeout(r, 4000))
    healthResolved = true
    await route.fulfill(json({
      data: {
        summary: { companies: 2, active_companies: 1, mrr: 29, risk: { high: 1, medium: 1, low: 0 } },
        items: [
          {
            company: { id: 'c1', name: 'Alpha SARL', status: 'active', country: 'DZ' },
            plan: { name: 'Pilot' }, subscription: { mrr: 29, currency: 'EUR' },
            risk_level: 'medium', health_score: 72, attendance_logs_30d: 12, employees_active: 3,
            next_action: { label: 'Relancer le pointage' },
          },
          {
            company: { id: 'c2', name: 'Beta SARL', status: 'trial', country: 'MA' },
            plan: { name: 'Free' }, subscription: { mrr: 0, currency: 'EUR' },
            risk_level: 'high', health_score: 15, attendance_logs_30d: 0, employees_active: 1,
            next_action: null,
          },
        ],
      },
    }))
  })

  await page.goto('/login')
  await page.locator('#email').fill('admin@leopardo-rh.com')
  await page.locator('#password').fill(E2E_ADMIN_PASSWORD)
  await page.getByRole('button', { name: /^Se connecter$/i }).click()
  await expect(page).toHaveURL(/\/$/)

  await page.goto('/companies')

  // (a) AVANT le scoring : la liste est là, et le calcul est annoncé.
  await expect(page.getByText('Alpha SARL')).toBeVisible({ timeout: 3000 })
  await expect(page.getByText('Beta SARL')).toBeVisible({ timeout: 3000 })
  expect(healthResolved, 'la liste ne doit pas attendre le scoring').toBe(false)
  await expect(page.getByText(/Calcul des scores en cours/i)).toBeVisible({ timeout: 3000 })
  expect(directoryUrl, 'l’annuaire doit demander tout le portefeuille').toContain('per_page=100')

  // (b) APRÈS le scoring : les scores remplacent les placeholders.
  await expect(page.getByText('72%')).toBeVisible({ timeout: 15000 })
  await expect(page.getByText('Pilot')).toBeVisible({ timeout: 15000 })
  await expect(page.getByText(/Calcul des scores en cours/i)).toBeHidden({ timeout: 15000 })
})
