import { expect, test } from '@playwright/test'

test('login screen loads for administrators', async ({ page }) => {
  await page.goto('/login')

  await expect(page).toHaveTitle(/Leopardo RH/i)
  await expect(
    page.getByRole('heading', { name: /Administration Leopardo RH/i }),
  ).toBeVisible()
  await expect(page.getByLabel(/Adresse email/i)).toBeVisible()
  await expect(page.getByLabel(/Mot de passe/i)).toBeVisible()
  await expect(page.getByRole('button', { name: /Se connecter/i })).toBeVisible()
})
