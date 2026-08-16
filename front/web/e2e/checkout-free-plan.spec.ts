import { test, expect } from '@playwright/test';

/**
 * E2E — Checkout plan Free (issue #4195)
 * Une URL profonde /checkout?plan=free ne doit JAMAIS présenter le paywall
 * Pilot : elle affiche l'état « essai guidé » explicite vers /signup.
 */
test.describe('Checkout free-plan routing', () => {
  test('?plan=free shows guided-trial state, never the Pilot payment form', async ({ page }) => {
    await page.goto('/checkout?plan=free');

    // État honnête « essai guidé » (titre + CTA vers /signup)
    await expect(
      page.locator('a[href^="/signup"]').first(),
    ).toBeVisible();

    // Jamais le formulaire de paiement Pilot : pas de bouton « Payer » /
    // numéro de carte 24 € (le checkout pilot affiche un récapitulatif
    // avec montant). On vérifie l'absence du wizard de paiement.
    await expect(
      page.locator('button:has-text("Payer"), text=24 €, text=24,00 €').first(),
    ).toHaveCount(0);
  });
});
