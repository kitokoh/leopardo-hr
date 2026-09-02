import { expect, test } from '@playwright/test'

/**
 * E2E bugs prod lot frontend admin-dashboard (2026-09-01/02) :
 *  - #6714 : /settings en super-admin ne doit plus déconnecter (401 sur
 *    /company/banking sans _skipAuthRedirect → l'intercepteur détruisait la
 *    session).
 *  - #6713 : /travel avec /travel/ping en 404 (backend BC-24 non livré,
 *    #6127) → état « module non activé » et non une fausse panne.
 *  - #6712 : /fuel-station avec l'API référentiel absente (3×404, #6391/#6373)
 *    → panneau « module en préparation » et non 3 toasts d'erreur permanents.
 *
 * Stratégie (même pattern que travel-admin.spec.js) : mocks API (page.route)
 * + login via token — déterministe en CI sans backend ; utilisable contre
 * staging avec PLAYWRIGHT_AUTH_TOKEN (BACKEND_LIVE=1 → mocks désactivés).
 */

const AUTHENTICATED = Boolean(process.env.PLAYWRIGHT_AUTH_TOKEN)
const LIVE = process.env.BACKEND_LIVE === '1'

test.describe.configure({ timeout: 120_000 })

const ADMIN_USER = {
  id: 1,
  name: 'Agent E2E',
  email: 'agent.e2e@leopardo.test',
  role: 'superadmin',
  language: 'fr',
}

/** Login via sessionStorage + mocks API génériques (auth + données). */
async function mockBaseApi(page) {
  // Catch-all API : les endpoints non mockés renvoient 200 vide pour ne pas
  // déclencher l'intercepteur 401 (qui détruirait la session de test).
  await page.route('**/api/v1/**', (route) =>
    route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: {} }) }),
  )
  await page.route(/^https?:\/\/[^/]+\/api\/v1\/platform\/auth\/me(\?.*)?$/, (route) =>
    route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: ADMIN_USER }) }),
  )
}

async function loginViaToken(page) {
  await page.addInitScript((token) => {
    sessionStorage.setItem('admin_token', token)
  }, process.env.PLAYWRIGHT_AUTH_TOKEN)
}

test.describe('Settings — session super-admin préservée (#6714)', () => {
  test.skip(!AUTHENTICATED, 'Skipped: requiert PLAYWRIGHT_AUTH_TOKEN (tests authentifiés)')
  test.skip(LIVE, 'Skipped: BACKEND_LIVE=1 — tests mock désactivés')

  test('visiter /settings avec /company/banking en 401 ne déconnecte pas', async ({ page }) => {
    const toasts = []
    page.on('console', (msg) => {
      if (msg.type() === 'error') toasts.push(msg.text())
    })
    await loginViaToken(page)
    await mockBaseApi(page)
    // Route tenant : 401 attendu pour le super-admin — doit être ignoré grâce
    // à _skipAuthRedirect (#4170/#6714), la session doit survivre.
    await page.route(/^https?:\/\/[^/]+\/api\/v1\/company\/banking(\?.*)?$/, (route) =>
      route.fulfill({ status: 401, contentType: 'application/json', body: JSON.stringify({ message: 'Unauthenticated.' }) }),
    )

    await page.goto('/settings')
    await expect(page.getByRole('heading', { name: /Paramètres/i }).first()).toBeVisible({ timeout: 15_000 })
    // Pas de redirection vers /login (session non détruite)
    await page.waitForTimeout(1500)
    expect(page.url()).not.toContain('/login')
    // Pas de toast « Authentication required »
    await expect(page.locator('.toast, [role="alert"]').filter({ hasText: /Authentication required/i })).toHaveCount(0)
  })
})

test.describe('TravelAgency — gate 404 = module non livré (#6713)', () => {
  test.skip(!AUTHENTICATED, 'Skipped: requiert PLAYWRIGHT_AUTH_TOKEN (tests authentifiés)')
  test.skip(LIVE, 'Skipped: BACKEND_LIVE=1 — tests mock désactivés')

  test('404 sur /travel/ping → « module non activé », pas de fausse panne', async ({ page }) => {
    await loginViaToken(page)
    await mockBaseApi(page)
    await page.route(/^https?:\/\/[^/]+\/api\/v1\/travel\/ping(\?.*)?$/, (route) =>
      route.fulfill({ status: 404, contentType: 'application/json', body: JSON.stringify({ error: 'RESOURCE_NOT_FOUND' }) }),
    )

    await page.goto('/travel')
    await expect(
      page.getByRole('heading', { name: /Module Agence de voyage non activé/i }),
    ).toBeVisible({ timeout: 15_000 })
    // Pas l'état d'erreur « temporairement indisponible »
    await expect(
      page.getByRole('heading', { name: /Module temporairement indisponible/i }),
    ).toHaveCount(0)
  })
})

test.describe('Fuel stations — API référentiel absente (#6712)', () => {
  test.skip(!AUTHENTICATED, 'Skipped: requiert PLAYWRIGHT_AUTH_TOKEN (tests authentifiés)')
  test.skip(LIVE, 'Skipped: BACKEND_LIVE=1 — tests mock désactivés')

  test('3×404 → panneau « Module Fuel en préparation », pas de toasts', async ({ page }) => {
    const toasts = []
    page.on('console', (msg) => {
      if (msg.type() === 'error') toasts.push(msg.text())
    })
    await loginViaToken(page)
    await mockBaseApi(page)
    // Les 3 endpoints référentiel n'existent pas encore (#6391/#6373) → 404.
    for (const path of ['/fuel-station/stations', '/fuel-station/incidents', '/fuel-station/reconciliations']) {
      await page.route(new RegExp(`^https?:\\/\\/[^/]+/api/v1${path}(\\?.*)?$`), (route) =>
        route.fulfill({ status: 404, contentType: 'application/json', body: JSON.stringify({ error: 'RESOURCE_NOT_FOUND' }) }),
      )
    }

    await page.goto('/fuel-station')
    await expect(
      page.getByRole('heading', { name: /Module Fuel en préparation/i }),
    ).toBeVisible({ timeout: 15_000 })
    // Pas de toast « Resource not found » permanent
    await expect(page.locator('.toast, [role="alert"]').filter({ hasText: /Resource not found/i })).toHaveCount(0)
  })

  test('API livrée (200) → KPIs et onglets rendus', async ({ page }) => {
    await loginViaToken(page)
    await mockBaseApi(page)
    await page.route(/^https?:\/\/[^/]+\/api\/v1\/fuel-station\/stations(\?.*)?$/, (route) =>
      route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: [{ id: 1, code: 'DZ-01', name: 'Alger Centre', timezone: 'Africa/Algiers', status: 'active' }] }) }),
    )
    await page.route(/^https?:\/\/[^/]+\/api\/v1\/fuel-station\/incidents(\?.*)?$/, (route) =>
      route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: [] }) }),
    )
    await page.route(/^https?:\/\/[^/]+\/api\/v1\/fuel-station\/reconciliations(\?.*)?$/, (route) =>
      route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: [] }) }),
    )

    await page.goto('/fuel-station')
    await expect(page.getByRole('tab', { name: /Stations/i }).first()).toBeVisible({ timeout: 15_000 })
    await expect(page.getByText('Alger Centre')).toBeVisible()
  })
})
