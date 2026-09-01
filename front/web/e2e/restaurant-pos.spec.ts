import { expect, test, type Page } from '@playwright/test';
import { setSessionCookie } from './session-helpers';

/**
 * RESTO-902 (#6231) — Spécs E2E du parcours POS (prise de commande &
 * encaissement) sur le portail tenant web (RESTO-705, #6218).
 *
 * Stratégie : mocks API (page.route) — aucun backend requis, déterministe en
 * CI. Le contrat mocké reproduit les réponses réelles du backend BC-25
 * (branches/catalogue/sessions/commandes/addition/paiement).
 *
 * Parcours couvert (golden flow GJ-RESTO-01) :
 *   caisse ouverte → commande → ajout article → soumission → confirmation
 *   cuisine → addition → encaissement espèces → commande payée.
 */

const baseUser = {
  id: 101,
  first_name: 'Serveur',
  last_name: 'Test',
  email: 'serveur@resto.test.cm',
  role: 'manager',
  manager_role: 'manager',
  language: 'fr',
  is_rtl: false,
  capabilities: {
    can_view_dashboard: true,
    restaurant: true,
    'restaurant.kitchen': true,
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

const branches = {
  data: [{ id: 1, name: 'Branche Douala Centre' }],
};

const categories = {
  data: [{ id: 1, name: 'Plats' }],
};

const products = {
  data: [
    {
      id: 10,
      category_id: 1,
      name: 'Poulet braisé',
      price_minor: 5000,
      currency: 'XAF',
      is_available: true,
    },
    {
      id: 11,
      category_id: 1,
      name: 'Poisson braisé',
      price_minor: 4500,
      currency: 'XAF',
      is_available: true,
    },
  ],
};

const sessionOpen = { data: { id: 1, branch_id: 1, status: 'open' } };

/**
 * Mock d'état de la commande : les transitions (submit/confirm/pay) changent
 * le statut renvoyé par GET /orders/{id} — comme le backend réel.
 */
function orderStateFactory() {
  const state = {
    id: 1,
    reference: 'CMD-001',
    status: 'draft',
    items: [] as Array<{
      id: number;
      product_id: number;
      name: string;
      quantity: number;
      line_total_minor: number;
      status: string;
    }>,
  };
  return {
    get: () => ({ data: { ...state, items: [...state.items] } }),
    reset: () => {
      state.status = 'draft';
      state.items = [];
    },
    addItem: () => {
      state.items.push({
        id: 5,
        product_id: 10,
        name: 'Poulet braisé',
        quantity: 1,
        line_total_minor: 5000,
        status: 'active',
      });
    },
    transition: (to: string) => {
      state.status = to;
    },
  };
}

async function mockCommon(page: Page) {
  await page.route('**/api/v1/notifications**', (route) =>
    route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ data: [], meta: { unread_count: 0 } }),
    }),
  );
  // Pattern existant (client-visual-smoke.spec.ts) : naviguer d'abord vers une
  // page réelle du site, sinon localStorage sur about:blank → SecurityError.
  await page.goto('/auth/login', { waitUntil: 'domcontentloaded' });
  await page.evaluate((user) => {
    window.localStorage.setItem('auth_token', 'resto-pos-token');
    window.localStorage.setItem('auth_user', JSON.stringify(user));
  }, baseUser);
  await setSessionCookie(page);
}

