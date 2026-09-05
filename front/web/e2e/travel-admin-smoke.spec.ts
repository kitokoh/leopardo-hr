import { expect, test } from '@playwright/test';
import { installAuthenticatedSession, type AuthenticatedUser } from './fixtures/authenticated';

/**
 * TRAVEL-1008 (#6121) — E2E Playwright admin TravelAgency (squelette).
 *
 * L'UI admin TravelAgency (lot frontend TRAVEL-601..609 : navigation,
 * référentiel des trajets, réservations) n'est PAS encore livrée dans
 * front/web : le portail voyageur livré est `/travel/portal` (TRAVEL-702,
 * suivi e-billet côté passager). Ces parcours admin sont donc compilés
 * (garde tsc) mais exécutés en `skip` motivé, à activer quand l'UI admin
 * atterrit sur main (même pattern que les skeletons e2e historiques).
 * Contrats API stables couverts côté Feature (302 tests Travel).
 */

const travelUser: AuthenticatedUser = {
  id: 201,
  first_name: 'Camille',
  last_name: 'Toure',
  email: 'camille.toure@agence-pilote.cm',
  role: 'manager',
  manager_role: 'principal',
  language: 'fr',
  is_rtl: false,
  capabilities: { can_view_dashboard: true, can_view_travel: true },
  features: { travelagency: true },
  company: { id: 'company-travel-pilot', name: 'Agence Pilote SARL', language: 'fr', timezone: 'Africa/Douala', currency: 'XAF' },
};

test.describe('TravelAgency admin (TRAVEL-1008)', () => {
  test('navigation travel is gated by feature flag', async ({ page }) => {
    test.skip(true, 'UI admin TravelAgency non livrée (TRAVEL-601..609) — activer avec le lot frontend');
    await installAuthenticatedSession(page, { user: travelUser });
    await page.goto('/');
    await expect(page.getByRole('link', { name: /agence de voyage/i })).toBeVisible();
  });

  test('referential screen lists published trips', async ({ page }) => {
    test.skip(true, 'UI admin TravelAgency non livrée — route /travel/trips absente de front/web');
    await installAuthenticatedSession(page, { user: travelUser });
    // Intercepte le contrat API référentiel (stable, testé côté Feature).
    await page.route('**/api/v1/travel/trips**', (route) =>
      route.fulfill({
        json: {
          data: [{ id: 1, code: 'DLA-YDE-001', status: 'published', total_seats: 40 }],
        },
      }),
    );
    await page.goto('/travel/trips');
    await expect(page.getByText('DLA-YDE-001')).toBeVisible();
  });
});
