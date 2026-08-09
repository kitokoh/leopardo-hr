import { expect, test } from '@playwright/test'

// PA2-COMM-013 — Fallback polling robuste : si le canal push (Socket.IO)
// n'est pas disponible (proxy/firewall, serveur down, ...), l'admin-dashboard
// doit continuer a recevoir les notifications via un polling REST regulier
// de /notifications plutot que de rester silencieusement bloque.
//
// Flaky tracking (issue #1575) : ce test dépend du timing de détection
// d'échec Socket.IO (grace period 8s + retry socket.io). Stabilisation :
//   - les connexions socket.io sont avortées immédiatement (mock déterministe),
//   - timeout global du test relevé à 60 s (l'assertion de polling peut
//     légitimement prendre ~8-30 s),
//   - retries séparés (3) via describe.configure.
test.describe.configure({ retries: 3 })

test('falls back to REST polling when the push (Socket.IO) channel is unavailable', async ({ page }) => {
  // Le serveur de dev n'a pas d'upstream socket.io : pour rendre la détection
  // d'échec déterministe (pas de dépendance à la grace period), on avorte les
  // connexions socket.io dès leur tentative.
  await page.route('**/socket.io/**', (route) => route.abort())
  await page.route(/\/socket\.io\/?$/, (route) => route.abort())

  test.setTimeout(60_000)

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
  // admin without any notification updates. Assert the REST polling first
  // (the actual contract under test), then the header fallback label — with
  // generous timeouts since socket failure detection depends on the store's
  // connect grace period (8s) plus socket.io's own retry timing.
  await expect.poll(() => notificationsPolled, { timeout: 30000 }).toBeGreaterThan(0)
  await expect(page.getByText(/Mode secours \(polling\)|D\u00e9connect\u00e9/i)).toBeVisible({ timeout: 30000 })
})
