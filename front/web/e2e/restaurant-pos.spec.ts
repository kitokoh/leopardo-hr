import { expect, test, type Page } from '@playwright/test';
import { setSessionCookie } from './session-helpers';

/**
 * RESTO-902 (#6231) — E2E Playwright : prise de commande & encaissement (POS).
 *
 * Parcours (spec §4.1) : ouverture de caisse → nouvelle commande → ajout
 * d'articles → soumission → confirmation cuisine → addition → encaissement
 * espèces. L'API restaurant est MOCKÉE (pattern client-business-flows) :
 * le spec tourne sans backend, les fixtures reflètent les contrats
 * RESTO-401..407 (montants serveur minor units).
 */

const baseUser = {
  id: 101,
  first_name: 'Amine',
  last_name: 'Chef de salle',
  email: 'amine.resto@demo.dz',
  role: 'manager',
  manager_role: 'principal',
  language: 'fr',
  is_rtl: false,
  capabilities: { restaurant: true, 'restaurant.server': true, 'restaurant.manage': true },
  company: {
    id: 'company-1',
    name: 'Restaurant Démo',
    language: 'fr',
    timezone: 'Africa/Algiers',
    currency: 'XAF',
    features: { restaurantmanager: true },
    metadata: { onboarding_completed: true },
  },
};

const branches = { data: [{ id: 1, code: 'BR-001', name: 'Branche Centrale' }] };
const session = { data: { id: 9, branch_id: 1, status: 'open', opening_cash_minor: 0 } };
const categories = { data: [{ id: 1, name: 'Plats' }] };
const products = {
  data: [
    { id: 101, code: 'PRD-1', name: 'Poulet braisé', price_minor: 2500, currency: 'XAF', category_id: 1, is_available: true },
    { id: 102, code: 'PRD-2', name: 'Salade', price_minor: 1000, currency: 'XAF', category_id: 1, is_available: true },
  ],
};
const bill = { data: { subtotal_minor: 2500, tax_minor: 0, discount_minor: 0, total_minor: 2500, currency: 'XAF' } };

async function mockCommon(page: Page) {
  await page.route('**/api/v1/notifications**', (route) =>
    route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: [], meta: { unread_count: 0 } }) }),
  );
  await page.addInitScript((user) => {
    window.localStorage.setItem('auth_token', 'resto-pos-token');
    window.localStorage.setItem('auth_user', JSON.stringify(user));
  }, baseUser);
  await setSessionCookie(page);
}

async function mockRestaurantApis(page: Page) {
  await page.route('**/api/v1/restaurant/branches**', (route) =>
    route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(branches) }),
  );
  await page.route('**/api/v1/restaurant/categories**', (route) =>
    route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(categories) }),
  );
  await page.route('**/api/v1/restaurant/products**', (route) =>
    route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(products) }),
  );
  await page.route('**/api/v1/restaurant/pos-sessions/current**', (route) =>
    route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(session) }),
  );

  let orderStatus = 'draft';
  let orderItems: unknown[] = [];

  await page.route('**/api/v1/restaurant/orders**', (route) => {
    const request = route.request();
    const isPost = request.method() === 'POST';
    const url = new URL(request.url());
    const segments = url.pathname.split('/').filter(Boolean);

    if (isPost && segments.length === 4 && segments[3] === 'orders') {
      orderStatus = 'draft';
      orderItems = [];
      return route.fulfill({
        status: 201,
        contentType: 'application/json',
        body: JSON.stringify({ data: { id: 11, reference: 'RST-ABC', status: 'draft', items: [] } }),
      });
    }

    const last = segments[segments.length - 1];
    if (isPost && last === 'items') {
      orderItems = [{ id: 1, product_id: 101, name: 'Poulet braisé', quantity: 1, line_total_minor: 2500, status: 'active' }];
      return route.fulfill({ status: 201, contentType: 'application/json', body: JSON.stringify({ data: { id: 1 } }) });
    }
    if (isPost && last === 'submit') {
      orderStatus = 'open';
      return route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: { id: 11, reference: 'RST-ABC', status: 'open', items: orderItems } }) });
    }
    if (isPost && last === 'confirm') {
      orderStatus = 'in_preparation';
      return route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: { id: 11, reference: 'RST-ABC', status: 'in_preparation', items: orderItems } }) });
    }
    if (isPost && last === 'pay') {
      orderStatus = 'paid';
      return route.fulfill({ status: 201, contentType: 'application/json', body: JSON.stringify({ data: { id: 1, status: 'confirmed' } }) });
    }
    if (last === 'bill') {
      return route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(bill) });
    }

    return route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ data: { id: 11, reference: 'RST-ABC', status: orderStatus, items: orderItems } }),
    });
  });
}

test.describe('E2E POS — prise de commande & encaissement (RESTO-902)', () => {
  test('flux complet : caisse → commande → article → cuisine → addition → encaissement', async ({ page }) => {
    await mockCommon(page);
    await mockRestaurantApis(page);

    await page.goto('/restaurant/pos', { waitUntil: 'domcontentloaded' });

    await expect(page.getByText('Poulet braisé').first()).toBeVisible();

    await page.getByRole('button', { name: /Nouvelle commande/i }).click();
    await expect(page.getByText('RST-ABC').first()).toBeVisible();

    await page.getByRole('button', { name: /Poulet braisé/i }).first().click();
    await expect(page.getByText('RST-ABC').first()).toBeVisible();

    await page.getByRole('button', { name: /Soumettre/i }).click();
    await page.getByRole('button', { name: /Confirmer/i }).click();

    await page.getByRole('button', { name: /Addition/i }).click();
    await expect(page.getByText('25.00', { exact: true }).last()).toBeVisible();
    await page.getByRole('button', { name: /Encaisser espèces/i }).click();

    await expect(page.getByText(/Commande payée/i)).toBeVisible();
  });
});
