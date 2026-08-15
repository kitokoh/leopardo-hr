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

  test('tenant-scoped recruitment view redirects the super-admin to the dashboard', async ({ page }) => {
    // Issue #2272 : la console super-admin n'a pas de contexte tenant —
    // l'accès direct par URL à une vue tenant redirige vers le dashboard.
    await page.addInitScript((token) => {
      sessionStorage.setItem('admin_token', token)
    }, process.env.PLAYWRIGHT_AUTH_TOKEN)

    await page.goto('/recruitment')

    await expect(page).toHaveURL(/\/$/, { timeout: 10_000 })
    await expect(page.getByText(/Fonctionnalité entreprise/i)).toBeVisible()
  })
})

test.describe('Exports page structure', () => {
  test.skip(
    !process.env.PLAYWRIGHT_AUTH_TOKEN,
    'Skipped: requires PLAYWRIGHT_AUTH_TOKEN for authenticated tests',
  )

  test('tenant-scoped exports view redirects the super-admin to the dashboard', async ({ page }) => {
    // Issue #2272 : la console super-admin n'a pas de contexte tenant —
    // l'accès direct par URL à une vue tenant redirige vers le dashboard.
    await page.addInitScript((token) => {
      sessionStorage.setItem('admin_token', token)
    }, process.env.PLAYWRIGHT_AUTH_TOKEN)

    await page.goto('/exports')

    await expect(page).toHaveURL(/\/$/, { timeout: 10_000 })
    await expect(page.getByText(/Fonctionnalité entreprise/i)).toBeVisible()
  })
})
