import { expect } from '@playwright/test';
import type { Page } from '@playwright/test';
import { installAuthenticatedSession, test, type AuthenticatedUser } from './fixtures/authenticated';

/**
 * RESTO-902 (#6231) — E2E de l'écran cuisine (RESTO-707/#6220).
 *
 * File cuisine : chargement des commandes en préparation/prêtes, transition
 * start/ready. Session mockée (fixture authenticated) + endpoints restaurant
 * mockés (route interception).
 */

const BRANCHES = { data: [{ id: 1, code: 'MAIN', name: 'Branche Centrale' }] };

const KITCHEN_ORDERS = {
  data: [
    {
      id: 11,
      reference: 'RST-ABC',
      branch_id: 1,
      table_id: 3,
      order_type: 'dine_in',
      status: 'in_preparation',
      covers: 4,
      created_at: '2026-08-30T10:00:00Z',
      items: [{ id: 1, product_id: 101, name: 'Burger XL', quantity: 2, line_index: 1, status: 'active' }],
    },
    {
      id: 12,
      reference: 'RST-DEF',
      branch_id: 1,
      table_id: null,
      order_type: 'takeaway',
      status: 'ready',
      covers: null,
      created_at: '2026-08-30T10:05:00Z',
      items: [{ id: 2, product_id: 102, name: 'Salade César', quantity: 1, line_index: 1, status: 'active' }],
    },
  ],
};

// Utilisateur manager avec le flag restaurantmanager actif (RESTO-902) :
// sans feature/capability restaurant, le portail masque le module
// (« Restaurant Module non inclus ») et l'écran cuisine ne se monte pas.
const kitchenUser: AuthenticatedUser = {
  id: 101,
  first_name: 'Fatima',
  last_name: 'Meziane',
  email: 'fatima.meziane@techcorp-algerie.dz',
  role: 'manager',
  manager_role: 'principal',
  language: 'fr',
  is_rtl: false,
  capabilities: { can_view_dashboard: true, restaurant: true, 'restaurant.kitchen': true },
  features: { restaurantmanager: true },
  company: { id: 'company-resto-e2e', name: 'Restaurant E2E', features: { restaurantmanager: true } },
  plan: { features: { restaurantmanager: true } },
};

async function mockKitchenApi(page: Page) {
  // Session requise depuis que /restaurant/* est protégé par le middleware
  // (PROTECTED_PREFIXES #3377, split /restaurant → /restaurateur) : la page
  // /restaurant/kitchen est redirigée vers /auth/login sans cookie valide.
  await installAuthenticatedSession(page, { user: kitchenUser });
  await page.route('**/api/v1/restaurant/branches**', async (route) => {
    await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(BRANCHES) });
  });
  await page.route('**/api/v1/restaurant/kitchen/orders**', async (route) => {
    await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(KITCHEN_ORDERS) });
  });
  await page.route('**/api/v1/restaurant/kitchen/orders/*/start', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ data: { ...KITCHEN_ORDERS.data[0], status: 'in_preparation' } }),
    });
  });
  await page.route('**/api/v1/restaurant/kitchen/orders/*/ready', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ data: { ...KITCHEN_ORDERS.data[0], status: 'ready' } }),
    });
  });
}

test.describe('Écran cuisine (RESTO-707)', () => {
  test('affiche la file cuisine et ses commandes', async ({ page }) => {
    await mockKitchenApi(page);

    await page.goto('/restaurant/kitchen');

    await expect(page.getByText('Écran cuisine')).toBeVisible();
    await expect(page.getByText('RST-ABC')).toBeVisible();
    await expect(page.getByText('RST-DEF')).toBeVisible();
    await expect(page.getByText('Burger XL')).toBeVisible();
    await expect(page.getByText('Salade César')).toBeVisible();
  });
});
