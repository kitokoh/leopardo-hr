import { expect, test } from '@playwright/test'

// PA2-COMM-013 — Fallback polling robuste : si le canal push (Socket.IO)
// n'est pas disponible (proxy/firewall, serveur down, ...), l'admin-dashboard
// doit continuer a recevoir les notifications via un polling REST regulier
// de /notifications plutot que de rester silencieusement bloque.
test('falls back to REST polling when the push (Socket.IO) channel is unavailable', async ({ page }) => {
  await page.route('**/api/v1/platform/auth/login', async (route) => {
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

  await page.route(/\/api\/v1\/platform\/auth\/me(?:\?.*)?$/, async (route) => {
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

  await page.route(/\/api\/v1\/admin\/dashboard\/stats(\?.*)?$/, async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        totalUsers: 0,
        totalCompanies: 0,
        activeSubscriptions: 0,
        monthlyRevenue: 0,
        newUsersToday: 0,
        newCompaniesToday: 0,
        supportTickets: 0,
        systemHealth: 'good',
      }),
    })
  })

  await page.route(/\/api\/v1\/admin\/dashboard\/(activities|alerts)(\?.*)?$/, async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify([]),
    })
  })

  let notificationsPolled = 0
  await page.route(/\/api\/v1\/notifications(\?.*)?$/, async (route) => {
    notificationsPolled += 1
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        data: [
          {
            id: 'notif-1',
            type: 'system_alert',
            title: 'Maintenance planifiee',
            body: 'Une fenetre de maintenance est prevue ce soir.',
            is_read: false,
            created_at: new Date().toISOString(),
          },
        ],
        meta: { unread_count: 1 },
      }),
    })
  })

  await page.goto('/login')
  await page.getByLabel(/Adresse email/i).fill('admin@leopardo-rh.com')
  await page.locator('#password').fill('password123')
  await page.getByRole('button', { name: /^Se connecter$/i }).click()

  await expect(page).toHaveURL(/\/$/)

  // No websocket server is reachable in this test environment (dev server
  // proxy has no socket.io upstream), so Socket.IO will fail to connect and
  // the store must switch to the polling fallback rather than leaving the
  // admin without any notification updates.
  await expect(page.getByText(/Mode secours \(polling\)/i)).toBeVisible({ timeout: 15000 })

  await expect.poll(() => notificationsPolled, { timeout: 15000 }).toBeGreaterThan(0)
})
