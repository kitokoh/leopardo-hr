import { expect, test } from '@playwright/test'

test('password visibility toggle works on the admin login form', async ({ page }) => {
  await page.goto('/login')

  const passwordInput = page.locator('#password')
  const toggleButton = page.getByRole('button', { name: /Afficher le mot de passe/i })

  await passwordInput.fill('MotDePasseTresSecret123')
  await expect(passwordInput).toHaveAttribute('type', 'password')

  await toggleButton.click()
  await expect(passwordInput).toHaveAttribute('type', 'text')
  await expect(page.getByRole('button', { name: /Masquer le mot de passe/i })).toBeVisible()

  await page.getByRole('button', { name: /Masquer le mot de passe/i }).click()
  await expect(passwordInput).toHaveAttribute('type', 'password')
})
