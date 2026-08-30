import { expect, test } from '@playwright/test';
import type { Page } from '@playwright/test';

/**
 * RESTO-807-front (#6405) — E2E du kiosque libre-service.
 *
 * Flux public complet : menu → panier → commande → ticket affiché, sans
 * aucune authentification utilisateur (jeton boutique `?token=`). Endpoints
 * kiosque mockés (route interception).
 */

const MENU = {
  data: {
    products: [
      { id: 101, code: 'BURGER-XL', name: 'Burger XL', price_minor: 3500, currency: 'XAF', category_id: 1 },
      { id: 102, code: 'SALADE', name: 'Salade César', price_minor: 2500, currency: 'XAF', category_id: 1 },
    ],
    pagination: { per_page: 50, total: 2 },
  },
};

test.describe('Kiosque libre-service (RESTO-807-front)', () => {
  test('affiche une erreur explicite sans jeton', async ({ page }) => {
    await page.goto('/kiosk');

    await expect(page.getByText('Jeton de boutique invalide ou absent.')).toBeVisible();
  });

  test('flux complet : menu → panier → commande → ticket', async ({ page }) => {
    await mockKioskApi(page);

    await page.goto('/kiosk?token=rshop_e2e');

    await expect(page.getByText('Burger XL')).toBeVisible();
    await expect(page.getByText('Salade César')).toBeVisible();

    await page.getByLabel('Ajouter').first().click();
    await page.getByLabel('Ajouter').first().click();
    await expect(page.getByText('70.00 XAF')).toBeVisible();

    await page.getByText('Valider la commande').click();

    await expect(page.getByText('Commande envoyée en cuisine !')).toBeVisible();
    await expect(page.getByText('42')).toBeVisible();
    await expect(page.getByText(/Réglez à l'encaissement/)).toBeVisible();
  });
});

async function mockKioskApi(page: Page) {
  await page.route('**/api/v1/public/restaurant/kiosk/menu', async (route) => {
    await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(MENU) });
  });
  await page.route('**/api/v1/public/restaurant/kiosk/orders', async (route) => {
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
          ticket_number: '42',
          status: 'draft',
          total_minor: 7000,
          currency: 'XAF',
          created: true,
        },
      }),
    });
  });
}
