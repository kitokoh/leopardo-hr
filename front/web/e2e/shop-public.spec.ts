import { expect, test, type Page } from '@playwright/test';

/**
 * RESTO-805-front (#6404) — Parcours boutique publique (commande en ligne).
 *
 * Page publique /shop?token=… : menu → panier → commande idempotente →
 * suivi. Les endpoints /public/restaurant/shop/* sont mockés (le backend
 * RESTO-805 est livré sur bc/bc25-restaurant-mobile-public, PR #6390).
 * Vérifie aussi l'absence de fuite cross-tenant : le jeton envoyé en
 * en-tête est celui de l'URL.
 */

const SHOP_TOKEN = 'e2e-shop-token-'.padEnd(48, 'a');

async function mockShopApi(page: Page) {
  await page.route('**/api/v1/public/restaurant/shop/menu*', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        data: {
          categories: [{ id: 1, name: 'Plats' }, { id: 2, name: 'Boissons' }],
          products: [
            { id: 1, code: 'P-01', name: 'Poulet braisé', description: 'Demi poulet', price_minor: 3500, currency: 'XOF', category_id: 1, available: true },
            { id: 2, code: 'D-01', name: 'Jus de bissap', description: null, price_minor: 1000, currency: 'XOF', category_id: 2, available: true },
          ],
        },
      }),
    });
  });

  await page.route('**/api/v1/public/restaurant/shop/orders', async (route) => {
    const request = route.request();
    const tokenHeader = request.headers()['x-restaurant-shop-token'] ?? '';
    expect(tokenHeader).toBe(SHOP_TOKEN);
    await route.fulfill({
      status: 201,
      contentType: 'application/json',
      body: JSON.stringify({
        data: { reference: 'RST-E2E-1', status: 'open', total_minor: 4500, currency: 'XOF', created: true, track_url: '/api/v1/public/restaurant/shop/orders/RST-E2E-1' },
      }),
    });
  });

  await page.route('**/api/v1/public/restaurant/shop/orders/RST-E2E-1', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        data: {
          reference: 'RST-E2E-1',
          status: 'open',
          subtotal_minor: 4500,
          tax_minor: 0,
          total_minor: 4500,
          currency: 'XOF',
          items: [
            { product_code: 'P-01', name: 'Poulet braisé', quantity: 1, line_total_minor: 3500 },
            { product_code: 'D-01', name: 'Jus de bissap', quantity: 1, line_total_minor: 1000 },
          ],
        },
      }),
    });
  });
}

test('commande en ligne publique : menu → panier → commande → suivi', async ({ page }) => {
  await mockShopApi(page);
  await page.goto(`/shop?token=${SHOP_TOKEN}`);

  // Menu public chargé.
  await expect(page.getByRole('heading', { name: 'Commande en ligne' })).toBeVisible();
  await expect(page.getByText('Poulet braisé')).toBeVisible();
  await expect(page.getByText('Jus de bissap')).toBeVisible();

  // Filtre par catégorie.
  await page.getByRole('button', { name: 'Boissons' }).click();
  await expect(page.getByText('Poulet braisé')).toHaveCount(0);
  await expect(page.getByText('Jus de bissap')).toBeVisible();
  await page.getByRole('button', { name: 'Tout' }).click();

  // Panier : 2 articles puis commande.
  await page.getByRole('button', { name: /Ajouter Poulet braisé/ }).click();
  await page.getByRole('button', { name: /Ajouter Jus de bissap/ }).click();
  await page.getByRole('button', { name: /Panier \(2\)/ }).click();

  await expect(page.getByText('Poulet braisé')).toBeVisible();
  await page.getByLabel('Téléphone (optionnel)').fill('+22507000000');
  await page.getByRole('button', { name: /Commander/ }).click();

  // Confirmation + suivi.
  await expect(page.getByText('Commande confirmée')).toBeVisible();
  await expect(page.getByText('RST-E2E-1')).toBeVisible();
  await expect(page.getByText('Poulet braisé')).toBeVisible();

  // Aucune fuite cross-tenant : le jeton envoyé est celui de l'URL (assertion
  // dans le mock du POST orders).
});

test('boutique publique : lien sans jeton → état erreur', async ({ page }) => {
  await page.goto('/shop');
  await expect(page.getByText('Lien de boutique invalide ou manquant. Utilisez le lien fourni par le restaurant.')).toBeVisible();
});
