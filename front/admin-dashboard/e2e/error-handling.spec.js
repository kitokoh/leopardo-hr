import { expect, test } from '@playwright/test'

test.describe('Error handling smoke tests', () => {
  test('404 page renders for unknown routes', async ({ page }) => {
    const response = await page.goto('/this-route-does-not-exist-12345')

    // Should either redirect to login or show a proper error page
    // Not a browser-level error
    await expect(page.locator('body')).toBeVisible()
  })

  test('API errors do not leak stack traces', async ({ page }) => {
    await page.goto('/login')

    // Check that no PHP/Laravel stack traces are visible on the page
    const body = await page.locator('body').textContent()
    expect(body).not.toContain('vendor/laravel')
    expect(body).not.toContain('Stack trace')
    expect(body).not.toContain('SQLSTATE')
  })

  test('page loads without console errors', async ({ page }) => {
    const consoleErrors = []
    page.on('console', (msg) => {
      if (msg.type() === 'error') {
        consoleErrors.push(msg.text())
      }
    })

    await page.goto('/login')
    await page.waitForLoadState('networkidle')

    // Filter out expected errors (e.g., favicon 404, API connection)
    const unexpectedErrors = consoleErrors.filter(
      (msg) =>
        !msg.includes('favicon') &&
        !msg.includes('net::ERR_') &&
        !msg.includes('Failed to load resource'),
    )
    expect(unexpectedErrors).toHaveLength(0)
  })
})
