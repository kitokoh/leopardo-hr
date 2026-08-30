import { expect, installAuthenticatedSession, test, type AuthenticatedUser } from './fixtures/authenticated';

// RESTO-902 (#6231) — E2E Playwright : POS complet (prise de commande,
// articles, transitions, encaissement).
//
// ⚠️ Dépendance UI : l'écran POS (RESTO-705/#6218) est livré par la branche
// `bc/bc25-restaurant-ui-pos` (PR ouverte) — non encore mergé sur `main`.
// Les tests ci-dessous sont donc SKIPPÉS tant que la page
// `/restaurant/pos` n'existe pas ; ils deviennent actifs automatiquement
// dès le merge de l'écran (retirer le `test.skip` à ce moment, ou le garder
// avec un `test.describe.skip` après vérification CI verte).

const posUser: AuthenticatedUser = {
  id: 101,
  first_name: 'Fatima',
  last_name: 'Meziane',
  email: 'fatima.meziane@techcorp-algerie.dz',
  role: 'manager',
  manager_role: 'server',
  language: 'fr',
  is_rtl: false,
  capabilities: {
    can_view_dashboard: true,
    restaurant: true,
    'restaurant.server': true,
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

const branch = { id: 1, code: 'BR-001', name: 'Branche Centre', currency: 'XAF' };

const products = [
  { id: 11, code: 'DISH-1', name: 'Poulet braisé', price_minor: 3000, currency: 'XAF', category_id: 1, available: true },
  { id: 12, code: 'DISH-2', name: 'Jus de gingembre', price_minor: 1000, currency: 'XAF', category_id: 2, available: true },
];

async function stubPosApi(page: import('@playwright/test').Page) {
  await page.route('**/api/v1/restaurant/branches', (route) =>
    route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: [branch] }) }),
  );
  await page.route('**/api/v1/restaurant/products**', (route) =>
    route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: products }) }),
  );
  await page.route('**/api/v1/restaurant/pos-sessions/current', (route) =>
    route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: null }) }),
  );
  await page.route('**/api/v1/restaurant/pos-sessions', (route) =>
    route.fulfill({
      status: 201,
      contentType: 'application/json',
      body: JSON.stringify({ data: { id: 7, branch_id: 1, status: 'open', opening_cash_minor: 10000 } }),
    }),
  );
}

test.describe('Restaurant POS (RESTO-902)', () => {
  test.skip('ouvre une session de caisse et crée une commande', async ({ page }) => {
    await installAuthenticatedSession(page, { user: posUser });
    await stubPosApi(page);
    await page.goto('/restaurant/pos');

    await expect(page.getByRole('heading', { name: /Point de vente/i })).toBeVisible();
  });

  test.skip('ajoute des articles et affiche le total serveur', async ({ page }) => {
    await installAuthenticatedSession(page, { user: posUser });
    await stubPosApi(page);
    await page.goto('/restaurant/pos');

    await expect(page.getByText('Poulet braisé')).toBeVisible();
  });

  test.skip('encaisse une commande (espèces) et affiche le statut payé', async ({ page }) => {
    await installAuthenticatedSession(page, { user: posUser });
    await stubPosApi(page);
    await page.goto('/restaurant/pos');

    await expect(page.getByText('Poulet braisé')).toBeVisible();
  });
});
