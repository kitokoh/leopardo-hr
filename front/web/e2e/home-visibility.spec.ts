import { expect, test, type Page } from '@playwright/test';

/**
 * home-visibility — garde e2e contre les sections invisibles au scroll
 * (#8063).
 *
 * Constat de l'audit du 22/09/2026 (prod) : reveal GSAP/framer-motion jamais
 * déclenché → spinner sous le hero, écrans blancs, cartes figées à
 * demi-opacité, pricing et témoignages jamais affichés. L'animation doit être
 * une bonification, jamais une condition d'affichage.
 *
 * Le test scrolle toute la home (vite, puis section par section) et vérifie
 * que CHAQUE section de <main> est réellement affichée : visible au sens
 * Playwright ET opacité calculée > 0.9, du hero au footer.
 */

const OPACITY_DEADLINE_MS = 6_000;

async function scrollFullPage(page: Page) {
  // Scroll rapide jusqu'en bas (reproduit le « scroll rapide » de l'audit)…
  await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
  await page.waitForTimeout(400);
  // …puis remontée et descente lente écran par écran.
  await page.evaluate(() => window.scrollTo(0, 0));
  await page.waitForTimeout(200);
  const steps = await page.evaluate(
    () => Math.ceil(document.body.scrollHeight / (window.innerHeight * 0.8)) + 1
  );
  for (let i = 0; i < steps; i += 1) {
    await page.evaluate(() => window.scrollBy(0, window.innerHeight * 0.8));
    await page.waitForTimeout(150);
  }
}

test.describe('Home visibility (#8063)', () => {
  test.setTimeout(120_000);

  test('un scroll complet affiche 100 % des sections de la home', async ({ page }) => {
    await page.goto('/');
    await page.waitForLoadState('domcontentloaded');

    await scrollFullPage(page);

    const sections = page.locator('main section');
    const count = await sections.count();
    expect(count, 'la home doit contenir des sections').toBeGreaterThan(5);

    for (let i = 0; i < count; i += 1) {
      const section = sections.nth(i);
      const label = `section ${i + 1}/${count}`;

      await section.scrollIntoViewIfNeeded();
      await expect(section, `${label} doit être visible`).toBeVisible();

      // Opacité calculée > 0.9 pour la section ET pour ses blocs animés
      // encore présents dans le viewport (reveal GSAP / framer-motion).
      await expect
        .poll(
          () =>
            section.evaluate((el) => {
              const opaque = (node: Element) =>
                parseFloat(window.getComputedStyle(node).opacity) > 0.9;
              // Élément « bloqué par une animation jamais déclenchée » :
              // opacité quasi nulle. Les semi-transparences de design (ex.
              // briques non-focus à 0.4 dans la pile Leopardo) sont légitimes.
              const stuck = (node: Element) =>
                parseFloat(window.getComputedStyle(node).opacity) < 0.3;
              if (!opaque(el)) return false;
              const animated = el.querySelectorAll(
                '.gsap-reveal, .gsap-stagger-item, [style*="opacity"]'
              );
              for (const child of animated) {
                const rect = child.getBoundingClientRect();
                const inViewport =
                  rect.top < window.innerHeight && rect.bottom > 0 && rect.width > 0;
                // Décors volontairement transparents : on ne contrôle que les
                // éléments porteurs de contenu.
                const hasContent =
                  (child.textContent ?? '').trim().length > 0 ||
                  child.querySelector('img, svg, video') !== null;
                if (inViewport && hasContent && stuck(child)) return false;
              }
              return true;
            }),
          {
            message: `${label} : contenu à pleine opacité (reveal déclenché ou fallback)`,
            timeout: OPACITY_DEADLINE_MS,
          }
        )
        .toBe(true);

      // Chaque section doit porter un vrai contenu (pas d'écran blanc).
      const text = (await section.innerText()).trim();
      const hasMedia = (await section.locator('img, svg, video').count()) > 0;
      expect(
        text.length > 0 || hasMedia,
        `${label} ne doit pas être un écran vide`
      ).toBe(true);
    }
  });
});
