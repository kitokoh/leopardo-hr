import { expect, test } from '@playwright/test';
import type { Page } from '@playwright/test';

/**
 * RESTO-805-front (#6404) — E2E de la commande en ligne publique.
 *
 * Flux public complet : menu → panier → commande → suivi → paiement
 * (cash à l'encaissement), sans authentification utilisateur (jeton
 * boutique `?token=`). Endpoints shop mockés (route interception).
 */

const MENU = {
  data: {
    categories: [{ id: 1, name: 'Plats' }],
    products: [
      {
        id: 101,
        code: 'BURGER-XL',
        name: 'Burger XL',
        description: 'Double steak',
        price_minor: 3500,
        currency: 'XAF',
        category_id: 1,
        available: true,
      },
      {
        id: 102,
        code: 'SALADE',
        name: 'Salade César',
        description: null,
        price_minor: 2500,
        currency: 'XAF',
        category_id: 1,
        available: true,
      },
    ],
    pagination: { per_page: 50, total: 2 },
  },
};

test.describe('Commande en ligne publique (RESTO-805-front)', () => {
  test('affiche une erreur explicite sans jeton', async ({ page }) => {
    await page.goto('/order');

    await expect(page.getByText('Jeton de boutique invalide ou absent.')).toBeVisible();
  });

  test('flux complet : menu → panier → commande → suivi → paiement', async ({ page }) => {
    await mockShopApi(page);

    await page.goto('/order?token=rshop_e2e');

    await expect(page.getByText('Burger XL')).toBeVisible();
    await expect(page.getByText('Salade César')).toBeVisible();

    await page.getByLabel('Ajouter').first().click();
    await page.getByLabel('Ajouter').first().click();
    await expect(page.getByText('70.00 XAF')).toBeVisible();

    await page.getByText('Valider la commande').click();
    await expect(page.getByText('Commande enregistrée !')).toBeVisible();
    await expect(page.getByText('RST-SHOP-E2E')).toBeVisible();

    await page.getByText('Suivre ma commande').click();
    await expect(page.getByText('Suivi de commande')).toBeVisible();
    await expect(page.getByText('open')).toBeVisible();

    await page.getByText('Retour au menu').click();
    await expect(page.getByText('Commander en ligne')).toBeVisible();
  });
});

async function mockShopApi(page: Page) {
  await page.route('**/api/v1/public/restaurant/shop/menu', async (route) => {
    await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(MENU) });
  });
  await page.route('**/api/v1/public/restaurant/shop/orders', async (route) => {
    if (route.request().method() !== 'POST') {
      await route.continue();
      return;
    }
    await route.fulfill({
      status: 201,
      contentType: 'application/json',
      body: JSON.stringify({
        data: {
          reference: 'RST-SHOP-E2E',
          status: 'draft',
          total_minor: 7000,
          currency: 'XAF',
          created: true,
          track_url: '/api/v1/public/restaurant/shop/orders/RST-SHOP-E2E',
        },
      }),
    });
  });
  await page.route('**/api/v1/public/restaurant/shop/orders/RST-SHOP-E2E', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        data: {
          reference: 'RST-SHOP-E2E',
          status: 'open',
          subtotal_minor: 7000,
          tax_minor: 0,
          total_minor: 7000,
          currency: 'XAF',
          items: [{ product_code: 'BURGER-XL', name: 'Burger XL', quantity: 2, line_total_minor: 7000 }],
          updated_at: '2026-08-30T10:00:00Z',
        },
      }),
    });
  });
}
