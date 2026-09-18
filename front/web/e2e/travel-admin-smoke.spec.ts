import { expect, installAuthenticatedSession, test, type AuthenticatedUser } from './fixtures/authenticated';
import { type Page } from '@playwright/test';

/**
 * TRAVEL-1008 (#6121) — E2E Playwright admin TravelAgency.
 *
 * Dé-skippé avec le lot espace gérant (#7633..#7636) : hub /travel,
 * référentiel réseau /travel/network, voyages /travel/trips et
 * réservations/billetterie /travel/bookings sont livrés dans front/web.
 * Seul /travel/reports (#7637, A5) reste en `test.skip` documenté.
 *
 * Stratégie : mocks API (page.route) — aucun backend requis, déterministe en
 * CI (même pattern que restaurant-navigation.spec.ts). Les contrats mockés
 * reproduisent les Resources réelles du module TravelAgency
 * (TravelBookingResource, TravelTicketResource, TravelPassengerResource…).
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
  company: {
    id: 'company-travel-pilot',
    name: 'Agence Pilote SARL',
    language: 'fr',
    timezone: 'Africa/Douala',
    currency: 'XAF',
    features: { travelagency: true },
  },
};

function json(body: unknown) {
  return { status: 200, contentType: 'application/json', body: JSON.stringify(body) };
}

/**
 * Filet de sécurité : toute route travel non mockée explicitement répond une
 * collection vide (les pages chargent plusieurs référentiels en parallèle).
 * Playwright évalue la route la plus récemment enregistrée en premier : les
 * mocks spécifiques des tests, posés APRÈS, priment sur ce catch-all.
 */
async function installTravelApiFallback(page: Page): Promise<void> {
  await page.route('**/api/v1/travel/**', (route) => route.fulfill(json({ data: [] })));
}

