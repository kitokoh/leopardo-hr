import { expect, test } from '@playwright/test'

test.describe('Exports and reports (unauthenticated guard)', () => {
  test('exports route requires authentication', async ({ page }) => {
    await page.goto('/exports')
    await expect(page).toHaveURL(/\/login/)
  })

  test('fleet route requires authentication', async ({ page }) => {
    await page.goto('/fleet')
    await expect(page).toHaveURL(/\/login/)
  })

  test('webhooks route requires authentication', async ({ page }) => {
    await page.goto('/webhooks')
    await expect(page).toHaveURL(/\/login/)
  })

  test('chat route requires authentication', async ({ page }) => {
    await page.goto('/chat')
    await expect(page).toHaveURL(/\/login/)
  })

  test('recruitment route requires authentication', async ({ page }) => {
    await page.goto('/recruitment')
    await expect(page).toHaveURL(/\/login/)
  })
})

test.describe('Exports page structure', () => {
  test.skip(
    !process.env.PLAYWRIGHT_AUTH_TOKEN,
    'Skipped: requires PLAYWRIGHT_AUTH_TOKEN for authenticated tests',
  )

  test('exports view loads with expected heading', async ({ page }) => {
    await page.addInitScript((token) => {
      localStorage.setItem('admin_token', token)
    }, process.env.PLAYWRIGHT_AUTH_TOKEN)

    await page.goto('/exports')

    await expect(
      page.getByRole('heading', { name: /export|rapports|reports/i }),
    ).toBeVisible({ timeout: 10_000 })
  })
})
