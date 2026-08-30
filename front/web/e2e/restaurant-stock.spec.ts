import { expect, test, type Page } from '@playwright/test';
import { setSessionCookie } from './session-helpers';

/**
 * RESTO-902 (#6231) — Spécs E2E du parcours STOCK & ACHATS (BC-25 RESTAURANT,
 * RESTO-706, #6219) sur le portail tenant web.
 *
 * Stratégie : mocks API (page.route) — aucun backend requis, déterministe en
 * CI. Contrat mocké conforme au backend BC-25 (RESTO-501..506) : niveaux de
 * stock, bons de commande (draft → send → receive) et réceptions.
 *
 * Parcours couvert :
 *   niveaux de stock (onglet par défaut) → onglet bons de commande →
 *   transition draft → sent → received.
 */

const baseUser = {
  id: 202,
  first_name: 'Manager',
  last_name: 'Stock',
  email: 'stock@resto.test.cm',
  role: 'manager',
  manager_role: 'manager',
  language: 'fr',
  is_rtl: false,
  capabilities: {
    can_view_dashboard: true,
    restaurant: true,
    'restaurant.stock': true,
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

const stockLevels = {
  data: [
    { id: 1, product_code: 'P-01', product_name: 'Poulet braisé', quantity: 24, min_level: 10, unit: 'kg' },
    { id: 2, product_code: 'P-02', product_name: 'Jus de bissap', quantity: 3, min_level: 10, unit: 'L' },
  ],
};

/** Mock d'état des bons de commande + réceptions. */
function purchaseOrderStateFactory() {
  const pos = [
    { id: 1, reference: 'PO-0001', supplier_name: 'Ferme de l’Ouest', supplier_id: 1, status: 'draft', total_minor: 15000, currency: 'XAF' },
  ];
  const receivings = [
    { id: 1, reference: 'RCV-0001', purchase_order_id: 1, received_at: '2026-08-28T10:00:00+00:00' },
  ];

  return {
    listPos() {
      return { data: pos, meta: { current_page: 1, last_page: 1, per_page: 100, total: pos.length } };
    },
    listReceivings() {
      return { data: receivings, meta: { current_page: 1, last_page: 1, per_page: 100, total: receivings.length } };
    },
    transition(id: number, action: string) {
      const row = pos.find((p) => p.id === id);
      if (!row) return { data: null };
      row.status = action === 'send' ? 'sent' : action === 'receive' ? 'received' : row.status;
      return { data: row };
    },
  };
}

  // Catch-all : tout appel restaurant non mocké répond 200 { data: [] } (aucun backend requis).
  await page.route('**/api/v1/restaurant/**', (route) =>
    route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: [] }) }),
  );

async function mockStock(page: Page) {
  const state = purchaseOrderStateFactory();

  await page.route(/\/api\/v1\/restaurant\/stock-levels\?per_page=200$/, (route) =>
    route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(stockLevels) }),
  );
  await page.route(/\/api\/v1\/restaurant\/purchase-orders\?per_page=100$/, (route) =>
    route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(state.listPos()) }),
  );
  await page.route(/\/api\/v1\/restaurant\/receivings\?per_page=100$/, (route) =>
    route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(state.listReceivings()) }),
  );
  await page.route(/\/api\/v1\/restaurant\/purchase-orders\/\d+\/(send|receive)$/, async (route) => {
    const match = route.request().url().match(/\/purchase-orders\/(\d+)\/([a-z]+)$/);
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

test.describe('Stock & achats restaurant (RESTO-902)', () => {
  test('affiche les niveaux de stock et les bons de commande', async ({ page }) => {
    await setSessionCookie(page);
    await mockStock(page);
    await page.goto('/restaurant/stock');

    // Niveaux de stock (onglet par défaut)
    await expect(page.getByText('Poulet braisé')).toBeVisible();
    await expect(page.getByText('Jus de bissap')).toBeVisible();

    // Onglet bons de commande
    await page.getByRole('button', { name: /Bons de commande/ }).click();
    await expect(page.getByText('Ferme de l’Ouest')).toBeVisible();

    // Bon draft → Envoyer → sent
    await page.getByRole('button', { name: /Envoyer/ }).click();
    await expect(page.getByText('PO-0001').locator('..')).toContainText(/sent/);
  });

  test('réceptionne un bon de commande envoyé', async ({ page }) => {
    await setSessionCookie(page);
    await mockStock(page);
    await page.goto('/restaurant/stock');

    await page.getByRole('button', { name: /Bons de commande/ }).click();
    await page.getByRole('button', { name: /Envoyer/ }).click();
    await page.getByRole('button', { name: /Réceptionner/ }).click();
    await expect(page.getByText('PO-0001').locator('..')).toContainText(/received/);
  });
});
