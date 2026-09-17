import { expect, test } from '@playwright/test';

/**
 * Non-régression des défauts constatés sur la vitrine (audit du 2026-09-16).
 *
 * Chaque test verrouille un défaut qui a été **mesuré sur le HTML servi**, pas
 * supposé — les corrections correspondantes sont dans le même lot.
 */
test.describe('Vitrine — médias, SEO et 404', () => {
  test.use({ viewport: { width: 1280, height: 900 } });

  test("les visuels SVG ne passent pas par l'optimiseur d'images (400 sinon)", async ({ page }) => {
    // Défaut : `next/image` envoie les SVG à `/_next/image`, qui répond 400
    // (« image type is not allowed ») tant que `dangerouslyAllowSVG` est faux.
    // Conséquence en production : couvertures d'articles et avatars d'équipe
    // cassés. Correctif : `unoptimized` sur les sources vectorielles.
    const failed: string[] = [];
    page.on('response', (response) => {
      const url = response.url();
      if (url.includes('/_next/image') && response.status() >= 400) {
        failed.push(`${response.status()} ${url}`);
      }
    });

    for (const path of ['/blog', '/about']) {
      await page.goto(path, { waitUntil: 'networkidle' });

      // Aucune image ne doit rester vide (0 = chargement échoué).
      const broken = await page.evaluate(() =>
        Array.from(document.images)
          .filter((image) => image.naturalWidth === 0)
          .map((image) => image.currentSrc || image.src),
      );
      expect(broken, `images non chargées sur ${path}`).toEqual([]);
    }

    expect(failed, 'réponses 4xx/5xx de l’optimiseur d’images').toEqual([]);
  });

  test('le titre ne répète pas la marque (gabarit racine + titre du catalogue)', async ({ page }) => {
    // Défaut mesuré : « Questions Fréquentes | FAQ Leopardo RH | Leopardo RH ».
    for (const path of ['/faq', '/pricing', '/about', '/contact']) {
      await page.goto(path, { waitUntil: 'domcontentloaded' });
      const title = await page.title();
      const brandOccurrences = (title.match(/leopardo/gi) ?? []).length;
      expect(brandOccurrences, `titre de ${path} : « ${title} »`).toBeLessThanOrEqual(1);
    }
  });

  test("les pages à jeton ne revendiquent pas le canonical de l'accueil", async ({ page }) => {
    // Défaut mesuré : /shop, /order et /travel/contact déclaraient
    // `rel="canonical"` = l'URL racine (aucune métadonnée propre), soit
    // plusieurs pages revendiquant la même URL canonique.
    for (const path of ['/shop', '/order', '/travel/contact']) {
      const response = await page.goto(path, { waitUntil: 'domcontentloaded' });
      expect(response?.status(), `${path} doit répondre`).toBeLessThan(500);

      const canonical = await page.locator('link[rel="canonical"]').first().getAttribute('href');
      expect(canonical, `canonical de ${path}`).toContain(path);

      // Ces pages ne sont atteignables qu'avec un jeton / lien signé.
      const robots = await page.locator('meta[name="robots"]').first().getAttribute('content');
      expect(robots ?? '', `robots de ${path}`).toContain('noindex');
    }
  });

  test('la page 404 a son propre titre et demande à ne pas être indexée', async ({ page }) => {
    const response = await page.goto('/cette-page-nexiste-pas-7489', { waitUntil: 'domcontentloaded' });
    expect(response?.status()).toBe(404);

    // Défaut mesuré : la 404 reprenait le titre de la PAGE D'ACCUEIL. On vérifie
    // donc qu'elle porte bien un titre de page introuvable (dans la langue
    // servie), et non la formulation exacte d'une langue donnée.
    const title = await page.title();
    const robots = await page.locator('meta[name="robots"]').first().getAttribute('content');

    // …lu AVANT toute autre navigation : après un `goto`, on lirait les
    // métadonnées de l'autre page.
    await page.goto('/', { waitUntil: 'domcontentloaded' });
    const homeTitle = await page.title();

    expect(title, 'la 404 ne doit pas reprendre le titre de l’accueil').not.toBe(homeTitle);
    expect(title).toMatch(/404|introuvable|non trouv|not found|bulunamad|غير موجودة/i);
    expect(robots ?? '', 'la 404 ne doit pas être indexable').toContain('noindex');
  });
});
