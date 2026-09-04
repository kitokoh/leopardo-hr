import { expect, test } from '@playwright/test'

// TRAVEL-604 (#6081) — Réservations guichet : liste, détail passagers,
// confirmation (action critique confirmée par dialogue).
const AUTH_ME = {
  data: { id: 1, name: 'Super Admin', email: 'admin@leopardo-rh.com', role: 'super_admin' },
}

const bookings = [
  {
    id: 100,
    reference: 'GV-2026-0001',
    trip_id: 7,
    status: 'pending',
    passenger_count: 2,
    total_amount_minor: 6000,
    currency: 'XOF',
    booking_source: 'office',
    payment_status: 'pending',
    expires_at: '2026-08-30T18:00:00Z',
    version: 1,
    created_at: '2026-08-30T08:00:00Z',
    updated_at: '2026-08-30T08:00:00Z',
  },
]

const trips = [
  { id: 7, code: 'ABJ-BKE-001', route_id: 3, departure_date: '2026-09-01', departure_time: '08:00', status: 'published' },
]

const detail = {
  id: 100,
  reference: 'GV-2026-0001',
  trip_id: 7,
  status: 'pending',
  passenger_count: 2,
  total_amount_minor: 6000,
  currency: 'XOF',
  booking_source: 'office',
  payment_status: 'pending',
  expires_at: '2026-08-30T18:00:00Z',
  version: 1,
  passengers: [
    { id: 1, booking_id: 100, full_name: 'Aya Konan', birth_date: '1990-05-01', document_type: 'national_id', has_document: true, age_category: 'adult', class_id: 1, seat_number: 12, unit_price_minor: 3000 },
    { id: 2, booking_id: 100, full_name: 'Kofi Konan', birth_date: '2015-02-11', document_type: null, has_document: false, age_category: 'child', class_id: 1, seat_number: 13, unit_price_minor: 3000 },
  ],
  tickets: [],
  created_at: '2026-08-30T08:00:00Z',
  updated_at: '2026-08-30T08:00:00Z',
}

test.describe('Réservations travel (TRAVEL-604)', () => {
  test('liste les réservations et confirme une vente guichet', async ({ page }) => {
    await page.route(/\/api\/v1\/platform\/auth\/me(\?.*)?$/, (route) =>
      route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(AUTH_ME) }),
    )
    await page.route(/\/api\/v1\/travel\/ping(\?.*)?$/, (route) =>
      route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({}) }),
    )
    await page.route(/\/api\/v1\/travel\/trips(\?.*)?$/, (route) =>
      route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: trips, meta: {} }) }),
    )
    await page.route(/\/api\/v1\/travel\/bookings(\?.*)?$/, (route) =>
      route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: bookings, meta: {} }) }),
    )
    await page.route(/\/api\/v1\/travel\/bookings\/100\/confirm(\?.*)?$/, (route) => {
      bookings[0].status = 'confirmed'
      bookings[0].payment_status = 'confirmed'
      return route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: { ...detail, status: 'confirmed', payment_status: 'confirmed' } }) })
    })
    await page.route(/\/api\/v1\/travel\/bookings\/100(\?.*)?$/, (route) =>
      route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: detail }) }),
    )
    await page.addInitScript(() => {
      sessionStorage.setItem('admin_token', 'e2e-travel-token')
    })

    await page.goto('/travel/bookings')

    await expect(page.getByText('GV-2026-0001')).toBeVisible()
    await expect(page.getByRole('cell', { name: 'ABJ-BKE-001' })).toBeVisible()

    // Détail : passagers visibles
    await page.getByRole('button', { name: /Détail/i }).click()
    await expect(page.getByText('Aya Konan')).toBeVisible()
    await expect(page.getByText('Kofi Konan')).toBeVisible()
    await page.getByRole('button', { name: /Fermer/i }).click()

    // Confirmation via dialogue (action critique)
    await page.getByRole('button', { name: /Confirmer/i }).click()
    await page.getByRole('button', { name: 'Confirmer', exact: true }).last().click()
    await expect(page.getByRole('cell', { name: 'Confirmée' }).first()).toBeVisible()
  })
})
