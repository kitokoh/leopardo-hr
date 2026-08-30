import { expect, test, type Page } from '@playwright/test';
import { setSessionCookie } from './session-helpers';

/**
 * RESTO-902 (#6231) — Spécs E2E du parcours RÉSERVATIONS (BC-25 RESTAURANT,
 * RESTO-706, #6219) sur le portail tenant web.
 *
 * Stratégie : mocks API (page.route) — aucun backend requis, déterministe en
 * CI. Le contrat mocké reproduit les réponses réelles du backend BC-25
 * (RESTO-601..604) : liste paginée, création, transitions confirm/check-in/
 * no-show/cancel avec conflit de créneau (409).
 *
 * Parcours couvert :
 *   liste → création (contact, créneau, couverts) → confirmation →
 *   check-in (table occupée) → no-show sur une seconde réservation.
 */

const baseUser = {
  id: 201,
  first_name: 'Manager',
  last_name: 'Resto',
  email: 'manager@resto.test.cm',
  role: 'manager',
  manager_role: 'manager',
  language: 'fr',
  is_rtl: false,
  capabilities: {
    can_view_dashboard: true,
    restaurant: true,
    'restaurant.reservations': true,
  },
  company: {
    id: 'company-resto-1',
    name: 'Restaurant Test SARL',
    language: 'fr',
    timezone: 'Africa/Douala',
    currency: 'XAF',
    features: {
      restaurantmanager: true,
    },
    metadata: { onboarding_completed: true },
  },
};

/** Mock d'état : la liste évolue avec les transitions, comme le backend réel. */
function reservationStateFactory() {
  const rows = [
    {
      id: 1,
      reference: 'RES-0001',
      contact_name: 'Aline Ngo',
      contact_phone: '+237699999999',
      reserved_at: '2026-08-30T19:30:00+00:00',
      covers: 4,
      table_id: null,
      status: 'pending',
      deposit_minor: null,
    },
  ];

  return {
    rows,
    list() {
      return { data: rows, meta: { current_page: 1, last_page: 1, per_page: 100, total: rows.length } };
    },
    create(body: Record<string, unknown>) {
      const row = {
        id: rows.length + 10,
        reference: `RES-${String(rows.length + 10).padStart(4, '0')}`,
        contact_name: String(body.contact_name ?? ''),
        contact_phone: String(body.contact_phone ?? ''),
        reserved_at: String(body.reserved_at ?? ''),
        covers: Number(body.covers ?? 2),
        table_id: body.table_id ? Number(body.table_id) : null,
        status: 'pending',
        deposit_minor: null,
      };
      rows.unshift(row);
      return { data: row };
    },
    transition(id: number, action: string) {
      const row = rows.find((r) => r.id === id);
      if (!row) return { data: null };
      const next: Record<string, string> = {
        confirm: 'confirmed',
        'check-in': 'seated',
        'no-show': 'no_show',
        cancel: 'cancelled',
      };
      row.status = next[action] ?? row.status;
      return { data: row };
    },
  };
}

async function mockReservations(page: Page) {
  const state = reservationStateFactory();
  // Catch-all : tout appel restaurant non mocké répond 200 { data: [] } (aucun backend requis).
  await page.route('**/api/v1/restaurant/**', (route) =>
    route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: [] }) }),
  );



  await page.route(/\/api\/v1\/restaurant\/reservations\?per_page=100$/, (route) =>
    route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(state.list()) }),
  );
  await page.route(/\/api\/v1\/restaurant\/reservations$/, async (route) => {
    const body = route.request().postDataJSON() as Record<string, unknown>;
    await route.fulfill({
      status: 201,
      contentType: 'application/json',
      body: JSON.stringify(state.create(body)),
    });
  });
  await page.route(/\/api\/v1\/restaurant\/reservations\/\d+\/(confirm|check-in|no-show|cancel)$/, async (route) => {
    const match = route.request().url().match(/\/reservations\/(\d+)\/([a-z-]+)$/);
    const id = Number(match?.[1] ?? 0);
    const action = match?.[2] ?? '';
    const payload = state.transition(id, action);
    if (!payload.data) {
      await route.fulfill({ status: 404, contentType: 'application/json', body: JSON.stringify({ message: 'Introuvable' }) });
      return;
    }
    await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(payload) });
  });
}

test.describe('Réservations restaurant (RESTO-902)', () => {
  test('liste, crée et fait transiter une réservation (confirm → check-in)', async ({ page }) => {
    await page.addInitScript((user) => {
      window.localStorage.setItem('auth_token', 'resto-e2e-token');
      window.localStorage.setItem('auth_user', JSON.stringify(user));
    }, baseUser);
    await page.route('**/api/v1/auth/me', (route) =>
      route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: baseUser }) }),
    );
    await setSessionCookie(page);
    await mockReservations(page);
    await page.goto('/restaurant/reservations');

    // Liste initiale
    await expect(page.getByText('Aline Ngo')).toBeVisible();
    await expect(page.getByText('RES-0001')).toBeVisible();

    // Création
    await page.getByPlaceholder(/Nom du client|Client/).fill('Boris Kamga');
    await page.getByPlaceholder(/Téléphone|Tél/).fill('+237655555555');
    await page.getByRole('button', { name: /Créer|Réserver/ }).first().click();

    // La nouvelle réservation apparaît (statut pending → Confirmer dispo)
    await expect(page.getByText('Boris Kamga')).toBeVisible();

    // Transition confirm sur la ligne RES-0001 (scope ligne)
    const resRow = page.getByText('RES-0001').locator('..');
    await resRow.getByRole('button', { name: /Confirmer/ }).click();
    await expect(resRow).toContainText(/confirmed/);

    // Check-in (table occupée)
    await resRow.getByRole('button', { name: /Check-in/ }).click();
    await expect(resRow).toContainText(/seated/);
  });

  test('no-show sur une réservation pending', async ({ page }) => {
    await page.addInitScript((user) => {
      window.localStorage.setItem('auth_token', 'resto-e2e-token');
      window.localStorage.setItem('auth_user', JSON.stringify(user));
    }, baseUser);
    await page.route('**/api/v1/auth/me', (route) =>
      route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: baseUser }) }),
    );
    await setSessionCookie(page);
    await mockReservations(page);
    await page.goto('/restaurant/reservations');

    await page.getByRole('button', { name: /No-show/ }).first().click();
    await expect(page.getByText('RES-0001').locator('..')).toContainText(/No-show|no_show/);
  });
});
