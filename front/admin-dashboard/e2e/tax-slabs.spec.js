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
 * Issue #1814 — Page barèmes fiscaux (tax-slabs) : chargement, éditeur,
 * simulateur. Nécessite le login super-admin démo.
 */
test.describe('Tax slabs admin page', () => {
  test.beforeEach(async ({ page }) => {
    // Le libellé de l'app suit admin_locale (localStorage) sinon navigator.language
    // (en-US dans le headless) — les assertions ci-dessous sont en français.
    await page.addInitScript(() => localStorage.setItem('admin_locale', 'fr'))
    await page.goto('/login')
    await page.locator('#email').fill('admin@leopardo-rh.com')
    await page.locator('#password').fill(E2E_ADMIN_PASSWORD)
    await page.getByRole('button', { name: /Se connecter/i }).click()
    await expect(page).not.toHaveURL(/\/login/, { timeout: 15_000 })
  })

  test('page loads with country selector and editor', async ({ page }) => {
    await page.goto('/settings/payroll/tax-slabs')
    // Le h1 ET le sous-titre (h3) contiennent 'Barèmes fiscaux' — cibler le h1.
    await expect(page.getByRole('heading', { name: /Barèmes fiscaux/i }).first()).toBeVisible()
    await expect(page.locator('#slab-country')).toBeVisible()
    await expect(page.getByRole('button', { name: /Ajouter une tranche/i })).toBeVisible()
    await expect(page.getByRole('heading', { name: /Simulateur d'impact/i })).toBeVisible()
  })

  test('simulator displays a result for the default gross salary', async ({ page }) => {
    await page.goto('/settings/payroll/tax-slabs')
    const gross = page.locator('#sim-gross')
    await expect(gross).toBeVisible()
    await expect(page.getByText(/Net/i).first()).toBeVisible({ timeout: 15_000 })
    // Après simulation, au moins un montant net est affiché.
    await expect(page.locator('text=Net').first()).toBeVisible()
  })

  test('editor allows opening the create form', async ({ page }) => {
    await page.goto('/settings/payroll/tax-slabs')
    await page.getByRole('button', { name: /Ajouter une tranche/i }).click()
    await expect(page.getByRole('heading', { name: /Ajouter une tranche/i })).toBeVisible()
    // L'uid est un compteur global : les lignes du tableau consomment les
    // premiers compteurs, le modal porte donc slab-DZ-min-2+ — assertion robuste.
    await expect(page.locator('input[id^="slab-DZ-min-"]').first()).toBeVisible()
    await page.getByRole('button', { name: /Annuler/i }).click()
    await expect(page.getByRole('heading', { name: /Ajouter une tranche/i })).not.toBeVisible()
  })
})
