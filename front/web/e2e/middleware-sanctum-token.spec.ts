import { expect, test } from '@playwright/test';

/**
 * Issue #6726 — le middleware Next.js (`src/middleware.ts`) rejetait les
 * tokens Sanctum `{id}|{plaintext}` : la regex de format excluait le
 * séparateur `|`, donc toute navigation vers la zone dashboard était
 * redirigée en boucle vers /auth/login en production (cookie posé par le
 * proxy `src/app/api/v1/auth/login/route.ts` au format réel « 990|1FVy… »).
 *
 * Les e2e mockés existants utilisaient un token opaque
 * (« e2e-mocked-session-token », session-helpers.ts) qui passait l'ancienne
 * regex — d'où l'absence de détection. Ce spec utilise un token au format
 * Sanctum réel.
 */
const SESSION_COOKIE_NAME = 'leopardo_token';
const SANCTUM_TOKEN = '990|1FVyYnVzSbMu8F1OCOtk';

async function setSanctumCookie(page: import('@playwright/test').Page): Promise<void> {
  const base = process.env.BASE_URL || 'http://localhost:3000';
  await page.context().addCookies([
    {
      name: SESSION_COOKIE_NAME,
      value: SANCTUM_TOKEN,
      url: base,
      httpOnly: true,
      sameSite: 'Lax',
    },
  ]);
}

test.describe('middleware — token Sanctum id|secret (#6726)', () => {
  test('cookie Sanctum → /dashboard servi (pas de redirection /auth/login)', async ({ page }) => {
    await setSanctumCookie(page);
    // Catch-all API d'abord (la dernière route enregistrée est prioritaire) :
    // évite qu'un appel non mocké redirige côté client.
    await page.route('**/api/v1/**', async (route) => {
      await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: {} }) });
    });
    // Le reste de l'app peut appeler /auth/me : mocké pour rester déterministe.
    await page.route('**/api/v1/auth/me', async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          data: {
            id: 101,
            first_name: 'Fatima',
            last_name: 'Meziane',
            email: 'fatima.meziane@techcorp-algerie.dz',
            role: 'manager',
            manager_role: 'rh',
            language: 'fr',
            is_rtl: false,
          },
        }),
      });
    });

    const response = await page.goto('/dashboard', { waitUntil: 'domcontentloaded' });
    // Le middleware ne redirige pas : statut 200 et URL restée sur /dashboard.
    expect(response?.status()).toBe(200);
    expect(page.url()).toContain('/dashboard');
    expect(page.url()).not.toContain('/auth/login');
  });

  test('cookie opaque historique → toujours accepté (non-régression)', async ({ page }) => {
    const base = process.env.BASE_URL || 'http://localhost:3000';
    await page.context().addCookies([
      {
        name: SESSION_COOKIE_NAME,
        value: 'e2e-mocked-session-token-abcdefghijklmnop',
        url: base,
        httpOnly: true,
        sameSite: 'Lax',
      },
    ]);
    await page.route('**/api/v1/**', async (route) => {
      await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: {} }) });
    });
    const response = await page.goto('/absences', { waitUntil: 'domcontentloaded' });
    expect(response?.status()).toBe(200);
    expect(page.url()).not.toContain('/auth/login');
  });

  test('sans cookie → redirigé vers /auth/login (garde intacte)', async ({ page }) => {
    await page.goto('/dashboard', { waitUntil: 'domcontentloaded' });
    expect(page.url()).toContain('/auth/login');
  });
});
