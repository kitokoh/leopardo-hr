import { expect, test } from '@playwright/test'

test.describe('Leaves management (unauthenticated guard)', () => {
  test('leaves route requires authentication', async ({ page }) => {
    await page.goto('/leaves')
    await expect(page).toHaveURL(/\/login/)
  })

  test('contracts route requires authentication', async ({ page }) => {
    await page.goto('/contracts')
    await expect(page).toHaveURL(/\/login/)
  })

  test('training route requires authentication', async ({ page }) => {
    await page.goto('/training')
    await expect(page).toHaveURL(/\/login/)
  })
})

test.describe('Leaves page structure', () => {
  test.skip(
    !process.env.PLAYWRIGHT_AUTH_TOKEN,
    'Skipped: requires PLAYWRIGHT_AUTH_TOKEN for authenticated tests',
  )

  test('tenant-scoped leaves view redirects the super-admin to the dashboard', async ({ page }) => {
    // Issue #2272 : la console super-admin n'a pas de contexte tenant —
    // l'accès direct par URL à une vue tenant redirige vers le dashboard.
    await page.addInitScript((token) => {
      sessionStorage.setItem('admin_token', token)
    }, process.env.PLAYWRIGHT_AUTH_TOKEN)

    await page.goto('/leaves')

    await expect(page).toHaveURL(/\/$/, { timeout: 10_000 })
    await expect(page.getByText(/Fonctionnalité entreprise/i)).toBeVisible()
  })
})
