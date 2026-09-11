import { expect, test } from '@playwright/test';
import type { Page } from '@playwright/test';

// Pages PUBLIQUES (locale résolue via navigator, pas d'utilisateur) : force fr-FR.
test.use({ locale: 'fr-FR' });

/**
 * RESTO-902 (#6231) — E2E du kiosque libre-service (RESTO-807/#6228).
 *
 * Parcours public complet : menu → panier → commande → paiement espèces,
 * sans aucune authentification utilisateur (jeton de boutique `?token=`).
 * Les endpoints publics sont mockés (pattern route interception, cf.
 * e2e/manager-workday-smoke.spec.ts).
 */

// Contrat réel de `GET /public/restaurant/kiosk/menu` (RestaurantKioskController::menu) :
// liste plate de produits + pagination — voir api/tests/Feature/Restaurant/RestaurantKioskTest.php.
const MENU_FIXTURE = {
  data: {
    products: [
      { id: 101, code: 'BURGER-XL', name: 'Burger XL', price_minor: 3500, currency: 'XAF', category_id: 1 },
      { id: 102, code: 'SALADE', name: 'Salade César', price_minor: 2500, currency: 'XAF', category_id: 1 },
    ],
    pagination: { per_page: 50, total: 2 },
  },
};

async function mockPublicApi(page: Page) {
  await page.route('**/api/v1/public/restaurant/kiosk/menu', async (route) => {
    await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(MENU_FIXTURE) });
  });
}

test.describe('Kiosque libre-service (RESTO-807)', () => {
  test('affiche une erreur explicite sans jeton', async ({ page }) => {
    await page.goto('/kiosk');

    await expect(page.getByText('Jeton de boutique invalide ou absent.')).toBeVisible();
  });

  test('flux complet : menu → panier → commande → paiement espèces', async ({ page }) => {
    await mockPublicApi(page);

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
            status: 'open',
            total_minor: 7000,
            currency: 'XAF',
            created: true,
          },
        }),
      });
    });

    // Paiement public : même contrat que la boutique RESTO-805 (jeton boutique),
    // documenté dans docs/restaurant/KIOSK_ETUDE.md § « Paiement ».
    await page.route('**/api/v1/public/restaurant/shop/orders/*/pay', async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          data: { provider_code: 'cash', status: 'pending', instruction: 'pay_at_pickup', order_reference: 'RST-KIOSK-E2E' },
        }),
      });
    });

    await page.goto('/kiosk?token=rshop_e2e');

    // Menu public affiché.
    await expect(page.getByText('Burger XL')).toBeVisible();
    await expect(page.getByText('Salade César')).toBeVisible();

    // Panier : 2 × Burger XL.
    await page.getByLabel('Ajouter').first().click();
    await page.getByLabel('Ajouter').first().click();
    await expect(page.getByText('70.00 XAF').first()).toBeVisible();

    // Commande puis paiement espèces.
    await page.getByText('Valider la commande').click();
    await expect(page.getByText('Commande envoyée en cuisine !')).toBeVisible();
    await expect(page.getByText('RST-KIOSK-E2E')).toBeVisible();

    await page.getByText('Payer en espèces').click();
    await expect(page.getByText('Paiement confirmé. Bon appétit !')).toBeVisible();
  });
});
