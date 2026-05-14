import { expect, test } from '@playwright/test'

test.describe('Dashboard cockpit', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/login')
  })

  test('login page redirects unauthenticated users', async ({ page }) => {
    await page.goto('/')
    await expect(page).toHaveURL(/\/login/)
    await expect(
      page.getByRole('heading', { name: /Administration Leopardo RH/i }),
    ).toBeVisible()
  })

  test('login form shows validation on empty submit', async ({ page }) => {
    const emailInput = page.getByLabel(/Adresse email/i)
    const submitButton = page.getByRole('button', { name: /Se connecter/i })

    await emailInput.clear()
    await submitButton.click()

    // HTML5 validation should prevent submission
    await expect(emailInput).toHaveAttribute('required', '')
  })

  test('login form shows error on invalid credentials', async ({ page }) => {
    await page.getByLabel(/Adresse email/i).fill('invalid@example.com')
    await page.locator('#password').fill('wrongpassword')
    await page.getByRole('button', { name: /Se connecter/i }).click()

    // Should show error message after failed API call
    await expect(
      page.locator('.bg-red-50').or(page.getByText(/Erreur de connexion/i)),
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

  test('forgot password link is present', async ({ page }) => {
    await expect(
      page.getByRole('link', { name: /Mot de passe oublie/i }),
    ).toBeVisible()
  })
})
