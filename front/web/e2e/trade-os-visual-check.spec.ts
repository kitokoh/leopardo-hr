import { test, expect } from './fixtures/authenticated';
import { installAuthenticatedSession } from './fixtures/authenticated';

/**
 * Vérification VISUELLE des onglets vendeur B2B de `/trade-os` (#9779).
 *
 * Ce spec ne teste pas la logique métier (couverte par `Trade B2B CI`) : il
 * rend la page dans un vrai Chromium et capture les deux onglets livrés pour
 * qu'un humain puisse constater le rendu réel.
 *
 * La session est celle du fixture `authenticated` (cookie + `/auth/me` mockés)
 * : sans elle, la page redirige vers /auth/login et les onglets n'existent
 * jamais dans le DOM — c'est exactement ce qui s'est produit au premier essai.
 */

const ORDERS = [
  {
    id: 1,
    reference: 'CMD-2026-0001',
    direction: 'sale',
    status: 'confirmed',
    currency: 'EUR',
    incoterm: 'FOB',
    total_minor: 1250000,
  },
  {
    id: 2,
    reference: 'CMD-2026-0002',
    direction: 'purchase',
    status: 'shipped',
    currency: 'EUR',
    incoterm: 'CIF',
    total_minor: 480000,
  },
];

const COUNTERPARTIES = [
  {
    id: 1,
    name: 'Atlas Import SARL',
    country: 'DZ',
    roles: ['buyer'],
    verification_status: 'verified',
  },
  {
    id: 2,
    name: 'Bosphorus Textile A.S.',
    country: 'TR',
    roles: ['supplier', 'carrier'],
    verification_status: 'pending',
  },
];

const emptyList = { data: [] };

test.describe('trade-os — onglets vendeur B2B', () => {
  // La page monte sept sources API et un shell de dashboard : 30 s (défaut)
  // suffisent à l'assertion mais pas à la capture. On borne explicitement.
  test.setTimeout(90_000);

  test.beforeEach(async ({ page }) => {
    // Les trois outils vendeur de la mission, mockés indépendamment.
    await page.route('**/api/v1/trade/orders**', (route) =>
      route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: ORDERS }) }),
    );
    await page.route('**/api/v1/trade/counterparties**', (route) =>
      route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ data: COUNTERPARTIES }),
      }),
    );
    await page.route('**/api/v1/trade/products**', (route) =>
      route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(emptyList) }),
    );
    await page.route('**/api/v1/trade/rfqs**', (route) =>
      route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(emptyList) }),
    );
    await page.route('**/api/v1/trade/quotes**', (route) =>
      route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(emptyList) }),
    );
    await page.route('**/api/v1/trade/cost-estimates**', (route) =>
      route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(emptyList) }),
    );
    await page.route('**/api/v1/trade/meta/integrations**', (route) =>
      route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ data: { integrations: [], degraded: false } }),
      }),
    );

    // Session complète (cookie `leopardo_token`, /auth/me, branding, etc.).
    await installAuthenticatedSession(page, {
      user: {
        company: {
          id: 'company-trade',
          name: 'Atlas Trading SARL',
          features: { trade: true },
        },
      },
    });
  });

  test('onglet Commandes — rendu réel', async ({ page }) => {
    await page.goto('/trade-os', { waitUntil: 'domcontentloaded' });

    // `role="tablist"` + `role="tab"` sont posés explicitement par la page.
    const ordersTab = page.getByRole('tab', { name: /^(commandes|orders)$/i });
    await expect(ordersTab).toBeVisible({ timeout: 30_000 });
    await ordersTab.click();

    // La référence de commande doit apparaître : preuve que le tableau est
    // rendu avec des données, pas un état vide.
    await expect(page.getByText('CMD-2026-0001')).toBeVisible({ timeout: 15_000 });

    await page.screenshot({
      path: 'test-results/trade-os-orders.png',
      fullPage: false,
      animations: 'disabled',
      timeout: 20_000,
    });
  });

  test('onglet Contreparties — rendu réel', async ({ page }) => {
    await page.goto('/trade-os', { waitUntil: 'domcontentloaded' });

    const counterpartiesTab = page.getByRole('tab', { name: /^(contreparties|counterparties)$/i });
    await expect(counterpartiesTab).toBeVisible({ timeout: 30_000 });
    await counterpartiesTab.click();

    await expect(page.getByText('Atlas Import SARL')).toBeVisible({ timeout: 15_000 });

    await page.screenshot({
      path: 'test-results/trade-os-counterparties.png',
      fullPage: false,
      animations: 'disabled',
      timeout: 20_000,
    });
  });
});
