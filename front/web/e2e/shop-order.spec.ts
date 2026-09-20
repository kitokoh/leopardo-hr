import { expect, test } from '@playwright/test';
import type { Page } from '@playwright/test';

// Pages PUBLIQUES (locale résolue via navigator, pas d'utilisateur) : force fr-FR.
test.use({ locale: 'fr-FR' });

/**
 * RESTO-805-front (#6404) — E2E de la commande en ligne publique.
 *
 * RESTO-903 (#7748) — `/order` a été dédupliqué : le PARCOURS de commande
 * (menu → panier → commande → suivi → paiement) vit désormais sur `/shop`
 * (jeton boutique `?token=`), `/order` étant réduit au seul suivi par
 * référence (deep-link `?ref=` conservé). Ce spec couvre les deux pages.
 * Endpoints shop mockés (route interception).
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
    pagination: { per_page: 100, total: 2 },
  },
};

const TRACK = {
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
};

test.describe('Commande en ligne publique (RESTO-805-front)', () => {
  test('affiche une erreur explicite sans jeton', async ({ page }) => {
    // #7748 — le parcours de commande vit sur /shop (le lien du gérant porte
    // le jeton) : sans jeton, la page l'explique sans écran cassé.
    await page.goto('/shop');

    await expect(
      page.getByText('Lien de boutique invalide ou manquant. Utilisez le lien fourni par le restaurant.'),
    ).toBeVisible();
  });

  test('flux complet : menu → panier → commande → suivi → paiement', async ({ page }) => {
    await mockShopApi(page);

    await page.goto('/shop?token=rshop_e2e');

    await expect(page.getByText('Commander en ligne')).toBeVisible();
    await expect(page.getByText('Burger XL')).toBeVisible();
    await expect(page.getByText('Salade César')).toBeVisible();

    // Ajout ×2 du burger (aria-label « Ajouter <produit> »), puis panier.
    await page.getByLabel('Ajouter Burger XL').click();
    await page.getByLabel('Ajouter Burger XL').click();
    await page.getByRole('button', { name: /^Panier/ }).click();

    const cart = page.getByRole('dialog', { name: 'Panier' });
    await expect(cart).toBeVisible();
    await expect(cart.getByText('Burger XL')).toBeVisible();
    await cart.getByRole('button', { name: /Valider la commande/ }).click();

    // Commande confirmée : référence affichée, suivi rafraîchi automatiquement.
    await expect(page.getByText('Commande confirmée')).toBeVisible();
    await expect(page.getByText('RST-SHOP-E2E')).toBeVisible();
    await expect(page.getByText(/Suivre ma commande — open/)).toBeVisible();

    // Paiement (cash à l'encaissement) : l'instruction du provider s'affiche.
    await page.getByRole('button', { name: 'Payer' }).click();
    await expect(page.getByText(/pay_at_pickup/)).toBeVisible();
  });

  test('suivi seul sur /order : deep-link ?ref= et erreur sans jeton', async ({ page }) => {
    await mockShopApi(page);

    // #7748 — /order est la page de suivi : le deep-link historique
    // `?token=&ref=` déclenche la recherche immédiate.
    await page.goto('/order?token=rshop_e2e&ref=RST-SHOP-E2E');
    await expect(page.getByText('Suivi de commande')).toBeVisible();
    await expect(page.getByText('Burger XL')).toBeVisible();
    await expect(page.getByText('open')).toBeVisible();

    // Sans jeton, la recherche d'une référence affiche l'erreur explicite.
    await page.goto('/order');
    await page.getByLabel('Référence de commande').fill('RST-SHOP-E2E');
    await page.getByRole('button', { name: 'Suivre ma commande' }).click();
    await expect(page.getByText('Jeton de boutique invalide ou absent.')).toBeVisible();
  });
});

async function mockShopApi(page: Page) {
  await page.route('**/api/v1/public/restaurant/shop/menu**', async (route) => {
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
      body: JSON.stringify(TRACK),
    });
  });
  await page.route('**/api/v1/public/restaurant/shop/orders/RST-SHOP-E2E/pay', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        data: { provider_code: 'cash', status: 'pending', instruction: 'pay_at_pickup' },
      }),
    });
  });
}
