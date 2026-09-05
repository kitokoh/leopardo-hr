import { expect, test } from '@playwright/test';
import { installAuthenticatedSession, type AuthenticatedUser } from './fixtures/authenticated';

/**
 * TRAVEL-1008 (#6121) — E2E Playwright admin TravelAgency.
 *
 * Squelette des parcours admin, À ACTIVER quand l'UI admin TravelAgency
 * (lot frontend TRAVEL-601..609) est livrée dans le portail manager.
 * Les contrats API sous-jacents sont stables et couverts par les tests
 * Feature Travel — ces specs valideront la couche UI une fois livrée.
 *
 * Note consolidation 2026-09-04 : l'UI admin travel n'existe pas encore dans
 * ce front (navigation « Agence de voyage » absente du portail manager),
 * les parcours sont donc marqués `test.skip` avec la fixture de session
 * canonique (`installAuthenticatedSession`).
 */

const managerUser: AuthenticatedUser = {
  id: 201,
  first_name: 'Camille',
  last_name: 'Toure',
  email: 'camille.toure@agence-pilote.cm',
  role: 'manager',
  manager_role: 'principal',
  language: 'fr',
  is_rtl: false,
  capabilities: { can_view_travel: true },
  company: {
    id: 'company-travel-pilot',
    name: 'Agence Pilote SARL',
    language: 'fr',
    timezone: 'Africa/Douala',
    currency: 'XAF',
    metadata: { onboarding_completed: true },
  },
};

test.describe('TravelAgency admin (TRAVEL-1008)', () => {
  test.skip('navigation travel is gated by feature flag', async ({ authenticatedPage: page }) => {
    await installAuthenticatedSession(page, { user: managerUser });
    await page.goto('/');
    await expect(page.getByRole('link', { name: /agence de voyage/i })).toBeVisible();
  });

  test.skip('referential screen lists published trips', async ({ authenticatedPage: page }) => {
    await installAuthenticatedSession(page, { user: managerUser });
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
