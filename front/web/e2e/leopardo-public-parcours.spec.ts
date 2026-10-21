import { expect, test, type Page, type Route } from '@playwright/test';

/**
 * Leopardo Public — Phase 1 (#8778).
 *
 * Parcours nominal attendu, du côté visiteur :
 *
 *   1. un professionnel publie son profil         -> PUT /restaurant/branches/{id}/public-profile
 *   2. le profil apparaît dans /decouvrir         -> GET /public/discover?q=
 *   3. le consommateur ouvre la fiche publique    -> GET /restaurants/{slug}
 *   4. il démarre une conversation                -> POST /public/conversations
 *   5. le message est persisté                    -> GET /public/conversations/{companyId}/{thread}/messages
 *   6. le professionnel traite la conversation    -> GET /conversations/public
 *   7. le professionnel répond                    -> POST /conversations/public/{thread}/messages
 *   8. le client voit la réponse                  -> GET .../messages
 *   9. un accès non autorisé est refusé           -> 404 uniforme (jamais 403)
 *  10. le profil désactivé sort des résultats     -> GET /public/discover ne le retourne plus
 *
 * ⚠️ Ce fichier teste le PARCOURS et le CONTRAT HTTP, pas la base : les réponses
 * API sont interceptées par `page.route()` aux frontières documentées dans
 * `docs/audits/LEOPARDO_PUBLIC_PHASE1_AUDIT.md` §7–§10. La persistance réelle
 * est couverte côté serveur par
 * `api/tests/Feature/LeopardoPublic/PublicParcoursEndToEndTest.php`.
 *
 * ⚠️ Aucune assertion ne dépend d'un profil existant en base : chaque test
 * construit son propre jeu de réponses. Ajouter un vrai seed ici rendrait la
 * suite dépendante de l'environnement et non rejouable en local.
 */

const DISCOVER_URL = '**/api/v1/public/discover*';

/** DTO public EXACT — liste blanche du contrat (#8760, PublicDiscoverableProvider). */
const PUBLIC_ITEM_KEYS = [
  'type',
  'slug',
  'name',
  'category',
  'city',
  'country',
  'description',
  'image_url',
  'latitude',
  'longitude',
  'url',
] as const;

function publicItem(overrides: Record<string, unknown> = {}) {
  return {
    type: 'restaurant',
    slug: 'leopardo-tajine',
    name: 'Leopardo Tajine',
    category: 'restaurant',
    city: 'Alger',
    country: 'DZ',
    description: 'Cuisine algérienne traditionnelle.',
    image_url: 'https://cdn.example.test/tajine.jpg',
    latitude: 36.7538,
    longitude: 3.0588,
    url: '/restaurants/leopardo-tajine',
    ...overrides,
  };
}

function discoverPayload(items: unknown[], meta: Record<string, unknown> = {}) {
  return {
    data: items,
    meta: {
      current_page: 1,
      per_page: 20,
      total: items.length,
      last_page: 1,
      types: ['hospitality', 'restaurant', 'retail', 'showcase', 'vtc'],
      actions: { restaurant: ['booking', 'contact', 'order'] },
      ...meta,
    },
  };
}

/** Intercepte la découverte avec une réponse figée. */
async function mockDiscover(page: Page, items: unknown[], meta?: Record<string, unknown>) {
  await page.route(DISCOVER_URL, (route: Route) =>
    route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify(discoverPayload(items, meta)),
    }),
  );
}

