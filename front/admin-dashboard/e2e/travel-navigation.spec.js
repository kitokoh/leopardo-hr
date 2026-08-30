import { expect, test } from '@playwright/test'

// TRAVEL-601 (#6078) + TRAVEL-1008 (#6121) — Navigation « Agence de voyage »
// conditionnée par le flag `travelagency` (contrat réel : GET /travel/ping,
// middleware module.travelagency → 200 si actif, 403 FEATURE_NOT_ENABLED sinon,
// 401 hors contexte tenant).
const AUTH_ME = {
  data: {
    id: 1,
    name: 'Super Admin',
    email: 'admin@leopardo-rh.com',
    role: 'super_admin',
  },
}

async function stubAuth(page, { pingStatus = 200, pingBody = {} } = {}) {
  await page.route(/\/api\/v1\/platform\/auth\/me(\?.*)?$/, async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify(AUTH_ME),
    })
  })
  await page.route(/\/api\/v1\/travel\/ping(\?.*)?$/, async (route) => {
    await route.fulfill({
      status: pingStatus,
      contentType: 'application/json',
      body: JSON.stringify(pingBody),
    })
  })
  await page.addInitScript(() => {
    sessionStorage.setItem('admin_token', 'e2e-travel-token')
  })
}

test.describe('Agence de voyage — navigation par flag (TRAVEL-601)', () => {
  test('le menu est visible quand le flag travelagency est actif', async ({ page }) => {
    await stubAuth(page, { pingStatus: 200 })
    await page.goto('/')

    await expect(page.getByRole('link', { name: /Agence de voyage/i })).toBeVisible()
    await page.getByRole('link', { name: /Agence de voyage/i }).click()
    await expect(page).toHaveURL(/\/travel$/)
    await expect(page.getByRole('heading', { name: /Agence de voyage/i })).toBeVisible()
  })

  test('le menu est masqué quand le flag est inactif (403 FEATURE_NOT_ENABLED)', async ({ page }) => {
    await stubAuth(page, {
      pingStatus: 403,
      pingBody: { error: 'FEATURE_NOT_ENABLED', message: 'Your plan does not include the TravelAgency module.' },
    })
    await page.goto('/')

    await expect(page.getByRole('link', { name: /Agence de voyage/i })).toHaveCount(0)
  })

  test('le menu est masqué sans contexte tenant (401) et la session survit', async ({ page }) => {
    await stubAuth(page, { pingStatus: 401 })
    await page.goto('/')

    await expect(page.getByRole('link', { name: /Agence de voyage/i })).toHaveCount(0)
    // La session admin n'est pas détruite (pattern _skipAuthRedirect #4170).
    await expect(page.getByText('Super Admin')).toBeVisible()
  })

  test('accès direct à /travel avec flag inactif → état « module inactif »', async ({ page }) => {
    await stubAuth(page, {
      pingStatus: 403,
      pingBody: { error: 'FEATURE_NOT_ENABLED', message: 'FEATURE_NOT_ENABLED' },
    })
    await page.goto('/travel')

    await expect(page.getByText(/Module Agence de voyage inactif/i)).toBeVisible()
  })
})