test.describe('Restaurant — POS portail tenant (RESTO-902/#6231)', () => {
  test('golden flow : caisse → commande → article → cuisine → encaissement espèces', async ({ page }) => {
    const order = orderStateFactory();

    await mockCommon(page);

    // ── Catalogue & référentiel ───────────────────────────────
    await page.route('**/api/v1/restaurant/branches**', (route) =>
      route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(branches) }),
    );
    await page.route('**/api/v1/restaurant/categories**', (route) =>
      route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(categories) }),
    );
    await page.route('**/api/v1/restaurant/products**', (route) =>
      route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(products) }),
    );

    // ── Caisse : pas de session ouverte → bouton d'ouverture ──
    await page.route('**/api/v1/restaurant/pos-sessions/current**', (route) =>
      route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: null }) }),
    );
    await page.route('**/api/v1/restaurant/pos-sessions', (route) =>
      route.fulfill({ status: 201, contentType: 'application/json', body: JSON.stringify(sessionOpen) }),
    );

    // ── Commandes : création + transitions d'état ─────────────
    await page.route('**/api/v1/restaurant/orders**', (route) =>
      route.fulfill({
        status: 201,
        contentType: 'application/json',
        body: JSON.stringify({ data: { id: 1, reference: 'CMD-001', status: 'draft', items: [] } }),
      }),
    );
    await page.route('**/api/v1/restaurant/orders/1/items**', (route) => {
      order.addItem();
      return route.fulfill({ status: 201, contentType: 'application/json', body: JSON.stringify({ data: { id: 5 } }) });
    });
    await page.route('**/api/v1/restaurant/orders/1/submit**', (route) => {
      order.transition('open');
      return route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(order.get()) });
    });
    await page.route('**/api/v1/restaurant/orders/1/confirm**', (route) => {
      order.transition('confirmed');
      return route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(order.get()) });
    });
    await page.route('**/api/v1/restaurant/orders/1/pay**', (route) => {
      order.transition('paid');
      return route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(order.get()) });
    });
    await page.route('**/api/v1/restaurant/orders/1/bill**', (route) =>
      route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          data: { id: 1, subtotal_minor: 4500, tax_minor: 500, total_minor: 5000, currency: 'XAF' },
        }),
      }),
    );
    await page.route('**/api/v1/restaurant/orders/1', (route) =>
      route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(order.get()) }),
    );

    await page.goto('/restaurant/pos', { waitUntil: 'domcontentloaded' });

    // ── Écran : titre, branche, catalogue ─────────────────────
    await expect(page.getByText('Point de vente (POS)')).toBeVisible({ timeout: 20_000 });
    await expect(page.getByRole('option', { name: 'Branche Douala Centre' })).toHaveCount(1);
    await expect(page.getByText('Poulet braisé')).toBeVisible();
    await expect(page.getByText('Poisson braisé')).toBeVisible();

    // ── Caisse : ouverture ────────────────────────────────────
    await expect(page.getByRole('button', { name: 'Ouvrir la caisse' })).toBeVisible();
    await page.getByRole('button', { name: 'Ouvrir la caisse' }).click();

    // ── Commande : création puis ajout d'article ──────────────
    await expect(page.getByRole('button', { name: 'Nouvelle commande' })).toBeVisible();
    await page.getByRole('button', { name: 'Nouvelle commande' }).click();
    await expect(page.getByText('CMD-001').first()).toBeVisible();

    await page.getByText('Poulet braisé').click();
    await expect(page.getByText('1 × Poulet braisé')).toBeVisible();

    // ── Soumission cuisine puis confirmation ──────────────────
    await page.getByRole('button', { name: 'Soumettre' }).click();
    await expect(page.getByText('Ouverte')).toBeVisible();
    await page.getByRole('button', { name: 'Confirmer (cuisine)' }).click();

    // ── Addition puis encaissement espèces ────────────────────
    await page.getByRole('button', { name: 'Addition' }).click();
    await expect(page.getByText('Total', { exact: true })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Encaisser espèces' })).toBeVisible();
    await page.getByRole('button', { name: 'Encaisser espèces' }).click();

    // ── Commande payée ────────────────────────────────────────
    await expect(page.getByText('Commande payée')).toBeVisible({ timeout: 15_000 });
  });

  test('catalogue : filtre des produits indisponibles (is_available=false)', async ({ page }) => {
    await mockCommon(page);
    await page.route('**/api/v1/restaurant/branches**', (route) =>
      route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(branches) }),
    );
    await page.route('**/api/v1/restaurant/categories**', (route) =>
      route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(categories) }),
    );
    await page.route('**/api/v1/restaurant/products**', (route) =>
      route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          data: [
            { id: 10, category_id: 1, name: 'Disponible', price_minor: 1000, currency: 'XAF', is_available: true },
            { id: 11, category_id: 1, name: 'Rupture', price_minor: 2000, currency: 'XAF', is_available: false },
          ],
        }),
      }),
    );
    await page.route('**/api/v1/restaurant/pos-sessions/current**', (route) =>
      route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: null }) }),
    );

    await page.goto('/restaurant/pos', { waitUntil: 'domcontentloaded' });

    await expect(page.getByText('Disponible')).toBeVisible({ timeout: 20_000 });
    await expect(page.getByText('Rupture')).toHaveCount(0);
  });
});
