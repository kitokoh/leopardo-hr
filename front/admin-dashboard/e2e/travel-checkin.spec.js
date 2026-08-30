import { expect, test } from '@playwright/test'

// TRAVEL-605 (#6082) — Check-in & manifeste : sélection trajet, manifeste
// trié par siège, embarquement d'un billet, compteur exact.
const AUTH_ME = {
  data: { id: 1, name: 'Super Admin', email: 'admin@leopardo-rh.com', role: 'super_admin' },
}

const trips = [
  { id: 7, code: 'ABJ-BKE-001', route_id: 3, departure_date: '2026-09-01', departure_time: '08:00', status: 'published' },
]

const manifest = [
  { id: 1, booking_id: 100, full_name: 'Aya Konan', birth_date: '1990-05-01', document_type: 'national_id', has_document: true, age_category: 'adult', class_id: 1, seat_number: 12, unit_price_minor: 3000 },
  { id: 2, booking_id: 100, full_name: 'Kofi Konan', birth_date: '2015-02-11', document_type: null, has_document: false, age_category: 'child', class_id: 1, seat_number: 13, unit_price_minor: 3000 },
]

const bookingDetail = {
  id: 100,
  reference: 'GV-2026-0001',
  trip_id: 7,
  status: 'confirmed',
  passenger_count: 2,
  total_amount_minor: 6000,
  currency: 'XOF',
  booking_source: 'office',
  payment_status: 'confirmed',
  passengers: manifest,
  tickets: [
    { id: 50, ticket_number: 'TKT-2026-0001', booking_id: 100, passenger_id: 1, status: 'issued', issued_at: '2026-08-30T09:00:00Z' },
    { id: 51, ticket_number: 'TKT-2026-0002', booking_id: 100, passenger_id: 2, status: 'issued', issued_at: '2026-08-30T09:00:00Z' },
  ],
  created_at: '2026-08-30T08:00:00Z',
  updated_at: '2026-08-30T08:00:00Z',
}

test.describe('Check-in travel (TRAVEL-605)', () => {
  test('manifeste affiché et embarquement incrémente le compteur', async ({ page }) => {
    await page.route(/\/api\/v1\/platform\/auth\/me(\?.*)?$/, (route) =>
      route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(AUTH_ME) }),
    )
    await page.route(/\/api\/v1\/travel\/ping(\?.*)?$/, (route) =>
      route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({}) }),
    )
    await page.route(/\/api\/v1\/travel\/trips(\?.*)?$/, (route) =>
      route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: trips, meta: {} }) }),
    )
    await page.route(/\/api\/v1\/travel\/trips\/7\/manifest(\?.*)?$/, (route) =>
      route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: manifest }) }),
    )
    await page.route(/\/api\/v1\/travel\/bookings(\?.*)?$/, (route) =>
      route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: [bookingDetail], meta: {} }) }),
    )
    await page.route(/\/api\/v1\/travel\/bookings\/100(\?.*)?$/, (route) =>
      route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: bookingDetail }) }),
    )
    await page.route(/\/api\/v1\/travel\/classes(\?.*)?$/, (route) =>
      route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: [], meta: {} }) }),
    )
    await page.route(/\/api\/v1\/travel\/tickets\/50\/check-in(\?.*)?$/, (route) =>
      route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: { id: 50, status: 'checked_in' } }) }),
    )
    await page.addInitScript(() => {
      sessionStorage.setItem('admin_token', 'e2e-travel-token')
    })

    await page.goto('/travel/checkin')

    await page.getByLabel('Trajet').selectOption('7')
    await expect(page.getByText('Aya Konan')).toBeVisible()
    await expect(page.getByText('Kofi Konan')).toBeVisible()

    // Compteur initial 0/2 puis 1/2 après embarquement du premier billet
    await expect(page.getByText('0 / 2')).toBeVisible()
    await page.getByRole('button', { name: /Embarquer/i }).first().click()
    await expect(page.getByText('1 / 2')).toBeVisible()
    await expect(page.getByText('Embarqué').first()).toBeVisible()
  })
})
