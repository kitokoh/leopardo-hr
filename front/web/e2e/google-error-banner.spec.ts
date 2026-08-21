import { expect, test } from '@playwright/test';

/**
 * Issue #5173 — erreurs Google silencieuses sur la page login.
 *
 * Le callback vitrine (`/api/v1/auth/google/callback`) redirige vers
 * `/auth/login?error=<code>` en cas d'échec (google, google_network,
 * google_auth_failed, google_no_account). La page doit afficher un message
 * localisé dans le bandeau `role=alert` — un formulaire muet fait croire à
 * une panne au lieu d'une action possible (ex. demander une invitation).
 */

// Locale forcée : le message affiché dépend de getPreferredLocale()
// (navigator.language) — on fige fr-FR pour une assertion déterministe.
test.use({ locale: 'fr-FR' });

const ERROR_CASES: Array<[code: string, fragment: string]> = [
  ['google', 'La connexion avec Google a échoué'],
  ['google_network', 'Impossible de contacter Google'],
  ['google_auth_failed', 'Google a refusé la connexion'],
  ['google_no_account', 'invitation à votre administrateur'],
];

for (const [code, fragment] of ERROR_CASES) {
  test(`affiche un message localisé pour ?error=${code}`, async ({ page }) => {
    await page.goto(`/auth/login?error=${code}`);

    const alert = page.getByRole('alert');
    await expect(alert).toBeVisible();
    await expect(alert).toContainText(fragment);
  });
}

test('aucun bandeau d’erreur quand ?error est inconnu ou absent', async ({ page }) => {
  await page.goto('/auth/login');

  // Pas d'erreur dans l'URL → pas de bandeau (le formulaire reste normal).
  await expect(page.getByRole('alert')).toHaveCount(0);
  await expect(page.getByLabel(/email/i).first()).toBeVisible();

  // Code inconnu → on ne masque pas le formulaire par un faux message.
  await page.goto('/auth/login?error=some_unknown_code');
  await expect(page.getByRole('alert')).toHaveCount(0);
});
