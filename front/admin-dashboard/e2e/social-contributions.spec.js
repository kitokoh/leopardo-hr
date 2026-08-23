import { expect, test } from '@playwright/test'

// #4415 : creds de test via env — jamais de littéral prod dans le dépôt (politique #1697).
const E2E_ADMIN_PASSWORD = process.env.E2E_ADMIN_PASSWORD || 'e2e-fixture-password'

// QA 2026-08-15 (#2658) : ce spec exécute un VRAI login super-admin contre un
// backend. En CI locale (web-ci, webServer 127.0.0.1 sans backend) il échouait
// systématiquement. Il ne s'exécute que si E2E_BACKEND_URL pointe une API
// réelle (staging/prod) — sinon skip explicite.
const hasRealBackend = Boolean(process.env.E2E_BACKEND_URL)
const isLocal = !process.env.BASE_URL && !process.env.PLAYWRIGHT_BASE_URL
test.describe.configure({ mode: 'serial' })
test.beforeEach(async () => {
  test.skip(!hasRealBackend || isLocal, 'Nécessite un backend réel (E2E_BACKEND_URL)')
})

/**
 * Issue #1815 — Page cotisations sociales : chargement + simulateur comparateur.
 * Nécessite le login super-admin démo.
 */
test.describe('Social contributions admin page', () => {
  test.beforeEach(async ({ page }) => {
    // Le libellé de l'app suit admin_locale (localStorage) sinon navigator.language
    // (en-US dans le headless) — les assertions ci-dessous sont en français.
    await page.addInitScript(() => localStorage.setItem('admin_locale', 'fr'))
    await page.goto('/login')
    await page.getByLabel(/^Email$/i).fill('admin@leopardo-rh.com')
    await page.locator('#password').fill(E2E_ADMIN_PASSWORD)
    await page.getByRole('button', { name: /Se connecter/i }).click()
    await expect(page).not.toHaveURL(/\/login/, { timeout: 15_000 })
  })

  test('page loads with country selector and contribution table', async ({ page }) => {
    await page.goto('/settings/payroll/social-contributions')
    await expect(page.getByRole('heading', { name: /Cotisations sociales/i }).first()).toBeVisible()
    await expect(page.locator('#sc-country')).toBeVisible()
    await expect(page.getByRole('button', { name: /Ajouter une cotisation/i })).toBeVisible()
  })

  test('simulator shows two-country comparison', async ({ page }) => {
    await page.goto('/settings/payroll/social-contributions')
    await expect(page.locator('#simc-gross')).toBeVisible()
    await expect(page.getByText(/Coût total employeur/i).first()).toBeVisible({ timeout: 15_000 })
  })

  test('create form opens and closes', async ({ page }) => {
    await page.goto('/settings/payroll/social-contributions')
    await page.getByRole('button', { name: /Ajouter une cotisation/i }).click()
    await expect(page.getByRole('heading', { name: /Ajouter une cotisation/i })).toBeVisible()
    await page.getByRole('button', { name: /Annuler/i }).click()
    await expect(page.getByRole('heading', { name: /Ajouter une cotisation/i })).not.toBeVisible()
  })
})
