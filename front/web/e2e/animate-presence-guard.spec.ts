import { test, expect } from '@playwright/test';

/**
 * #7305 — garde de non-régression : plus aucune utilisation invalide de
 * `AnimatePresence mode="wait"`.
 *
 * Contexte : Framer Motion avertit
 * `You're attempting to animate multiple children within AnimatePresence, but
 * its mode is set to "wait". This will lead to odd visual behaviour.`
 * dès que `mode="wait"` reçoit plusieurs enfants simultanés. C'était le cas de
 * la liste FAQ de `/pricing`, qui mappait TOUTES les questions filtrées dans un
 * même `AnimatePresence mode="wait"` → **6 avertissements** mesurés (console,
 * `/pricing`, 2026-09-13) et un rendu « odd visual behaviour » lors du
 * changement de catégorie. Le mode par défaut (synchrone) est le mode correct
 * pour une liste filtrée.
 *
 * Ce test échoue si l'avertissement réapparaît — y compris après interaction
 * avec les filtres de catégorie, qui re-rendent la liste.
 */

const FRAMER_MULTI_CHILD_WARNING =
  'attempting to animate multiple children within AnimatePresence';

test.describe('#7305 — AnimatePresence : aucune utilisation invalide de mode="wait"', () => {
  test('la liste FAQ de /pricing ne produit plus d’avertissement Framer', async ({ page }) => {
    const warnings: string[] = [];
    page.on('console', (message) => {
      if (message.text().includes(FRAMER_MULTI_CHILD_WARNING)) {
        warnings.push(message.text());
      }
    });

    await page.goto('/pricing');
    // La liste FAQ est rendue côté client : on attend qu'elle soit là.
    await expect(page.getByRole('heading', { level: 1 })).toBeVisible();

    // Re-rendu de la liste : le changement de catégorie remonte/retire des
    // questions — c'est le chemin qui déclenchait l'avertissement.
    const categoryFilter = page
      .getByRole('button', { name: /^(All|Billing|Trial|Support|Security|Toutes?)/i })
      .first();
    if (await categoryFilter.count()) {
      await categoryFilter.click();
      await page.waitForTimeout(600);
    }

    expect(
      warnings,
      `Avertissement Framer « mode="wait" » détecté ${warnings.length} fois sur /pricing`
    ).toHaveLength(0);
  });
});
