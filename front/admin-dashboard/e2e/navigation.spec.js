import { expect, test } from '@playwright/test'

test.describe('Navigation and routing', () => {
  test('unauthenticated access to protected routes redirects to login', async ({ page }) => {
    const protectedRoutes = [
      '/',
      '/analytics',
      '/users',
      '/companies',
      '/payroll',
      '/leaves',
      '/contracts',
      '/exports',
    ]

    for (const route of protectedRoutes) {
      await page.goto(route)
      await expect(page).toHaveURL(/\/login/, {
        timeout: 5_000,
      })
    }
  })

  test('login page has correct title', async ({ page }) => {
    await page.goto('/login')
    await expect(page).toHaveTitle(/Leopardo RH/i)
  })

  test('404 page renders for unknown routes', async ({ page }) => {
    await page.goto('/this-route-does-not-exist')

    // Should redirect to login (unauthenticated) or show 404
    const isLogin = await page.url().includes('/login')
    const has404 = await page.getByText(/404|introuvable|not found/i).isVisible().catch(() => false)

    expect(isLogin || has404).toBeTruthy()
  })

  test('login page renders all required elements', async ({ page }) => {
    await page.goto('/login')

    await expect(page.getByText('LRH')).toBeVisible()
    await expect(
      page.getByRole('heading', { name: /Administration Leopardo RH/i }),
    ).toBeVisible()
    await expect(
      page.getByText(/Connectez-vous a votre espace/i),
    ).toBeVisible()
    await expect(page.getByLabel(/Adresse email/i)).toBeVisible()
    await expect(page.locator('#password')).toBeVisible()
    await expect(page.getByRole('button', { name: /Se connecter/i })).toBeVisible()
    await expect(page.getByLabel(/Se souvenir de moi/i)).toBeVisible()
  })

  test('login form fields accept input', async ({ page }) => {
    await page.goto('/login')

    const email = page.getByLabel(/Adresse email/i)
    const password = page.locator('#password')

    await email.fill('admin@leopardo.rh')
    await expect(email).toHaveValue('admin@leopardo.rh')

    await password.fill('SecureP@ss123')
    await expect(password).toHaveValue('SecureP@ss123')
  })
})
