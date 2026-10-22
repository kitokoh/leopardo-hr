import { expect, test, type Page, type Route } from '@playwright/test';

/**
 * Leopardo Public — géolocalisation de `/decouvrir` (#8762, périmètre #8758).
 *
 * Ces deux scénarios étaient le SEUL trou de couverture du parcours public :
 * la suite `leopardo-public-parcours.spec.ts` couvre la découverte, les fiches
 * et la conversation, mais aucun test n'exerçait `navigator.geolocation`.
 *
 * Ce qui est réellement mesuré ici, côté navigateur :
 *
 *  1. **Autorisation accordée** → la position est demandée UNE fois, sur ACTION
 *     EXPLICITE (jamais au chargement), elle est transmise à l'API sous
 *     `near=lat,lng` avec un `radius_km` borné, et l'état « résultats autour de
 *     votre position » s'affiche avec une action d'effacement.
 *  2. **Autorisation refusée** → aucun `near` n'est envoyé, un message explicite
 *     annonce le refus, et la saisie manuelle ville/zone reste pleinement
 *     utilisable (elle déclenche une recherche SANS `near`).
 *
 * ⚠️ Le premier rendu de `/decouvrir` est SSR : `page.route()` ne fige que les
 * fetch du NAVIGATEUR (recherche, « Autour de moi »). C'est exactement la
 * surface testée ici — les assertions portent sur les requêtes émises APRÈS
 * une interaction, jamais sur le HTML initial.
 *
 * ⚠️ `serviceWorkers: 'block'` (playwright.config.ts, issue #2746) est requis :
 * le SW intercepte `/api/*` via son propre fetch et contournerait `page.route()`.
 */

/**
 * Timeout généreux : ces tests tournent aussi sur un serveur de DEV, où Next
 * compile chaque route à la première visite (plusieurs dizaines de secondes
 * sur un FS lent). Le délai par défaut de 30 s échouait alors pendant le
 * `page` setup, sans rapport avec le comportement testé.
 */
test.describe.configure({ timeout: 180_000 });

const DISCOVER_URL = '**/api/v1/public/discover*';

/** Position de test — Alger centre. */
const TEST_POSITION = { latitude: 36.7538, longitude: 3.0588 };

function discoverPayload() {
  return {
    data: [
      {
        type: 'restaurant',
        slug: 'leopardo-tajine',
        name: 'Leopardo Tajine',
        category: 'restaurant',
        city: 'Alger',
        country: 'DZ',
        description: 'Cuisine algérienne traditionnelle.',
        image_url: null,
        latitude: TEST_POSITION.latitude,
        longitude: TEST_POSITION.longitude,
        url: '/restaurants/leopardo-tajine',
        distance_km: 0.42,
      },
    ],
    meta: {
      current_page: 1,
      per_page: 20,
      total: 1,
      last_page: 1,
      types: ['restaurant', 'retail_shop', 'vtc_operator'],
      actions: { restaurant: ['booking', 'contact', 'order'] },
      radius_km: 10,
    },
  };
}

/**
 * Intercepte la découverte et enregistre les query strings réellement émises.
 * On renvoie une réponse valide pour que le composant sorte de l'état loading.
 */
async function captureDiscover(page: Page): Promise<string[]> {
  const calls: string[] = [];

  await page.route(DISCOVER_URL, (route: Route) => {
    calls.push(new URL(route.request().url()).search);
    return route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify(discoverPayload()),
    });
  });

  return calls;
}

