import { expect, test } from '@playwright/test';

/**
 * Leopardo Public — annuaires du portail public.
 *
 * Le portail grand public expose six surfaces : l'accueil neutre `/decouvrir`
 * et cinq annuaires de verticale (`/restaurants`, `/boutiques`, `/operateurs`,
 * `/entreprises`, `/stay`). Avant ce lot, seules `/decouvrir` et
 * `/restaurants` existaient : les quatre autres verticales n'avaient AUCUN
 * index, leurs fiches `/{verticale}/{slug}` étaient donc orphelines pour le
 * crawl et inatteignables depuis le portail.
 *
 * Ce que ce fichier mesure, et qui est réellement vérifiable en e2e :
 *
 *  1. chaque annuaire se rend côté serveur avec son titre (page indexable) ;
 *  2. le chrome partagé (en-tête + pied de page) est présent sur les SIX
 *     surfaces — c'est ce qui manquait : un visiteur venu d'un moteur de
 *     recherche n'avait aucun lien de retour vers le portail ;
 *  3. la navigation croisée entre verticales fonctionne (le pied de page mène
 *     d'un annuaire à l'autre).
 *
 * ⚠️ Ces pages sont des Server Components : `page.route()` n'intercepte que les
 * fetch du NAVIGATEUR et ne peut donc pas figer leur premier rendu. Les
 * assertions portent sur le chrome et le titre, servis en SSR quel que soit
 * l'état de l'API (une API indisponible affiche un état d'erreur propre, jamais
 * un écran blanc). Le rendu des items et le contrat `item.url` → `href` sont
 * couverts en Jest (`src/app/decouvrir/__tests__/discovery-explorer.test.tsx`).
 *
 * ⚠️ `serviceWorkers: 'block'` (playwright.config.ts, issue #2746) est requis :
 * le SW intercepte `/api/*` via son propre fetch et contournerait `page.route()`.
 */

/**
 * Timeout généreux : ces tests tournent aussi sur un serveur de DEV, où Next
 * compile chaque route à la première visite (plusieurs dizaines de secondes sur
 * un FS lent). Le délai par défaut de 30 s échouait alors pendant le `page`
 * setup, sans rapport avec le comportement testé.
 */
test.describe.configure({ timeout: 180_000 });

/** Les six surfaces du portail, avec un fragment attendu de leur titre H1. */
const DIRECTORIES = [
  { path: '/decouvrir', heading: /trouvez un professionnel/i },
  { path: '/restaurants', heading: /restaurant/i },
  { path: '/boutiques', heading: /boutiques/i },
  { path: '/operateurs', heading: /vtc/i },
  { path: '/entreprises', heading: /entreprises/i },
  { path: '/stay', heading: /hébergements/i },
] as const;

test.describe('Leopardo Public — chrome partagé des annuaires', () => {
  for (const directory of DIRECTORIES) {
    test(`${directory.path} rend son titre et le chrome du portail`, async ({ page }) => {
      await page.goto(directory.path);

      // Page indexable : un H1 servi côté serveur, avec le titre de la surface.
      await expect(page.locator('h1')).toBeVisible();
      await expect(page.locator('h1')).toContainText(directory.heading);

      // Chrome partagé : en-tête (navigation entre verticales) + pied de page.
      // `toBeAttached` pour l'en-tête : sa barre de liens est masquée sous `lg`
      // (menu hamburger sur mobile), mais elle doit être dans le DOM partout.
      await expect(page.locator('header')).toBeAttached();
      await expect(page.locator('footer')).toBeVisible();

      // Le lien de retour au portail est toujours présent (marque + pied de page).
      await expect(page.locator('a[href="/decouvrir"]').first()).toBeAttached();
    });
  }
});

test.describe('Leopardo Public — navigation croisée entre verticales', () => {
  test('depuis /boutiques, le pied de page mène à /restaurants', async ({ page }) => {
    await page.goto('/boutiques');

    // Le pied de page est visible sur TOUS les gabarits (desktop et mobile) :
    // c'est le point de navigation croisée fiable, contrairement à la barre du
    // haut masquée sous `lg`.
    await page.locator('footer').getByRole('link', { name: /restaurants/i }).first().click();

    await expect(page).toHaveURL(/\/restaurants$/);
    await expect(page.locator('h1')).toBeVisible();
  });

  test('depuis /stay, le pied de page mène à /decouvrir', async ({ page }) => {
    await page.goto('/stay');

    await page.locator('footer').getByRole('link', { name: /découvrir/i }).first().click();

    await expect(page).toHaveURL(/\/decouvrir$/);
    await expect(page.locator('h1')).toBeVisible();
  });
});
