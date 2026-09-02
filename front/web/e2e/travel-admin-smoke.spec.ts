import { expect, test } from '@playwright/test';
import { setSessionCookie } from './session-helpers';

/**
 * TRAVEL-1008 (#6121) — E2E Playwright admin TravelAgency.
 *
 * Squelette des parcours admin (à activer quand l'UI admin TravelAgency
 * est livrée — lot frontend TRAVEL-601..609) :
 *   - navigation « Agence de voyage » (gate feature flag) ;
 *   - écran référentiel (liste des trajets, création) ;
 *   - écran réservations (détail, actions).
 *
 * Les contrats API sous-jacents sont stables et couverts par les tests
 * Feature Travel (302 tests verts) — ces specs valideront la couche UI.
 */

const managerUser = {
  id: 201,
  first_name: 'Camille',
  last_name: 'Toure',
  email: 'camille.toure@agence-pilote.cm',
  role: 'manager',
  manager_role: 'principal',
  language: 'fr',
  is_rtl: false,
  capabilities: { can_view_travel: true },
  company: { id: 'company-travel-pilot', name: 'Agence Pilote SARL', language: 'fr', timezone: 'Africa/Douala', currency: 'XAF' },
};

test.describe('TravelAgency admin (TRAVEL-1008)', () => {
  test('navigation travel is gated by feature flag', async ({ page }) => {
    await setSessionCookie(page, managerUser, { features: { travelagency: true } });
    await page.goto('/');
    await expect(page.getByRole('link', { name: /agence de voyage/i })).toBeVisible();
  });

  test('referential screen lists published trips', async ({ page }) => {
    await setSessionCookie(page, managerUser, { features: { travelagency: true } });
    // Intercepte le contrat API référentiel (stable, testé côté Feature).
    await page.route('**/api/v1/travel/trips**', (route) =>
      route.fulfill({
        json: {
          data: [
            { id: 1, code: 'DLA-YDE-001', status: 'published', total_seats: 40 },
          ],
        },
      }),
    );
    await page.goto('/travel/trips');
    await expect(page.getByText('DLA-YDE-001')).toBeVisible();
  });
});
