import { expect, installAuthenticatedSession, test, type AuthenticatedUser } from './fixtures/authenticated';

// RESTO-902 (#6231) — E2E Playwright : navigation « Restaurant » par flag
// + écran cuisine (déjà mergé, RESTO-707/#6336). Les parcours POS et
// réservation (écrans non encore mergés) vivent dans
// restaurant-pos.spec.ts / restaurant-reservation.spec.ts.

const restaurantUser: AuthenticatedUser = {
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
    'restaurant.kitchen': true,
  },
  features: {
    restaurantmanager: true,
  },
  company: {
    id: 'company-resto-e2e',
    name: 'Restaurant E2E',
    features: { restaurantmanager: true },
  },
  plan: { features: { restaurantmanager: true } },
};

test.describe('Restaurant — navigation par flag (RESTO-902)', () => {
  test('le menu Restaurant est visible quand le flag restaurantmanager est actif', async ({ authenticatedPage: page }) => {
    await installAuthenticatedSession(page, { user: restaurantUser });
    await page.goto('/dashboard');

    // Deux entrées cohabitent quand le module est actif : le hub Restaurant
    // (/restaurant) et l'écran cuisine (/restaurant/kitchen).
    await expect(page.getByRole('link', { name: /Restaurant/i }).first()).toBeVisible();
    await expect(page.locator('a[href="/restaurant/kitchen"]')).toBeVisible();
  });

  test('le menu Restaurant est masqué sans le flag', async ({ authenticatedPage: page }) => {
    const noFeatureUser: AuthenticatedUser = {
      ...restaurantUser,
      capabilities: { can_view_dashboard: true },
      features: {},
      plan: { features: {} },
    };
    await installAuthenticatedSession(page, { user: noFeatureUser });
    await page.goto('/dashboard');

    await expect(page.getByRole('link', { name: /Restaurant/i })).toHaveCount(0);
  });

  test('l’écran cuisine est accessible depuis la navigation', async ({ authenticatedPage: page }) => {
    await page.route('**/api/v1/restaurant/kitchen/orders**', (route) =>
      route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: [] }) }),
    );
    await page.route('**/api/v1/restaurant/branches**', (route) =>
      route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: [] }) }),
    );
    await installAuthenticatedSession(page, { user: restaurantUser });
    await page.goto('/dashboard');

    // Navigation directe vers l'écran cuisine depuis le menu latéral.
    await page.locator('a[href="/restaurant/kitchen"]').click();
    await expect(page).toHaveURL(/\/restaurant\/kitchen/);
    await expect(page.getByRole('heading', { name: /En préparation/i })).toBeVisible();
  });
});