test.describe('Leopardo Public — géolocalisation autorisée', () => {
  test.use({ permissions: ['geolocation'], geolocation: TEST_POSITION });

  test('« Autour de moi » transmet near=lat,lng avec un rayon borné', async ({ page }) => {
    const calls = await captureDiscover(page);

    await page.goto('/decouvrir');

    // Aucune position ne doit être demandée au chargement (pas de collecte
    // silencieuse) : la seule requête initiale est celle du SSR, non
    // interceptable ici, donc `calls` est encore vide.
    expect(calls).toHaveLength(0);

    await page.getByRole('button', { name: /autour de moi/i }).click();

    // L'état actif est annoncé (confirmation visuelle + action d'effacement).
    await expect(page.getByText(/résultats autour de votre position/i)).toBeVisible();
    await expect(page.getByRole('button', { name: /effacer la position/i })).toBeVisible();

    // La requête porte la position réelle, arrondie à 5 décimales par le
    // composant, et un rayon explicite borné par le contrat public.
    expect(calls.length).toBeGreaterThan(0);
    const nearCall = calls.find((qs) => qs.includes('near='));
    expect(nearCall).toBeDefined();

    const params = new URLSearchParams(nearCall);
    expect(params.get('near')).toBe('36.75380,3.05880');
    const radius = Number(params.get('radius_km'));
    expect(Number.isFinite(radius)).toBe(true);
    expect(radius).toBeGreaterThan(0);
    expect(radius).toBeLessThanOrEqual(50);

    // La position ne pollue JAMAIS l'URL partageable/crawlable.
    expect(page.url()).not.toContain('near=');
  });

  test('effacer la position relance une recherche sans near', async ({ page }) => {
    const calls = await captureDiscover(page);

    await page.goto('/decouvrir');
    await page.getByRole('button', { name: /autour de moi/i }).click();
    await expect(page.getByText(/résultats autour de votre position/i)).toBeVisible();

    await page.getByRole('button', { name: /effacer la position/i }).click();

    // Le bandeau disparaît et la dernière requête ne porte plus de position.
    await expect(page.getByText(/résultats autour de votre position/i)).toHaveCount(0);
    expect(calls[calls.length - 1]).not.toContain('near=');
  });
});

test.describe('Leopardo Public — géolocalisation refusée', () => {
  /**
   * Aucune permission accordée : Chromium refuse `getCurrentPosition`. C'est le
   * cas réel d'un visiteur qui clique « Bloquer » sur la demande navigateur.
   */
  test('le refus affiche un message explicite et n’envoie aucun near', async ({ page }) => {
    const calls = await captureDiscover(page);

    await page.goto('/decouvrir');
    await page.getByRole('button', { name: /autour de moi/i }).click();

    // Message de repli explicite (et non un échec silencieux).
    await expect(page.getByText(/géolocalisation refusée/i)).toBeVisible();

    // Aucune position n'a fuité vers l'API.
    expect(calls.some((qs) => qs.includes('near='))).toBe(false);
  });

  test('le repli manuel ville/zone fonctionne après un refus', async ({ page }) => {
    const calls = await captureDiscover(page);

    await page.goto('/decouvrir');
    await page.getByRole('button', { name: /autour de moi/i }).click();
    await expect(page.getByText(/géolocalisation refusée/i)).toBeVisible();

    // Saisie manuelle : c'est le chemin de repli imposé par la mission.
    await page.getByLabel(/ville/i).fill('Oran');
    await page.getByRole('button', { name: /^rechercher$/i }).click();

    const cityCall = calls.find((qs) => new URLSearchParams(qs).get('city') === 'Oran');
    expect(cityCall).toBeDefined();

    // Une recherche par ville ne doit jamais embarquer de coordonnées.
    expect(new URLSearchParams(cityCall).get('near')).toBeNull();
  });

  test('un navigateur sans API géolocalisation affiche l’état « non pris en charge »', async ({
    page,
  }) => {
    // Certains WebViews / navigateurs d'entreprise n'exposent pas l'API.
    // ⚠️ `delete navigator.geolocation` ne suffit PAS : la propriété est un
    // getter sur `Navigator.prototype`, la supprimer de l'instance laisse le
    // getter du prototype répondre (le test retombait alors sur « refusée »).
    // On masque donc explicitement la propriété sur l'instance.
    await page.addInitScript(() => {
      Object.defineProperty(navigator, 'geolocation', {
        get: () => undefined,
        configurable: true,
      });
    });

    const calls = await captureDiscover(page);

    await page.goto('/decouvrir');
    await page.getByRole('button', { name: /autour de moi/i }).click();

    // Libellé réel de `discovery.geoUnsupported` : « La géolocalisation n'est
    // pas disponible sur cet appareil. »
    await expect(page.getByText(/géolocalisation n['’]est pas disponible/i)).toBeVisible();
    expect(calls.some((qs) => qs.includes('near='))).toBe(false);
  });
});
