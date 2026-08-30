import { expect, test } from '@playwright/test';
import type { Page } from '@playwright/test';

/**
 * RESTO-902 (#6231) — E2E du kiosque libre-service (RESTO-807/#6228).
 *
 * Parcours public complet : menu → panier → commande → paiement espèces,
 * sans aucune authentification utilisateur (jeton de boutique `?token=`).
 * Les endpoints publics sont mockés (pattern route interception, cf.
 * e2e/manager-workday-smoke.spec.ts).
 */

const MENU_FIXTURE = {
  data: [
    {
      id: 1,
      name: 'Plats',
      sort_order: 1,
      products: [
        { id: 101, code: 'BURGER-XL', name: 'Burger XL', description: 'Double steak', price_minor: 3500, currency: 'XAF', image_asset_id: null },
        { id: 102, code: 'SALADE', name: 'Salade César', description: null, price_minor: 2500, currency: 'XAF', image_asset_id: null },
      ],
    },
  ],
};

const BRANCHES_FIXTURE = {
  data: [{ id: 1, code: 'MAIN', name: 'Branche Centrale' }],
};

async function mockPublicApi(page: Page) {
  await page.route('**/api/v1/public/restaurant/menu', async (route) => {
    await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(MENU_FIXTURE) });
  });
  await page.route('**/api/v1/public/restaurant/branches', async (route) => {
    await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(BRANCHES_FIXTURE) });
  });
}

test.describe('Kiosque libre-service (RESTO-807)', () => {
  test('affiche une erreur explicite sans jeton', async ({ page }) => {
    await page.goto('/kiosk');

    await expect(page.getByText('Jeton de boutique invalide ou absent.')).toBeVisible();
  });

  test('flux complet : menu → panier → commande → paiement espèces', async ({ page }) => {
    await mockPublicApi(page);

    await page.route('**/api/v1/public/restaurant/orders', async (route) => {
      if (route.request().method() !== 'POST') {
        await route.continue();
        return;
      }
      await route.fulfill({
        status: 201,
        contentType: 'application/json',
        body: JSON.stringify({
          data: {
            reference: 'RST-KIOSK-E2E',
            status: 'draft',
            total_minor: 6000,
            currency: 'XAF',
            subtotal_minor: 6000,
            tax_minor: 0,
          },
        }),
      });
    });

    await page.route('**/api/v1/public/restaurant/orders/*/pay', async (route) => {
      await route.fulfill({
        status: 201,
        contentType: 'application/json',
        body: JSON.stringify({ data: { id: 1, status: 'confirmed', provider_code: 'cash' } }),
      });
    });

    await page.goto('/kiosk?token=rshop_e2e');

    // Menu public affiché.
    await expect(page.getByText('Burger XL')).toBeVisible();
    await expect(page.getByText('Salade César')).toBeVisible();

    // Panier : 2 × Burger XL.
    await page.getByLabel('Ajouter').first().click();
    await page.getByLabel('Ajouter').first().click();
    await expect(page.getByText('70.00 XAF')).toBeVisible();

    // Commande puis paiement espèces.
    await page.getByText('Valider la commande').click();
    await expect(page.getByText('Commande envoyée en cuisine !')).toBeVisible();
    await expect(page.getByText('RST-KIOSK-E2E')).toBeVisible();

    await page.getByText('Payer en espèces').click();
    await expect(page.getByText('Paiement confirmé. Bon appétit !')).toBeVisible();
  });
});
