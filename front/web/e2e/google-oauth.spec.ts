import { expect, test } from '@playwright/test';

/**
 * Issue #5174 — zéro couverture e2e du flux Google OAuth (bouton + proxy).
 *
 * Le flux dépend de 3 points fragiles (issue #5174) :
 *   1. href du bouton same-origin (`/api/v1/auth/google`, fix #2277) ;
 *   2. proxy catch-all (302 transmis) ;
 *   3. callback (échange de code + cookie `leopardo_token`) — couvert par le
 *      test d'intégration hermétique `callback/__tests__/route.test.ts`.
 *
 * Le smoke « proxy répond 3xx » est réservé à la CI contre la prod
 * (GOOGLE_OAUTH_PROXY_SMOKE=1, env Render GOOGLE_* requis — issue #5170) :
 * en local/web-ci le backend de prod n'est pas fiable pour ce test.
 */
test.describe('Google OAuth — vitrine (bouton + proxy same-origin)', () => {
  test('bouton « Continue with Google » : href same-origin /api/v1/auth/google (#2277)', async ({ page }) => {
    await page.goto('/auth/login');

    const googleButton = page.locator('a[href="/api/v1/auth/google"]');
    await expect(googleButton.first()).toBeVisible();
    await expect(googleButton.first()).toHaveAttribute('href', '/api/v1/auth/google');
  });

  test('aucune URL backend Render directe dans le DOM de la page login', async ({ page }) => {
    await page.goto('/auth/login');

    const hrefs = await page.locator('a[href]').evaluateAll((els) =>
      els.map((el) => el.getAttribute('href') ?? '')
    );
    for (const href of hrefs) {
      expect(href).not.toContain('onrender.com');
    }

    const bodyHtml = await page.locator('body').innerHTML();
    expect(bodyHtml).not.toContain('gestionemployerbackend.onrender.com');
  });

  test('proxy same-origin : GET /api/v1/auth/google répond 3xx (smoke prod)', async ({ request }) => {
    test.skip(
      !process.env.GOOGLE_OAUTH_PROXY_SMOKE,
      'smoke prod uniquement : activer avec GOOGLE_OAUTH_PROXY_SMOKE=1 (env Render GOOGLE_* requis, issue #5170)'
    );

    const response = await request.get('/api/v1/auth/google', { maxRedirects: 0 });
    // 302 vers accounts.google.com attendu ; un 500 (env GOOGLE_* absent)
    // fait échouer le smoke — c'est exactement le but (détection #5170).
    expect(response.status()).toBeGreaterThanOrEqual(300);
    expect(response.status()).toBeLessThan(400);
  });
});