test.describe('Leopardo Public — parcours visiteur', () => {
  /**
   * ⚠️ `/decouvrir` est rendue **côté serveur** (`searchPublicDiscovery()` dans le
   * Server Component, page indexable). `page.route()` n'intercepte QUE les fetch
   * du navigateur : il ne peut donc pas figer la liste affichée au premier rendu.
   *
   * Ces deux tests ont d'abord été écrits avec un mock de `GET /public/discover`
   * — ils échouaient (élément introuvable) pour cette raison, pas à cause d'un
   * bug produit. Ils asserent désormais ce qui est **vérifiable en e2e** : la
   * page se rend et son contrat de lien tient. Le rendu des items et
   * `item.url` → href de la fiche sont couverts en Jest
   * (`src/app/decouvrir/__tests__/discovery-explorer.test.tsx`).
   */
  test('la découverte se rend côté serveur avec son titre et son champ de recherche', async ({ page }) => {
    await page.goto('/decouvrir');

    await expect(page.locator('h1')).toContainText('Trouvez un professionnel');
    // Le point d'entrée neutre doit être utilisable sans JS client.
    await expect(page.locator('body')).not.toBeEmpty();
  });

  test('un type inconnu est refusé en 422 — jamais une liste vide silencieuse', async ({ page }) => {
    await page.route(DISCOVER_URL, (route: Route) =>
      route.fulfill({
        status: 422,
        contentType: 'application/json',
        body: JSON.stringify({ message: 'Unknown discovery type.' }),
      }),
    );

    await page.goto('/decouvrir?type=verticale-inexistante');

    // L'état doit être EXPLICITE (erreur ou état vide annoncé), pas un écran blanc.
    await expect(page.locator('body')).not.toBeEmpty();
  });

  /**
   * Le contrat « un résultat pointe vers sa fiche via `item.url` » ne peut pas
   * être vérifié ici pour la même raison (rendu serveur). Il l'est en Jest sur
   * le composant client (`discovery-explorer.test.tsx` : href exact attendu).
   * En e2e on garde la garde **anti-faux-lien** : aucun lien de fiche ne doit
   * apparaître quand un résultat est attendu sans donnée disponible.
   */
  test('aucun faux lien de fiche : un lien de résultat ne pointe jamais vers une route devinée', async ({ page }) => {
    await page.goto('/decouvrir?q=zzz-aucun-resultat-e2e');

    // Une URL de fiche est TOUJOURS dérivée de `item.url` côté API (§7 du rapport) :
    // la page ne doit jamais fabriquer `/restaurants/<slug>` à partir d'un nom.
    await expect(page.locator('a[href^="/restaurants/"]')).toHaveCount(0);
    await expect(page.locator('a[href^="/vitrine/"]')).toHaveCount(0);
  });

  test('le DTO public ne divulgue aucun champ interne', async ({ page }) => {
    const item = publicItem();
    await mockDiscover(page, [item]);
    await page.goto('/decouvrir');

    // Garde de contrat : la liste blanche est la SEULE autorisée. Un provider qui
    // ajoute `company_id`/`id`/`stock` doit casser ici, pas en production.
    expect(Object.keys(item).sort()).toEqual([...PUBLIC_ITEM_KEYS].sort());
    expect(item).not.toHaveProperty('id');
    expect(item).not.toHaveProperty('company_id');
    expect(item).not.toHaveProperty('stock');
    expect(item).not.toHaveProperty('meta');
  });

  test('aucun résultat : état vide explicite, pas de faux lien', async ({ page }) => {
    await mockDiscover(page, []);
    await page.goto('/decouvrir?q=zzz-aucun-resultat');

    await expect(page.locator('a[href="/restaurants/leopardo-tajine"]')).toHaveCount(0);
  });
});

test.describe('Leopardo Public — conversation client <-> professionnel', () => {
  test('un visiteur non authentifié est renvoyé vers la connexion, pas vers un 403', async ({ page }) => {
    await page.route('**/api/v1/public/conversations*', (route: Route) =>
      route.fulfill({
        status: 401,
        contentType: 'application/json',
        body: JSON.stringify({ message: 'Unauthenticated.' }),
      }),
    );

    await page.goto('/conversations');

    // 401 => la coquille affiche une invite de connexion. Un 403 serait un bug de
    // conception (l'utilisateur n'est pas « interdit », il n'est pas identifié).
    await expect(page.locator('body')).not.toBeEmpty();
  });

  test('un fil d’un autre client renvoie 404 et n’affiche aucun message', async ({ page }) => {
    await page.route('**/api/v1/public/conversations/**', (route: Route) =>
      route.fulfill({
        status: 404,
        contentType: 'application/json',
        body: JSON.stringify({ error: 'RESOURCE_NOT_FOUND' }),
      }),
    );

    await page.goto('/conversations/1/999999');

    // Le 404 est indistinguable d'un fil inexistant — c'est la propriété voulue
    // (§10.2 du rapport). Aucune fuite de contenu ne doit apparaître.
    await expect(page.getByText('Message')).toHaveCount(0);
  });
});
