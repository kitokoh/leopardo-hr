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

test.describe('Recruitment page structure', () => {
  test.skip(
    !process.env.PLAYWRIGHT_AUTH_TOKEN,
    'Skipped: requires PLAYWRIGHT_AUTH_TOKEN for authenticated tests',
  )

  test('removed recruitment route renders an authenticated 404', async ({ page }) => {
    await page.addInitScript((token) => {
      sessionStorage.setItem('admin_token', token)
    }, process.env.PLAYWRIGHT_AUTH_TOKEN)

    await page.goto('/recruitment')

    await expect(page).toHaveURL(/\/recruitment$/, { timeout: 10_000 })
    await expect(page.getByRole('heading', { name: /Page non trouvée/i })).toBeVisible()
  })
})

test.describe('Exports page structure', () => {
  test.skip(
    !process.env.PLAYWRIGHT_AUTH_TOKEN,
    'Skipped: requires PLAYWRIGHT_AUTH_TOKEN for authenticated tests',
  )

  test('exports page keeps tenant-only operations local and visible', async ({ page }) => {
    await page.addInitScript((token) => {
      sessionStorage.setItem('admin_token', token)
    }, process.env.PLAYWRIGHT_AUTH_TOKEN)

    await page.goto('/exports')

    await expect(page).toHaveURL(/\/exports$/, { timeout: 10_000 })
    await expect(page.getByText(/Disponible dans l.space client/i).first()).toBeVisible()
  })
})
