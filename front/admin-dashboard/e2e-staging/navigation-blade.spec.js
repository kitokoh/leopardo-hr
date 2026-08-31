import { expect, test } from '@playwright/test'

// Navigation et robustesse du portail web Laravel (blade) en staging :
// redirections des routes protégées, page 404, et portail de documentation
// API (/docs, /api-explorer, /tester-guide).

test.describe('Portail blade — navigation et erreurs', () => {
  test('les routes protégées redirigent vers /login quand non authentifié', async ({ page }) => {
    await page.goto('/dashboard')
    await expect(page).toHaveURL(/\/login/)
    await expect(
      page.getByRole('heading', { name: /Connexion manager/i }),
    ).toBeVisible()
  })

  test("l'espace employé /me redirige vers /login quand non authentifié", async ({ page }) => {
    await page.goto('/me')
    await expect(page).toHaveURL(/\/login/)
  })

  test('une route inconnue renvoie une page 404 sans fuite technique', async ({ page }) => {
    const response = await page.goto('/route-inexistante-e2e-12345')
    expect(response?.status()).toBe(404)

    await expect(page.locator('body')).toBeVisible()
    const body = await page.locator('body').textContent()
    expect(body).not.toContain('vendor/laravel')
    expect(body).not.toContain('Stack trace')
    expect(body).not.toContain('SQLSTATE')
  })

  test('le portail documentation API est accessible (/docs)', async ({ page }) => {
    const response = await page.goto('/docs')
    expect(response?.status()).toBe(200)
    await expect(page).toHaveTitle(/API Docs/)
    await expect(page.locator('#swagger-ui')).toBeVisible()
  })

  test("l'explorateur API et le guide testeur sont accessibles", async ({ page }) => {
    const explorer = await page.goto('/api-explorer')
    expect(explorer?.status()).toBe(200)
    await expect(page).toHaveTitle(/API Explorer/)

    const guide = await page.goto('/tester-guide')
    expect(guide?.status()).toBe(200)
    await expect(page).toHaveTitle(/Guide testeur/)
  })
})
