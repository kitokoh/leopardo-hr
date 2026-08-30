import { expect, test, type Page } from '@playwright/test';
import { setSessionCookie } from './session-helpers';

/**
 * RESTO-902 (#6231) — E2E Playwright : écran cuisine.
 *
 * File de commandes par branche : colonnes « en préparation » / « prêtes »,
 * transitions start/ready. API MOCKÉE (contrats RESTO-410) — aucun backend
 * requis.
 */

const kitchenUser = {
  id: 201,
  first_name: 'Moussa',
  last_name: 'Cuisinier',
  email: 'moussa.cuisine@demo.dz',
  role: 'manager',
  manager_role: 'kitchen',
  language: 'fr',
  is_rtl: false,
  capabilities: { restaurant: true, 'restaurant.kitchen': true },
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

const queue = {
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
      items: [{ id: 1, product_id: 101, name: 'Poulet braisé', quantity: 2, line_index: 1, status: 'active' }],
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
      items: [{ id: 2, product_id: 102, name: 'Salade', quantity: 1, line_index: 1, status: 'active' }],
    },
  ],
};

test.describe('E2E écran cuisine (RESTO-902)', () => {
  test('affiche la file et marque une commande prête', async ({ page }) => {
    await page.addInitScript((user) => {
      window.localStorage.setItem('auth_token', 'resto-kitchen-token');
      window.localStorage.setItem('auth_user', JSON.stringify(user));
    }, kitchenUser);
    await setSessionCookie(page);

    await page.route('**/api/v1/notifications**', (route) =>
      route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: [], meta: { unread_count: 0 } }) }),
    );
    await page.route('**/api/v1/restaurant/branches**', (route) =>
      route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(branches) }),
    );
    await page.route('**/api/v1/restaurant/kitchen/orders**', (route) =>
      route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(queue) }),
    );
    await page.route('**/api/v1/restaurant/kitchen/orders/11/ready', (route) =>
      route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: { id: 11, status: 'ready' } }) }),
    );

    await page.goto('/restaurant/kitchen', { waitUntil: 'domcontentloaded' });

    await expect(page.getByText('RST-ABC')).toBeVisible();
    await expect(page.getByText('RST-DEF')).toBeVisible();
    await expect(page.getByText(/2 × Poulet braisé/)).toBeVisible();

    // La commande prête (RST-DEF) n'a pas de bouton d'action cuisine.
    await expect(page.getByRole('button', { name: /Prête/i })).toHaveCount(1);

    await page.getByRole('button', { name: /Prête/i }).click();
    await expect(page.getByRole('button', { name: /Prête/i })).toBeVisible();
  });
});
