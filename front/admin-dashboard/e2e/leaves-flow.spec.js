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

  test('leaves calendar view loads with expected heading', async ({ page }) => {
    await page.addInitScript((token) => {
      localStorage.setItem('admin_token', token)
    }, process.env.PLAYWRIGHT_AUTH_TOKEN)

    await page.goto('/leaves')

    await expect(
      page.getByRole('heading', { name: /cong|absences|leaves/i }),
    ).toBeVisible({ timeout: 10_000 })
  })
})
