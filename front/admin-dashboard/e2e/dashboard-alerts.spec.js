import { expect, test } from '@playwright/test'

// Issue #2747 — le store dashboard affectait les réponses brutes
// (enveloppe Laravel {data:[...]}) à systemAlerts/recentActivities :
// criticalAlerts() appelait .filter sur un objet → TypeError sur le happy
// path connecté → badge alertes du header, compteur sidebar et
// SystemAlertsOverlay cassés. Le mock bare-array ne déclenchait pas
// l'erreur (faux vert) : ce spec mocke la VRAIE forme {data:[...]}.
test.describe('Dashboard alerts (real API envelope)', () => {
  test.use({ viewport: { width: 1440, height: 900 } })

  test('renders critical alerts overlay without TypeError', async ({ page }) => {
    const pageErrors = []
    page.on('pageerror', (error) => pageErrors.push(error.message))

    // Auth (forme réelle)
    await page.route(/\/api\/v1\/platform\/auth\/me(\?.*)?$/, async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          data: {
            id: 1,
            name: 'Super Admin',
            email: 'admin@leopardo-rh.com',
            role: 'super_admin',
          },
        }),
      })
    })

    // Endpoints du cockpit — enveloppe {data:[...]} réelle
    await page.route(/\/api\/v1\/admin\/dashboard\/stats(\?.*)?$/, async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          data: {
            totalUsers: 42,
            totalCompanies: 3,
            activeSubscriptions: 2,
            monthlyRevenue: 1200,
            newUsersToday: 1,
            newCompaniesToday: 0,
            supportTickets: 5,
            systemHealth: 'good',
          },
        }),
      })
    })

    await page.route(/\/api\/v1\/admin\/dashboard\/activities(\?.*)?$/, async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          data: [{ id: 'act-1', description: 'Activité de test', created_at: new Date().toISOString() }],
        }),
      })
    })

    await page.route(/\/api\/v1\/admin\/dashboard\/alerts(\?.*)?$/, async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          data: [
            { id: 'critical-1', level: 'critical', message: 'Alerte critique de test' },
            { id: 'warn-1', level: 'warning', message: 'Alerte warning de test' },
          ],
        }),
      })
    })

    // Token admin (sessionStorage, convention #1299)
    await page.addInitScript(() => {
      sessionStorage.setItem('admin_token', 'e2e-fake-token')
    })

    await page.goto('/')

    // Le badge d'alertes du header (computed réactif criticalAlerts.length)
    // doit afficher le décompte dès le fetch — avec le bug #2747, le store
    // explosait sur l'enveloppe {data:[...]} et aucun badge ne s'affichait.
    await expect(page.getByRole('button').filter({ has: page.locator('.bg-red-500') }).first()).toBeVisible({ timeout: 15_000 })
    await expect(page.getByText('1', { exact: true }).first()).toBeVisible({ timeout: 15_000 })

    // Aucune erreur JS sur le parcours connecté (le TypeError #2747 échouerait ici).
    expect(pageErrors.filter((m) => m.includes('filter is not a function'))).toEqual([])
    expect(pageErrors).toEqual([])
  })
})
