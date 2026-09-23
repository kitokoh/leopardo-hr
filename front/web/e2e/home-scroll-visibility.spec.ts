import { test, expect } from '@playwright/test';

/**
 * #8063 — Des sections entières de la home restaient vides/invisibles au scroll.
 *
 * Critère d'acceptation : un scroll complet de la home (rapide ou lent)
 * affiche 100 % des sections avec leur contenu. L'animation est une
 * bonification, jamais une condition d'affichage : chaque section doit
 * finir visible (toBeVisible) ET affichée (opacité calculée > 0.9).
 *
 * Couvre : scroll lent (déclenchement progressif des reveals) et scroll
 * rapide (saut direct en bas de page — cas qui cassait les triggers).
 */

async function scrollLent(page: import('@playwright/test').Page) {
  await page.evaluate(async () => {
    const step = 350;
    for (let y = 0; y <= document.body.scrollHeight; y += step) {
      window.scrollTo(0, y);
      await new Promise((r) => setTimeout(r, 80));
    }
  });
}

async function scrollRapide(page: import('@playwright/test').Page) {
  await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
  // Laisse le temps aux reveals déclenchés par le passage en viewport.
  await page.waitForTimeout(1500);
}

async function assertToutesSectionsVisibles(page: import('@playwright/test').Page) {
  const sections = page.locator('main section');
  const count = await sections.count();
  // Garde-fou : la home doit réellement contenir des sections (sinon le
  // test passerait à tort sur une page cassée).
  expect(count).toBeGreaterThanOrEqual(10);

  for (let i = 0; i < count; i++) {
    const section = sections.nth(i);
    await section.scrollIntoViewIfNeeded();
    await expect(section, `section #${i} doit être visible`).toBeVisible();
    const opacity = await section.evaluate(
      (el) => parseFloat(getComputedStyle(el).opacity),
    );
    expect(opacity, `section #${i} doit être révélée (opacité > 0.9)`).toBeGreaterThan(0.9);
  }
}

test.describe('#8063 — visibilité des sections de la home au scroll', () => {
  test('scroll lent : toutes les sections deviennent visibles', async ({ page }) => {
    await page.goto('/');
    await scrollLent(page);
    await assertToutesSectionsVisibles(page);
  });

  test('scroll rapide : toutes les sections deviennent visibles', async ({ page }) => {
    await page.goto('/');
    await scrollRapide(page);
    await assertToutesSectionsVisibles(page);
  });

  test('filet de sécurité : un contenu re-masqué est révélé de force', async ({ page }) => {
    await page.goto('/');
    // Simule un reveal jamais déclenché (observer mort, hydratation
    // incomplète) : le contenu est masqué APRÈS le chargement, comme le
    // faisait le bug #8063. Le filet doit le révéler sans animation.
    await page.evaluate(() => {
      const target = document.querySelector('main section');
      target?.setAttribute(
        'style',
        'opacity:0;transform:translateY(20px);filter:blur(10px)',
      );
    });
    const first = page.locator('main section').first();
    await expect(async () => {
      const opacity = await first.evaluate(
        (el) => parseFloat(getComputedStyle(el).opacity),
      );
      expect(opacity).toBeGreaterThan(0.9);
    }).toPass({ timeout: 5_000, intervals: [600, 1_000, 1_500] });
  });

  test('bande métriques : les 4 statistiques sont affichées', async ({ page }) => {
    await page.goto('/');
    await scrollLent(page);
    // La bande métriques affiche 4 stats (paie, langues, surfaces, tests).
    // Avant le correctif, la moitié droite restait vide.
    const metricsBand = page.locator('main section').filter({ hasText: /tests|Backend/i }).first();
    await metricsBand.scrollIntoViewIfNeeded();
    const cells = metricsBand.locator(':scope > div > div > *');
    await expect(metricsBand).toBeVisible();
    expect(await cells.count()).toBeGreaterThanOrEqual(4);
  });

  test('cartes pricing et témoignages finissent révélées', async ({ page }) => {
    await page.goto('/');
    await scrollLent(page);
    const pricing = page.locator('#tarifs');
    await pricing.scrollIntoViewIfNeeded();
    await expect(pricing).toBeVisible();
    const pricingOpacity = await pricing.evaluate(
      (el) => parseFloat(getComputedStyle(el).opacity),
    );
    expect(pricingOpacity).toBeGreaterThan(0.9);

    // Témoignages : la section (titre FR « Ils nous font… » / EN « They… »)
    // est visible et opaque après un scroll lent complet.
    const temoignages = page
      .locator('main section')
      .filter({ hasText: /Ils nous font|Trusted by/i })
      .first();
    await temoignages.scrollIntoViewIfNeeded();
    await expect(temoignages).toBeVisible();
  });
});
