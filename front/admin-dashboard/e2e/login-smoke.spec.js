import { expect, test } from '@playwright/test'

// Smoke tests du login admin SPA (fusionnés : login-smoke + navigation-smoke,
// audit #6592 — suppression des assertions dupliquées ; le redirect non-auth
// vit dans navigation-spa.spec.js).

test.describe('Login smoke tests', () => {
  test('login screen loads for administrators', async ({ page }) => {
    await page.goto('/login')

    await expect(page).toHaveTitle(/Leopardo RH/i)
    await expect(
      page.getByRole('heading', { name: /Leopardo RH/i }),
    ).toBeVisible()
    await expect(page.locator('#email')).toBeVisible()
    await expect(page.locator('#password')).toBeVisible()
    await expect(page.getByRole('button', { name: /Se connecter/i })).toBeVisible()
    await expect(page.getByRole('button', { name: /Se connecter/i })).toBeEnabled()
  })

  test('login form shows validation errors for empty submit', async ({ page }) => {
    await page.goto('/login')

    await page.getByRole('button', { name: /Se connecter/i }).click()

    // HTML5 validation should prevent submission with empty required fields
    const emailInput = page.locator('#email')
    const isValid = await emailInput.evaluate(
      (el) => el.validity.valid,
    )
    expect(isValid).toBe(false)
  })

  test('login form shows error for invalid credentials', async ({ page }) => {
    await page.goto('/login')

    await page.locator('#email').fill('invalid@example.com')
    await page.locator('#password').fill('wrongpassword')
    await page.getByRole('button', { name: /Se connecter/i }).click()

    // Should show an error message or stay on login page
    await expect(page).toHaveURL(/\/login/)
  })

  test('deep link / hard refresh serves the SPA (issue #2334)', async ({ page }) => {
    // Accès direct à une sous-route (ex. /companies) : avec base './' +
    // createWebHistory, les assets se résolvaient sous le chemin courant →
    // page blanche. Le routeur doit reprendre la main et rediriger vers
    // /login (non authentifié), preuve que index.html + les assets ont été
    // chargés depuis la racine.
    await page.goto('/companies')
    await expect(page).toHaveURL(/\/login/)
    // Le formulaire de login est rendu (preuve que l'app SPA a bien été servie)
    await expect(page.locator('#password')).toBeVisible()
  })
})