test.describe('TravelAgency admin (TRAVEL-1008)', () => {
  test('navigation travel is gated by feature flag', async ({ page }) => {
    await installTravelApiFallback(page);
    await installAuthenticatedSession(page, { user: travelUser });
    await page.goto('/dashboard');
    // Hub gérant (/travel) + portail voyageur (/travel/portal) au menu.
    await expect(page.locator('a[href="/travel"]').first()).toBeVisible();
  });

  test('navigation travel is hidden without the feature flag', async ({ page }) => {
    const noFeatureUser: AuthenticatedUser = {
      ...travelUser,
      capabilities: { can_view_dashboard: true },
      features: {},
      company: { ...travelUser.company, features: {} },
    };
    await installAuthenticatedSession(page, { user: noFeatureUser });
    await page.goto('/dashboard');
    await expect(page.locator('a[href="/travel"]')).toHaveCount(0);
  });

  test('hub /travel shows dashboard KPIs', async ({ page }) => {
    await installTravelApiFallback(page);
    // Contrat TravelReportService::dashboard (GET /travel/reports/dashboard).
    await page.route('**/api/v1/travel/reports/dashboard**', (route) =>
      route.fulfill(
        json({
          data: {
            date: '2026-09-18',
            period_days: 7,
            trip_id: null,
            sales_today: 12,
            passengers: 34,
            revenue_minor: 250000,
            confirmed_minor: 200000,
            cancellations: 2,
            occupancy_rate: 0.82,
            trips_count: 5,
          },
        }),
      ),
    );
    await installAuthenticatedSession(page, { user: travelUser });
    await page.goto('/travel');

    await expect(page.getByText('82%')).toBeVisible();
    // Tuiles d'accès rapide vers les sous-pages livrées.
    await expect(page.locator('a[href="/travel/network"]')).toBeVisible();
    await expect(page.locator('a[href="/travel/trips"]')).toBeVisible();
    await expect(page.locator('a[href="/travel/bookings"]')).toBeVisible();
    // Deux liens portail cohabitent (nav latérale + carte du hub).
    await expect(page.locator('a[href="/travel/portal"]').first()).toBeVisible();
  });

  test('network screen lists stations from the referential', async ({ page }) => {
    await installTravelApiFallback(page);
    await page.route('**/api/v1/travel/cities**', (route) =>
      route.fulfill(json({ data: [{ id: 1, name: 'Douala', country_iso2: 'CM' }] })),
    );
    await page.route('**/api/v1/travel/stations**', (route) =>
      route.fulfill(
        json({
          data: [
            {
              id: 1,
              code: 'DLA-GARE',
              name: 'Gare routière de Douala',
              city_id: 1,
              address: null,
              contact_phone: '+237699000000',
              timezone: 'Africa/Douala',
              is_terminal: true,
              status: 'active',
            },
          ],
        }),
      ),
    );
    await installAuthenticatedSession(page, { user: travelUser });
    await page.goto('/travel/network');

    await expect(page.getByText('DLA-GARE')).toBeVisible();
    await expect(page.getByText('Gare routière de Douala')).toBeVisible();
  });

  test('trips screen lists published trips', async ({ page }) => {
    await installTravelApiFallback(page);
    // Contrat API trips (stable, testé côté Feature).
    await page.route('**/api/v1/travel/trips**', (route) =>
      route.fulfill(
        json({
          data: [
            {
              id: 1,
              code: 'DLA-YDE-001',
              route_id: 1,
              carrier_id: null,
              vehicle_id: null,
              departure_date: '2026-09-20',
              departure_time: '08:00:00',
              arrival_date: '2026-09-20',
              arrival_time: '12:30:00',
              means_of_transport: 'bus',
              total_seats: 40,
              status: 'published',
              published_at: '2026-09-18T08:00:00+00:00',
            },
          ],
        }),
      ),
    );
    await installAuthenticatedSession(page, { user: travelUser });
    await page.goto('/travel/trips');

    await expect(page.getByText('DLA-YDE-001')).toBeVisible();
  });

  test('bookings screen lists bookings and opens the detail with tickets', async ({ page }) => {
    await installTravelApiFallback(page);
    await page.route('**/api/v1/travel/classes**', (route) =>
      route.fulfill(json({ data: [{ id: 3, code: 'STD', label: 'Standard' }] })),
    );
    await page.route('**/api/v1/travel/trips**', (route) =>
      route.fulfill(
        json({
          data: [
            {
              id: 1,
              code: 'DLA-YDE-001',
              departure_date: '2026-09-20',
              departure_time: '08:00:00',
            },
          ],
        }),
      ),
    );
    const bookingSummary = {
      id: 42,
      reference: 'BKG-2026-0042',
      trip_id: 1,
      status: 'confirmed',
      passenger_count: 1,
      total_amount_minor: 15000,
      currency: 'XAF',
      booking_source: 'office',
      payment_status: 'confirmed',
      expires_at: null,
      contact_email: 'client@exemple.cm',
      contact_phone: '+237677000000',
      created_at: '2026-09-17T10:00:00+00:00',
    };
    // Un seul handler pour liste ET détail : dans les globs Playwright,
    // `?` matche un caractère quelconque — discriminer sur le pathname.
    await page.route('**/api/v1/travel/bookings**', (route) => {
      const pathname = new URL(route.request().url()).pathname;
      if (pathname.endsWith('/travel/bookings')) {
        return route.fulfill(json({ data: [bookingSummary] }));
      }
      // Détail : TravelBookingResource avec passagers + billets chargés.
      return route.fulfill(
        json({
          data: {
            ...bookingSummary,
            passengers: [
              {
                id: 7,
                booking_id: 42,
                full_name: 'Aline Ngo',
                birth_date: null,
                document_type: null,
                has_document: false,
                age_category: 'adult',
                class_id: 3,
                seat_number: 12,
                unit_price_minor: 15000,
              },
            ],
            tickets: [
              {
                id: 9,
                ticket_number: 'TKT-0009',
                booking_id: 42,
                passenger_id: 7,
                status: 'issued',
                issued_at: '2026-09-17T10:05:00+00:00',
                valid_from: null,
                valid_until: null,
                checked_in_at: null,
                created_at: '2026-09-17T10:05:00+00:00',
              },
            ],
          },
        }),
      );
    });
    await installAuthenticatedSession(page, { user: travelUser });
    await page.goto('/travel/bookings');

    await expect(page.getByText('BKG-2026-0042')).toBeVisible();
    await page.getByRole('button', { name: 'Voir' }).click();

    // Détail : passager (tables passagers + billets), billet émis, actions.
    await expect(page.getByText('Aline Ngo').first()).toBeVisible();
    await expect(page.getByText('TKT-0009')).toBeVisible();
    await expect(page.getByRole('button', { name: 'Embarquer' })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Révoquer' })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Rembourser', exact: true })).toBeVisible();
  });

  test('reports screen shows the four reports', async ({ page }) => {
    // A5 (#7637) — /travel/reports n'est pas encore livré dans front/web :
    // activer ce parcours quand la page atterrit (ventes, occupation,
    // recettes, annulations + export CSV asynchrone).
    test.skip(true, 'UI /travel/reports non livrée — arrive avec A5 (#7637)');
    await installTravelApiFallback(page);
    await installAuthenticatedSession(page, { user: travelUser });
    await page.goto('/travel/reports');
    await expect(page.getByText(/Ventes/i)).toBeVisible();
  });
});
