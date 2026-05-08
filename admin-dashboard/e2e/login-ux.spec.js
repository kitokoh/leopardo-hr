import { expect, test } from '@playwright/test'

test('password visibility toggle works', async ({ page }) => {
  await page.goto('/login')

  const passwordInput = page.locator('#password')
  const toggleButton = page.getByRole('button', { name: /Afficher le mot de passe/i })

  // Initial state
  await expect(passwordInput).toHaveAttribute('type', 'password')
  await expect(toggleButton).toBeVisible()

  await page.screenshot({ path: 'login-initial.png' })

  // Click to show
  await toggleButton.click()
  await expect(passwordInput).toHaveAttribute('type', 'text')

  await page.screenshot({ path: 'login-password-visible.png' })

  // Click to hide
  const hideButton = page.getByRole('button', { name: /Masquer le mot de passe/i })
  await hideButton.click()
  await expect(passwordInput).toHaveAttribute('type', 'password')
})
