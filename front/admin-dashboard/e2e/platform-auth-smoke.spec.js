import { expect, test } from '@playwright/test'

test('platform administrator can sign in and reach the admin dashboard', async ({ page }) => {
  let loginRequestSeen = false

  await page.route('**/api/v1/platform/auth/login', async (route) => {
    loginRequestSeen = true

    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        data: {
          id: 1,
          name: 'Super Administrateur',
          email: 'admin@leopardo-rh.com',
          role: 'super_admin',
          two_fa_enabled: false,
        },
        token: 'platform-admin-token',
        token_type: 'Bearer',
      }),
    })
  })

  await page.route('**/api/v1/platform/auth/me', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        data: {
          id: 1,
          name: 'Super Administrateur',
          email: 'admin@leopardo-rh.com',
          role: 'super_admin',
          two_fa_enabled: false,
        },
      }),
    })
  })

  await page.goto('/login')
  await page.getByLabel(/Adresse email/i).fill('admin@leopardo-rh.com')
  await page.locator('#password').fill('password123')
  await page.getByRole('button', { name: /Se connecter/i }).click()

  await expect(page).toHaveURL(/\/$/)
  await expect(page.locator('body')).toContainText(/Tableau de bord|Dashboard/i)
  await expect(page.locator('body')).toContainText(/Cockpit plateforme|Synthese commerciale/i)
  expect(loginRequestSeen).toBe(true)
})

test('platform demo selector does not advertise tenant employee accounts', async ({ page }) => {
  await page.goto('/login')
  await page.getByRole('button', { name: /Acces Demo/i }).click()

  await expect(page.locator('body')).toContainText('Platform Admin')
  await expect(page.locator('body')).toContainText(/administrateurs plateforme/i)
  await expect(page.locator('body')).not.toContainText('Ahmed Benali')
  await expect(page.locator('body')).not.toContainText('karim.aouad@techcorp-algerie.dz')
})
