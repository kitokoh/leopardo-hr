import { expect, test } from '@playwright/test'

test.describe('Payroll flow (unauthenticated guard)', () => {
  test('payroll route requires authentication', async ({ page }) => {
    await page.goto('/payroll')
    await expect(page).toHaveURL(/\/login/)
  })

  test('payroll link is not accessible without login', async ({ page }) => {
    await page.goto('/login')

    // Sidebar should not be visible on login page
    const sidebar = page.locator('nav[aria-label]').or(page.locator('.sidebar'))
    await expect(sidebar).not.toBeVisible()
  })
})

test.describe('Payroll page structure', () => {
  test.skip(
    !process.env.PLAYWRIGHT_AUTH_TOKEN,
    'Skipped: requires PLAYWRIGHT_AUTH_TOKEN env var for authenticated tests',
  )

  test('tenant-scoped payroll view redirects the super-admin to the dashboard', async ({ page }) => {
    // Issue #2272 : la console super-admin n'a pas de contexte tenant —
    // l'accès direct par URL à une vue tenant redirige vers le dashboard.
    // Set auth token if available
    await page.addInitScript((token) => {
      sessionStorage.setItem('admin_token', token)
    }, process.env.PLAYWRIGHT_AUTH_TOKEN)

    await page.goto('/payroll')

    await expect(page).toHaveURL(/\/$/, { timeout: 10_000 })
    await expect(page.getByText(/Fonctionnalité entreprise/i)).toBeVisible()
  })
})
