import { expect, test } from '@playwright/test'

// TRAVEL-607 (#6084) — Rapports : cartes KPI, tableau des ventes, export CSV
// (URL signée ouverte dans une nouvelle fenêtre).
const AUTH_ME = {
  data: { id: 1, name: 'Super Admin', email: 'admin@leopardo-rh.com', role: 'super_admin' },
}

const kpis = {
  data: {
    sales_minor: 250000,
    bookings_count: 42,
    passengers_count: 61,
    revenue_minor: 240000,
    refunds_minor: 10000,
    occupancy_rate: 0.78,
    cancellations_count: 3,
    period: { from: '2026-08-01T00:00:00Z', to: '2026-08-30T00:00:00Z' },
  },
}

const sales = {
  data: {
    data: [
      { id: 100, reference: 'GV-2026-0001', passenger_count: 2, total_amount_minor: 6000, currency: 'XOF', status: 'confirmed', created_at: '2026-08-30T08:00:00Z' },
    ],
    meta: { current_page: 1, last_page: 1, per_page: 100, total: 1 },
  },
  summary: { booking_count: 1, passenger_count: 2, total_amount_minor: 6000 },
}

const occupancy = { data: { data: [
  { id: 7, code: 'ABJ-BKE-001', route_id: 3, departure_date: '2026-09-01', departure_time: '08:00', total_seats: 40, sold_seats: 31, reserved_seats: 3, free_seats: 6, occupancy_rate: 0.775 },
] } }

const revenue = { data: { confirmed_minor: 240000, refunded_minor: 10000, net_minor: 230000, by_route: [] } }
const cancellations = { data: { cancelled_count: 3, total_final_count: 42, cancellation_rate: 0.0714, by_reason: [], by_source: [] } }
const exportResult = { data: { request_hash: 'abc123', row_count: 42, mime_type: 'text/csv', signed_url: 'https://storage.example.com/exports/sales.csv?sig=xyz', expires_at: '2026-08-30T10:00:00Z' } }

test.describe('Rapports travel (TRAVEL-607)', () => {
  test('affiche les KPIs et le tableau des ventes', async ({ page }) => {
    await page.route(/\/api\/v1\/platform\/auth\/me(\?.*)?$/, (route) =>
      route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(AUTH_ME) }),
    )
    await page.route(/\/api\/v1\/travel\/ping(\?.*)?$/, (route) =>
      route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({}) }),
    )
    await page.route(/\/api\/v1\/travel\/reports\/dashboard(\?.*)?$/, (route) =>
      route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(kpis) }),
    )
    await page.route(/\/api\/v1\/travel\/reports\/sales(\?.*)?$/, (route) =>
      route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(sales) }),
    )
    await page.route(/\/api\/v1\/travel\/reports\/occupancy(\?.*)?$/, (route) =>
      route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(occupancy) }),
    )
    await page.route(/\/api\/v1\/travel\/reports\/revenue(\?.*)?$/, (route) =>
      route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(revenue) }),
    )
    await page.route(/\/api\/v1\/travel\/reports\/cancellations(\?.*)?$/, (route) =>
      route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(cancellations) }),
    )
    await page.addInitScript(() => {
      sessionStorage.setItem('admin_token', 'e2e-travel-token')
    })

    await page.goto('/travel/reports')

    await expect(page.getByText('GV-2026-0001')).toBeVisible()
    // Cartes KPI (occupation 78 %)
    await expect(page.getByText('78 %').first()).toBeVisible()
  })

  test('export CSV ouvre l’URL signée', async ({ page }) => {
    await page.route(/\/api\/v1\/platform\/auth\/me(\?.*)?$/, (route) =>
      route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(AUTH_ME) }),
    )
    await page.route(/\/api\/v1\/travel\/ping(\?.*)?$/, (route) =>
      route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({}) }),
    )
    for (const name of ['dashboard', 'sales', 'occupancy', 'revenue', 'cancellations']) {
      await page.route(new RegExp(`/api/v1/travel/reports/${name}(\\?.*)?$`), (route) =>
        route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(name === 'sales' ? sales : name === 'dashboard' ? kpis : name === 'occupancy' ? occupancy : name === 'revenue' ? revenue : cancellations) }),
      )
    }
    await page.route(/\/api\/v1\/travel\/reports\/export(\?.*)?$/, (route) =>
      route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(exportResult) }),
    )
    await page.addInitScript(() => {
      sessionStorage.setItem('admin_token', 'e2e-travel-token')
      window.__open = window.open
      window.open = (url) => { window.__openedUrl = url; return null }
    })

    await page.goto('/travel/reports')
    await page.getByRole('button', { name: /Exporter CSV/i }).click()

    await expect
      .poll(async () => page.evaluate(() => window.__openedUrl || null))
      .toBe('https://storage.example.com/exports/sales.csv?sig=xyz')
  })
})
