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

  test('removed leaves route renders an authenticated 404', async ({ page }) => {
    await page.addInitScript((token) => {
      sessionStorage.setItem('admin_token', token)
    }, process.env.PLAYWRIGHT_AUTH_TOKEN)

    await page.goto('/leaves')

    await expect(page).toHaveURL(/\/leaves$/, { timeout: 10_000 })
    await expect(page.getByRole('heading', { name: /Page non trouvée/i })).toBeVisible()
  })
})
