import { test, expect } from '@playwright/test';

/**
 * E2E \u2014 TH\u00c8ME UNIQUE (clair). Refonte vitrine #8461.
 *
 * La bascule jour/nuit a \u00e9t\u00e9 RETIR\u00c9E de l'interface et son code supprim\u00e9
 * (`useDarkMode`, `DarkModeProvider`). Ce spec remplace l'ancien
 * `dark-mode-toggle.spec.ts` : il ne teste plus une bascule disparue, il
 * VERROUILLE le nouveau contrat \u2014 un seul th\u00e8me clair, quel que soit le
 * syst\u00e8me, avec un contraste texte/fond conforme WCAG AA.
 */

/** Contraste WCAG 2.1 (m\u00eame formule que le backend ContrastingTextColor). */
const CONTRAST_FN = `(() => {
  const lum = ([r, g, b]) => {
    const f = (c) => { c /= 255; return c <= 0.03928 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4); };
    return 0.2126 * f(r) + 0.7152 * f(g) + 0.0722 * f(b);
  };
  const parse = (s) => (s.match(/[\\d.]+/g) || []).slice(0, 3).map(Number);
  const el = document.querySelector('main') || document.body;
  const bg = parse(getComputedStyle(document.body).backgroundColor);
  const fg = parse(getComputedStyle(el).color);
  const L1 = lum(bg), L2 = lum(fg);
  const ratio = (Math.max(L1, L2) + 0.05) / (Math.min(L1, L2) + 0.05);
  return { ratio, bg, fg, hasDarkClass: document.documentElement.classList.contains('dark') };
})()`;

test.describe('Vitrine \u2014 th\u00e8me unique clair (#8461)', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/');
  });

  test('aucun bouton de bascule jour/nuit ne subsiste', async ({ page }) => {
    const toggle = page.locator(
      'button[aria-label*="dark" i], button[aria-label*="theme" i], button[aria-label*="mode" i]'
    );
    await expect(toggle).toHaveCount(0);
  });

  test('la classe .dark n\u2019est jamais pos\u00e9e et le rendu reste clair', async ({ page }) => {
    const state = await page.evaluate(CONTRAST_FN as unknown as () => unknown);
    expect((state as { hasDarkClass: boolean }).hasDarkClass).toBe(false);
    // Fond clair : la luminance du fond doit rester \u00e9lev\u00e9e.
    const bg = (state as { bg: number[] }).bg;
    expect(bg[0]).toBeGreaterThan(200);
  });

  test('le contraste texte/fond respecte WCAG AA (\u2265 4.5:1)', async ({ page }) => {
    const { ratio } = (await page.evaluate(
      CONTRAST_FN as unknown as () => unknown
    )) as { ratio: number };
    expect(ratio).toBeGreaterThanOrEqual(4.5);
  });

  test('le th\u00e8me unique tient sur les pages principales', async ({ page }) => {
    for (const path of ['/employes', '/pricing', '/download']) {
      await page.goto(path);
      const { hasDarkClass } = (await page.evaluate(
        CONTRAST_FN as unknown as () => unknown
      )) as { hasDarkClass: boolean };
      expect(hasDarkClass, `classe .dark inattendue sur ${path}`).toBe(false);
      await expect(page.locator('header').first()).toBeVisible();
    }
  });
});

test.describe('S\u00e9lecteur de langue \u2014 ic\u00f4ne seule, accessible (#8461)', () => {
  test('le libell\u00e9 texte a disparu mais le nom accessible demeure', async ({ page }) => {
    await page.goto('/');
    // L'ancien libell\u00e9 visible n'existe plus.
    await expect(page.locator('nav >> text=/^Fran\u00e7ais$/')).toHaveCount(0);
    // Le vrai contr\u00f4le reste un <select> avec un nom accessible.
    const select = page.locator('select[aria-label]').first();
    await expect(select).toBeAttached();
    const label = await select.getAttribute('aria-label');
    expect(label && label.trim().length).toBeGreaterThan(0);
  });
});
