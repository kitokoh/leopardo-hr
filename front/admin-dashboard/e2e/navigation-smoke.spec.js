import { expect, test } from '@playwright/test'

test.describe('Navigation smoke tests', () => {
  test('unauthenticated user is redirected to login', async ({ page }) => {
    await page.goto('/')
    await expect(page).toHaveURL(/\/login/)
  })

  test('login page has correct form structure', async ({ page }) => {
    await page.goto('/login')

    const emailInput = page.getByLabel(/Adresse email/i)
    const passwordInput = page.locator('#password')
    const submitButton = page.getByRole('button', { name: /Se connecter/i })

    await expect(emailInput).toBeVisible()
    await expect(passwordInput).toBeVisible()
    await expect(submitButton).toBeVisible()
    await expect(submitButton).toBeEnabled()
  })

  test('login form shows validation errors for empty submit', async ({ page }) => {
    await page.goto('/login')

    const submitButton = page.getByRole('button', { name: /Se connecter/i })
    await submitButton.click()

    // HTML5 validation should prevent submission with empty required fields
    const emailInput = page.getByLabel(/Adresse email/i)
    const isValid = await emailInput.evaluate(
      (el) => el.validity.valid,
    )
    expect(isValid).toBe(false)
  })

  test('login form shows error for invalid credentials', async ({ page }) => {
    await page.goto('/login')

    await page.getByLabel(/Adresse email/i).fill('invalid@example.com')
    await page.locator('#password').fill('wrongpassword')
    await page.getByRole('button', { name: /Se connecter/i }).click()

    // Should show an error message or stay on login page
    await expect(page).toHaveURL(/\/login/)
  })
})
