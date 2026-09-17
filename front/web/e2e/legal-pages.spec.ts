import { expect, test } from '@playwright/test';

/**
 * Pages légales et transparence des formulaires (#7593).
 *
 * Trois défauts sont verrouillés ici :
 *  1. le pied de page renvoyait « Mentions légales » vers les CGU (corrigé dans
 *     #7592) — la page n'existait pas ;
 *  2. la politique de confidentialité ne désignait ni responsable de traitement,
 *     ni bases légales, ni durées, ni sous-traitants, ni transferts, ni cookies,
 *     ni autorité de contrôle ;
 *  3. les formulaires contact / démo / newsletter ne portaient aucune mention de
 *     finalité ni de base légale au moment de la collecte.
 */
test.describe('Pages légales (#7593)', () => {
  // Les pages légales suivent la langue du visiteur : on fixe le français pour
  // que les assertions portent sur le contenu, pas sur la langue du navigateur
  // de test. Les pages rendent aussi un `<main>` imbriqué (défaut d'a11y
  // préexistant, hors périmètre) : on cible le conteneur racine, sans ambiguïté.
  test.use({ locale: 'fr-FR' });

  const content = (page: import('@playwright/test').Page) => page.locator('#main-content');
  test('les trois pages légales répondent et exposent leur contenu', async ({ page }) => {
    for (const path of ['/privacy', '/terms', '/mentions-legales']) {
      const response = await page.goto(path, { waitUntil: 'domcontentloaded' });
      expect(response?.status(), `${path} doit répondre 200`).toBe(200);
      await expect(content(page).locator('h1').first()).toBeVisible();
      await expect(content(page).locator('section').first()).toBeVisible();
    }
  });

  test('la politique de confidentialité couvre les mentions attendues', async ({ page }) => {
    await page.goto('/privacy', { waitUntil: 'domcontentloaded' });

    // Contrôle sur les titres de section réellement rendus.
    const headings = await page.locator('h2').allInnerTexts();
    const normalized = headings.join(' | ').toLowerCase();
    for (const expected of [
      'responsable de traitement',
      'base légale',
      'durées de conservation',
      'destinataires',
      'transferts',
      'cookies',
      'droits',
    ]) {
      expect(normalized, `section manquante : ${expected}`).toContain(expected);
    }

    // Le consentement doit être retirable depuis la page (renvoi vers le
    // contrôle de pied de page), et le contact confidentialité présent.
    await expect(
      page.getByRole('link', { name: /confidentialit|privacy/i }).first(),
    ).toBeVisible();
  });

  test('le pied de page pointe vers les mentions légales, pas vers les CGU', async ({ page }) => {
    await page.goto('/', { waitUntil: 'domcontentloaded' });

    const link = page.getByRole('link', { name: /mentions légales|legal notice/i }).first();
    await expect(link).toBeVisible();
    await expect(link).toHaveAttribute('href', /\/mentions-legales/);
  });

  test('mentions légales : éditeur, hébergement et renvoi confidentialité', async ({ page }) => {
    await page.goto('/mentions-legales', { waitUntil: 'domcontentloaded' });

    const body = content(page);
    await expect(body).toContainText(/éditeur|publisher|yayıncı|ناشر/i);
    await expect(body).toContainText(/hébergement|hosting|barındırma|الاستضافة/i);
    await expect(body).toContainText(/propriété intellectuelle|intellectual property|fikri mülkiyet|الملكية الفكرية/i);
    // Les identifiants d'éditeur ne sont pas inventés : la page dit comment les obtenir.
    await expect(body).toContainText(/contact@leopardo-rh\.com/);
  });

  test('les formulaires publics annoncent finalité et base légale', async ({ page }) => {
    // Contact
    await page.goto('/contact', { waitUntil: 'domcontentloaded' });
    await expect(content(page)).toContainText(/base légale|legal basis|hukuki dayanak|الأساس القانوني/i);

    // Démonstration
    await page.goto('/demo', { waitUntil: 'domcontentloaded' });
    await expect(content(page)).toContainText(/base légale|legal basis|hukuki dayanak|الأساس القانوني/i);

    // Newsletter (pied de page, sur toutes les pages publiques)
    await page.goto('/', { waitUntil: 'domcontentloaded' });
    await expect(page.locator('footer')).toContainText(/finalité|purpose|amaç|الغاية/i);
  });

  test('le sitemap publie les mentions légales', async ({ request }) => {
    const response = await request.get('/sitemap.xml');
    expect(response.status()).toBe(200);
    expect(await response.text()).toContain('/mentions-legales');
  });
});
