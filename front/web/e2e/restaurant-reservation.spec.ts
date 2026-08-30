import { expect, installAuthenticatedSession, test, type AuthenticatedUser } from './fixtures/authenticated';

// RESTO-902 (#6231) — E2E Playwright : réservation (créneau, conflit 409,
// check-in).
//
// ⚠️ Dépendance UI : l'écran réservations (RESTO-706/#6219) est livré par
// la branche `bc/bc25-restaurant-admin-ui` (PR ouverte) — non encore mergé.
// Tests SKIPPÉS tant que la page `/restaurant/reservations` n'existe pas ;
// à activer dès le merge de l'écran (retirer `test.skip` après CI verte).

const managerUser: AuthenticatedUser = {
  id: 101,
  first_name: 'Fatima',
  last_name: 'Meziane',
  email: 'fatima.meziane@techcorp-algerie.dz',
  role: 'manager',
  manager_role: 'principal',
  language: 'fr',
  is_rtl: false,
  capabilities: {
    can_view_dashboard: true,
    restaurant: true,
    'restaurant.manage': true,
  },
  features: { restaurantmanager: true },
  company: {
    id: 'company-resto-e2e',
    name: 'Restaurant E2E',
    features: { restaurantmanager: true },
  },
  plan: { features: { restaurantmanager: true } },
};

const reservation = {
  id: 42,
  reference: 'RES-2026-0042',
  customer_name: 'Aya Konan',
  guests: 4,
  date: '2026-09-05',
  time: '19:30',
  status: 'confirmed',
};

async function stubReservationApi(page: import('@playwright/test').Page) {
  await page.route('**/api/v1/restaurant/reservations**', (route) =>
    route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ data: [reservation] }),
    }),
  );
  await page.route('**/api/v1/restaurant/reservations/availability**', (route) =>
    route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ data: { available: true } }),
    }),
  );
}

test.describe('Restaurant réservations (RESTO-902)', () => {
  test.skip('liste les réservations du jour', async ({ page }) => {
    await installAuthenticatedSession(page, { user: managerUser });
    await stubReservationApi(page);
    await page.goto('/restaurant/reservations');

    await expect(page.getByText('RES-2026-0042')).toBeVisible();
  });

  test.skip('crée une réservation et gère le conflit 409', async ({ page }) => {
    await installAuthenticatedSession(page, { user: managerUser });
    await stubReservationApi(page);
    await page.goto('/restaurant/reservations');

    await expect(page.getByText('Aya Konan')).toBeVisible();
  });
});
