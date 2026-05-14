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

  test('payroll view loads with expected sections', async ({ page }) => {
    // Set auth token if available
    await page.addInitScript((token) => {
      localStorage.setItem('admin_token', token)
    }, process.env.PLAYWRIGHT_AUTH_TOKEN)

    await page.goto('/payroll')

    await expect(page.getByRole('heading', { name: /paie|payroll|bulletins/i })).toBeVisible({
      timeout: 10_000,
    })
  })
})
