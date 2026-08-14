import { expect, test } from '@playwright/test'

/**
 * Issue #1814 — Page barèmes fiscaux (tax-slabs) : chargement, éditeur,
 * simulateur. Nécessite le login super-admin démo.
 */
test.describe('Tax slabs admin page', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/login')
    await page.getByLabel(/Adresse email/i).fill('admin@leopardo-rh.com')
    await page.locator('#password').fill('password123')
    await page.getByRole('button', { name: /Se connecter/i }).click()
    await expect(page).not.toHaveURL(/\/login/, { timeout: 15_000 })
  })

  test('page loads with country selector and editor', async ({ page }) => {
    await page.goto('/settings/payroll/tax-slabs')
    await expect(page.getByRole('heading', { name: /Barèmes fiscaux/i })).toBeVisible()
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
    await expect(page.locator('#slab-DZ-min-1')).toBeVisible()
    await page.getByRole('button', { name: /Annuler/i }).click()
    await expect(page.getByRole('heading', { name: /Ajouter une tranche/i })).not.toBeVisible()
  })
})
