import { expect, test } from '@playwright/test'

// Smoke du portail web Laravel (blade) — connexion super administrateur à
// /platform/login (même domaine staging que la connexion manager).

test.describe('Portail blade — connexion super admin', () => {
  test('la page /platform/login charge avec une structure accessible', async ({ page }) => {
    await page.goto('/platform/login')

    await expect(
      page.getByRole('heading', { name: /Connexion plateforme/i }),
    ).toBeVisible()

    await expect(page.getByLabel('Email', { exact: true })).toBeVisible()
    await expect(page.getByLabel('Mot de passe', { exact: true })).toBeVisible()
    await expect(page.getByRole('button', { name: /Se connecter/i })).toBeVisible()
  })

  test('identifiants invalides → erreur visible + câblage ARIA (S-6)', async ({ page }) => {
    await page.goto('/platform/login')

    await page.getByLabel('Email').fill('ci-staging-platform-invalid@leopardo-rh.com')
    await page.getByLabel('Mot de passe').fill('mot-de-passe-inexistant')
    await page.getByRole('button', { name: /Se connecter/i }).click()

    const error = page.locator('#email-error')
    await expect(error).toBeVisible()
    await expect(error).toHaveText('Identifiants invalides.')
    await expect(error).toHaveAttribute('role', 'alert')

    const email = page.getByLabel('Email')
    await expect(email).toHaveAttribute('aria-invalid', 'true')
    await expect(email).toHaveAttribute('aria-describedby', 'email-error')
  })
})
