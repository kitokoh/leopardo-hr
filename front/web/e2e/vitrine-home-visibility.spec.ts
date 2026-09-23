import { expect, test } from '@playwright/test';

/**
 * #8063 — la home doit être lisible à 100 % au scroll, quelle que soit la
 * fiabilité des mécanismes d'animation (IntersectionObserver, hydratation,
 * scroll rapide).
 *
 * Règle verrouillée ici : l'animation de révélation est une **bonification**,
 * jamais une condition d'affichage. Après un scroll complet de la page,
 * chaque section (hero → footer) doit être présente ET son contenu rendu avec
 * une opacité effective > 0.9 — aucun écran blanc, aucun texte gelé à
 * demi-opacité.
 */
test.describe('Vitrine — visibilité de la home au scroll (#8063)', () => {
  test.use({ viewport: { width: 1280, height: 900 } });
  // Premier chargement en dev : la compilation à la volée peut dépasser 30 s.
  test.setTimeout(120_000);

  async function collectLowOpacitySections(page: import('@playwright/test').Page) {
    return page.evaluate(() => {
      const offenders: string[] = [];
      const sections = Array.from(document.querySelectorAll('main section, main > div > section, footer'));
      for (const section of sections) {
        const rect = section.getBoundingClientRect();
        // Section sans contenu rendu = vide au scroll.
        const text = (section.textContent ?? '').trim();
        if (rect.height > 40 && text.length === 0 && !section.querySelector('img, video, canvas, svg')) {
          offenders.push(`section vide: ${section.className.slice(0, 80)}`);
          continue;
        }
        // Opacité effective : la section elle-même et ses blocs directs.
        const nodes: Element[] = [section, ...Array.from(section.children)];
        for (const node of nodes) {
          const style = window.getComputedStyle(node);
          if (style.display === 'none' || style.visibility === 'hidden') continue;
          const opacity = parseFloat(style.opacity || '1');
          const nodeText = (node.textContent ?? '').trim();
          if (opacity <= 0.9 && nodeText.length > 0) {
            offenders.push(
              `opacité ${opacity} — ${node.tagName.toLowerCase()}.${String(node.className).slice(0, 60)} — « ${nodeText.slice(0, 40)} »`,
            );
          }
        }
      }
      return offenders;
    });
  }

  for (const mode of ['lent', 'rapide'] as const) {
    test(`scroll ${mode} : toutes les sections sont visibles (opacité > 0.9)`, async ({ page }) => {
      await page.goto('/', { waitUntil: 'domcontentloaded' });

      const pageHeight = await page.evaluate(() => document.body.scrollHeight);

      if (mode === 'rapide') {
        // Saut direct en bas de page : les observers n'ont vu passer aucune
        // section intermédiaire — le contenu doit rester visible quand même.
        await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
        await page.waitForTimeout(400);
      } else {
        // Scroll progressif, un écran à la fois.
        for (let y = 0; y < pageHeight; y += 800) {
          await page.evaluate((top) => window.scrollTo(0, top), y);
          await page.waitForTimeout(120);
        }
      }

      // Remonter écran par écran : l'ancien `toggleActions … reverse`
      // re-masquait le contenu au scroll remontant.
      for (let y = pageHeight; y >= 0; y -= 800) {
        await page.evaluate((top) => window.scrollTo(0, top), y);
        await page.waitForTimeout(80);
      }
      await page.waitForTimeout(300);

      // Balayage final : plus rien ne doit être à opacité <= 0.9.
      const offendersTop = await collectLowOpacitySections(page);
      expect(offendersTop, 'sections invisibles ou à demi-opacité (haut de page)').toEqual([]);

      await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
      await page.waitForTimeout(300);
      const offendersBottom = await collectLowOpacitySections(page);
      expect(offendersBottom, 'sections invisibles ou à demi-opacité (bas de page)').toEqual([]);
    });
  }

  test('sections clés présentes et visibles après scroll complet', async ({ page }) => {
    await page.goto('/', { waitUntil: 'domcontentloaded' });
    await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
    await page.waitForTimeout(500);

    // Le pied de page (dernier bloc) et le CTA final doivent être rendus.
    await expect(page.locator('footer')).toBeVisible();

    // La bande métriques rend ses 4 statistiques (défaut constaté : 2/4).
    const metrics = page.locator('section', { has: page.locator('.grid.grid-cols-2.md\\:grid-cols-4') }).first();
    if (await metrics.count()) {
      const cells = metrics.locator('.text-center');
      const cellCount = await cells.count();
      for (let i = 0; i < cellCount; i += 1) {
        await expect(cells.nth(i)).toBeVisible();
      }
      expect(cellCount).toBeGreaterThanOrEqual(4);
    }
  });
});

/**
 * Cas déterministe : **sans JavaScript**, le HTML servi doit être lisible à
 * 100 %. Avant #8063, les reveals écrivaient `opacity: 0` inline dans le HTML
 * SSR : tout visiteur dont le JS échoue (ou tarde) voyait des écrans blancs.
 */
test.describe('Vitrine — home lisible sans JavaScript (#8063)', () => {
  test.use({ viewport: { width: 1280, height: 900 }, javaScriptEnabled: false });
  test.setTimeout(120_000);

  test('aucun élément de contenu ne porte une opacité inline <= 0.9 dans le HTML SSR', async ({ page }) => {
    await page.goto('/', { waitUntil: 'domcontentloaded' });

    const offenders = await page.evaluate(() => {
      const out: string[] = [];
      const nodes = Array.from(document.querySelectorAll<HTMLElement>('main [style*="opacity"]'));
      for (const node of nodes) {
        const opacity = parseFloat(node.style.opacity || '1');
        const text = (node.textContent ?? '').trim();
        if (!Number.isNaN(opacity) && opacity <= 0.9 && text.length > 0) {
          out.push(`opacité ${opacity} — ${node.tagName.toLowerCase()} — « ${text.slice(0, 50)} »`);
        }
      }
      return out;
    });

    expect(offenders, 'contenu SSR masqué par une opacité inline').toEqual([]);
  });
});
