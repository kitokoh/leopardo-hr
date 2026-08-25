import { expect, test } from '@playwright/test'

// #4415 : creds de test via env — jamais de littéral prod dans le dépôt (politique #1697).
const E2E_ADMIN_PASSWORD = process.env.E2E_ADMIN_PASSWORD || 'e2e-fixture-password'

// Mock helpers — les GET du client portent un cache-buster `_t=<ts>` :
// les patterns doivent tolérer la query string (sinon la requête part en
// vrai réseau → 401 sur token factice → logout global → spec cassée).
const withQuery = (path) => new RegExp(`\\/api\\/v1\\/${path}(?:\\?.*)?$`)
const json = (body) => ({ status: 200, contentType: 'application/json', body: JSON.stringify(body) })

test('platform administrator can sign in and reach the admin dashboard', async ({ page }) => {
  let loginRequestSeen = false

  await page.route('**/api/v1/platform/auth/login', async (route) => {
    loginRequestSeen = true
    await route.fulfill(json({
      data: {
        id: 1,
        name: 'Super Administrateur',
        email: 'admin@leopardo-rh.com',
        role: 'super_admin',
        two_fa_enabled: false,
      },
      token: 'platform-admin-token',
      token_type: 'Bearer',
    }))
  })

  await page.route(withQuery('platform/auth/me'), async (route) => {
    await route.fulfill(json({
      data: {
        id: 1,
        name: 'Super Administrateur',
        email: 'admin@leopardo-rh.com',
        role: 'super_admin',
        two_fa_enabled: false,
      },
    }))
  })

  // Cockpit plateforme (DashboardView) : 3 appels au mount.
  await page.route(withQuery('platform/companies/health'), async (route) => {
    await route.fulfill(json({
      data: {
        summary: { active_companies: 42, companies: 50, mrr: 12345, risk: { high: 2, medium: 3, low: 5 } },
        items: [],
      },
    }))
  })
  await page.route(withQuery('platform/metrics/overview'), async (route) => {
    await route.fulfill(json({
      data: {
        revenue: { currency: 'EUR', mrr: 12345, arr: 148140 },
        companies: { total: 50, active: 42, trial: 3, suspended: 1, expired: 0 },
        subscriptions: { total: 40, active: 35 },
      },
    }))
  })
  await page.route(withQuery('platform/company-requests'), async (route) => {
    await route.fulfill(json({ data: [], meta: { total: 7 } }))
  })

  // Dashboard store + polling notifications (mount de DashboardLayout).
  await page.route(withQuery('admin/dashboard/stats'), async (route) => {
    await route.fulfill(json({
      totalUsers: 12,
      totalCompanies: 50,
      activeSubscriptions: 40,
      monthlyRevenue: 12345,
      newUsersToday: 1,
      newCompaniesToday: 0,
      supportTickets: 3,
      systemHealth: 'good',
    }))
  })
  await page.route(withQuery('admin/dashboard/activities'), async (route) => {
    await route.fulfill(json({ data: [] }))
  })
  await page.route(withQuery('admin/dashboard/alerts'), async (route) => {
    await route.fulfill(json({ data: [] }))
  })
  await page.route(withQuery('notifications'), async (route) => {
    await route.fulfill(json({ data: [], meta: { total: 0 } }))
  })

  await page.goto('/login')
  await page.locator('#email').fill('admin@leopardo-rh.com')
  await page.locator('#password').fill(E2E_ADMIN_PASSWORD)
  await page.getByRole('button', { name: /^Se connecter$/i }).click()

  await expect(page).toHaveURL(/\/$/)
  await expect(page.locator('body')).toContainText(/Tableau de bord|Dashboard/i)
  // QA 2026-08-15 (#2658) : « Cockpit plateforme » n'est rendu qu'en état
  // d'erreur (DashboardView) — le chemin nominal affiche les KPI cards.
  await expect(page.locator('body')).toContainText(/Entreprises actives|MRR Plateforme|Demandes en attente/i)
  expect(loginRequestSeen).toBe(true)
})

test('platform demo selector does not advertise tenant employee accounts', async ({ page }) => {
  await page.goto('/login')
  await page.getByRole('button', { name: /Acces Demo/i }).click()

  await expect(page.locator('body')).toContainText('Super Administrateur')
  await expect(page.locator('body')).toContainText(/administrateurs plateforme/i)
  await expect(page.locator('body')).not.toContainText('Ahmed Benali')
  await expect(page.locator('body')).not.toContainText('karim.aouad@techcorp-algerie.dz')
})
