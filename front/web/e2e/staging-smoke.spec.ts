import { expect, test, type Page } from '@playwright/test';

test.describe('Web vitrine staging smoke', () => {
  const open = async (page: Page, path: string) => {
    await page.goto(path, { waitUntil: 'domcontentloaded' });
  };

  test('loads the public landing page with buyer-facing content', async ({ page }) => {
    await open(page, '/');

    await expect(page).toHaveTitle(/Leopardo RH/i);
    await expect(page.locator('body')).toContainText(/Leopardo/i);
    await expect(page.locator('body')).toContainText(/Pointage|paie|absences|Attendance|payroll|leave/i);
    await expect(page.locator('body')).toContainText(/Fonctionnalités|Fonctionnalites|Tarifs|Features|Pricing|FAQ/i);
  });

  test('exposes the main acquisition and login links', async ({ page }) => {
    await open(page, '/');

    const authLinks = page.locator('a[href="/auth/login"]');
    await expect(authLinks.first()).toHaveAttribute('href', '/auth/login');
    expect(await authLinks.count()).toBeGreaterThan(0);

    await expect(page.locator('body')).toContainText(/Essai gratuit|Commencer gratuitement|Connexion|Free trial|Start free|Sign in/i);
  });

  test('keeps commercial and legal pages reachable', async ({ page }) => {
    await open(page, '/pricing');
    await expect(page.locator('body')).toContainText(/Starter|Business|Enterprise|Tarifs|Pricing/i);

    await open(page, '/privacy');
    await expect(page.locator('body')).toContainText(/Confidentialite|Privacy/i);

    await open(page, '/terms');
    await expect(page.locator('body')).toContainText(/Conditions|Terms|CGU/i);
  });

  test('keeps locale and email acquisition entry points in the delivered HTML', async ({ page }) => {
    await open(page, '/');

    await expect(page.locator('body')).toContainText(/Francais|English|Turkce|العربية/i);

    await expect(page.locator('input[type="email"]').first()).toHaveAttribute('placeholder', /email/i);
    await expect(page.locator('form button[type="submit"]').first()).toContainText(/Tester|Try|Dene|جرّب/i);
  });

  test('Google OAuth : bouton same-origin + proxy 3xx en prod (#5174/#5170)', async ({ page, request }) => {
    await open(page, '/auth/login');

    // 1. Bouton same-origin (fix #2277) — jamais d'URL Render directe.
    const googleButton = page.locator('a[href="/api/v1/auth/google"]');
    await expect(googleButton.first()).toBeVisible();
    const hrefs = await page.locator('a[href]').evaluateAll((els) =>
      els.map((el) => el.getAttribute('href') ?? '')
    );
    for (const href of hrefs) {
      expect(href).not.toContain('onrender.com');
    }

    // 2. Smoke prod : GET /api/v1/auth/google doit répondre 3xx (redirection
    //    Google). Un 500 (env GOOGLE_CLIENT_ID/SECRET/REDIRECT_URL absents sur
    //    Render, issue #5170) fait échouer ce test — détection voulue (#5174).
    const response = await request.get('/api/v1/auth/google', { maxRedirects: 0 });
    expect(response.status()).toBeGreaterThanOrEqual(300);
    expect(response.status()).toBeLessThan(400);
  });
});
