import { expect, test } from '@playwright/test'

test.describe('Dashboard cockpit', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/login')
  })

  test('login page redirects unauthenticated users', async ({ page }) => {
    await page.goto('/')
    await expect(page).toHaveURL(/\/login/)
    await expect(
      page.getByRole('heading', { name: /Leopardo RH/i }),
    ).toBeVisible()
  })

  test('login form shows validation on empty submit', async ({ page }) => {
    const emailInput = page.locator('#email')
    const submitButton = page.getByRole('button', { name: /Se connecter/i })

    await emailInput.clear()
    await submitButton.click()

    // HTML5 validation should prevent submission
    await expect(emailInput).toHaveAttribute('required', '')
  })

  test('login form shows error on invalid credentials', async ({ page }) => {
    // QA 2026-08-15 (#2659) : le défaut d'api.js pointe désormais l'API de
    // prod — sans mock, ce test envoyait un VRAI appel (cold start Render,
    // non déterministe, >10s). On mock la route d'auth : 401 → LoginView
    // affiche le bandeau « Erreur de connexion ».
    await page.route('**/api/v1/platform/auth/login', async (route) => {
      await route.fulfill({
        status: 401,
        contentType: 'application/json',
        body: JSON.stringify({ message: 'Identifiants invalides.' }),
      })
    })

    await page.locator('#email').fill('invalid@example.com')
    await page.locator('#password').fill('wrongpassword')
    await page.getByRole('button', { name: /Se connecter/i }).click()

    // Should show error message after failed API call
    await expect(
      page.getByRole('heading', { name: /Erreur de connexion/i }),
    ).toBeVisible({ timeout: 10_000 })
  })

  test('remember me checkbox is functional', async ({ page }) => {
    const rememberCheckbox = page.getByLabel(/Se souvenir de moi/i)
    await expect(rememberCheckbox).not.toBeChecked()
    await rememberCheckbox.check()
    await expect(rememberCheckbox).toBeChecked()
    await rememberCheckbox.uncheck()
    await expect(rememberCheckbox).not.toBeChecked()
  })

  // QA 2026-08-15 (#2658) : l'ancien test attendait un lien « Mot de passe
  // oublie » absent de LoginView (surface supprimée) → échec permanent.
  // Remplacé par une assertion sur une fonctionnalité réelle de l'écran :
  // le toggle afficher/masquer le mot de passe.
  test('show/hide password toggle is functional', async ({ page }) => {
    const passwordInput = page.locator('#password')
    await expect(passwordInput).toBeVisible()
    await passwordInput.fill('secret123')
    await expect(passwordInput).toHaveAttribute('type', 'password')
    await page.getByRole('button', { name: /Afficher le mot de passe/i }).click()
    await expect(passwordInput).toHaveAttribute('type', 'text')
    await page.getByRole('button', { name: /Masquer le mot de passe/i }).click()
    await expect(passwordInput).toHaveAttribute('type', 'password')
  })
})
