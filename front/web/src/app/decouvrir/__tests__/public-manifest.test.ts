import { GET as getPublicManifest } from '../manifest.webmanifest/route';
import { GET as getRootManifest } from '../../manifest/route';

/**
 * Couloir 16 (#9103) — le manifeste PWA de la surface publique.
 *
 * ## Ce que ce test protège
 *
 * Avant ce correctif, `/decouvrir` déclarait le manifeste du produit RH :
 * `start_url: '/'`, catégories `business`/`productivity`, et quatre raccourcis
 * `/signup`, `/demo`, `/guides/rh-startup`, `/pricing`. Un visiteur qui
 * installait la PWA depuis un QR code de commerçant obtenait donc une icône
 * ouvrant la page de vente RH.
 *
 * Le test vérifie les deux moitiés de la promesse :
 *   1. le manifeste public décrit bien la surface publique ;
 *   2. le manifeste RH n'a PAS bougé (non-régression).
 *
 * Le second point compte autant que le premier : la correction la plus tentante
 * aurait été de modifier `/manifest` directement, ce qui aurait cassé
 * l'installation de la PWA du produit RH.
 */

/**
 * L'environnement de test est `jsdom`, qui n'expose ni `Request` ni `Response`
 * (API Web fournies par undici côté Node). Les handlers ne lisent qu'un seul
 * en-tête et ne renvoient qu'un JSON : on leur donne donc le strict minimum
 * qu'ils consomment, plutôt que d'importer un polyfill complet pour deux
 * propriétés.
 */
function requestWithLanguage(language: string): Request {
  return {
    headers: { get: (name: string) => (name.toLowerCase() === 'accept-language' ? language : null) },
  } as unknown as Request;
}

beforeAll(() => {
  if (typeof globalThis.Response === 'undefined') {
    class MinimalResponse {
      private readonly body: unknown;

      constructor(body: unknown) {
        this.body = body;
      }

      static json(body: unknown): MinimalResponse {
        return new MinimalResponse(body);
      }

      async json(): Promise<unknown> {
        return this.body;
      }
    }

    (globalThis as unknown as { Response: unknown }).Response = MinimalResponse;
  }
});

async function readJson(response: Response): Promise<Record<string, unknown>> {
  return (await response.json()) as Record<string, unknown>;
}

describe('manifeste PWA de la surface publique', () => {
  it('démarre sur /decouvrir et borne son scope à la surface publique', async () => {
    const manifest = await readJson(await getPublicManifest(requestWithLanguage('fr-FR,fr;q=0.9')));

    expect(manifest.start_url).toBe('/decouvrir');
    expect(manifest.scope).toBe('/decouvrir');
  });

  it('ne propose aucun raccourci du produit RH', async () => {
    const manifest = await readJson(await getPublicManifest(requestWithLanguage('fr')));
    const urls = (manifest.shortcuts as Array<{ url: string }>).map((s) => s.url);

    // Les quatre raccourcis RH ne doivent jamais apparaître ici.
    expect(urls).not.toContain('/signup?source=pwa_shortcut');
    expect(urls).not.toContain('/demo?source=pwa_shortcut');
    expect(urls).not.toContain('/guides/rh-startup?source=pwa_shortcut');
    expect(urls).not.toContain('/pricing?source=pwa_shortcut');
  });

  it('expose les trois raccourcis publics : découvrir, messages, profil', async () => {
    const manifest = await readJson(await getPublicManifest(requestWithLanguage('fr')));
    const urls = (manifest.shortcuts as Array<{ url: string }>).map((s) => s.url);

    expect(urls).toHaveLength(3);
    expect(urls[0]).toBe('/decouvrir');
    expect(urls[1]).toBe('/conversations');
    expect(urls[2]).toContain('panel=profile');
  });

  it('décrit un usage grand public, pas un usage professionnel', async () => {
    const manifest = await readJson(await getPublicManifest(requestWithLanguage('fr')));

    expect(manifest.categories).toEqual(['food', 'shopping', 'travel']);
    expect(manifest.categories).not.toContain('business');
    expect(manifest.categories).not.toContain('productivity');
  });

  it('suit la locale demandée, y compris le sens d’écriture arabe', async () => {
    const fr = await readJson(await getPublicManifest(requestWithLanguage('fr')));
    const ar = await readJson(await getPublicManifest(requestWithLanguage('ar')));

    expect(fr.lang).toBe('fr');
    expect(fr.dir).toBe('ltr');
    expect(ar.lang).toBe('ar');
    expect(ar.dir).toBe('rtl');
  });

  it('localise le nom et la description dans les quatre langues', async () => {
    const names = new Set<string>();

    for (const language of ['fr', 'en', 'tr', 'ar']) {
      const manifest = await readJson(await getPublicManifest(requestWithLanguage(language)));
      expect(typeof manifest.name).toBe('string');
      expect((manifest.name as string).length).toBeGreaterThan(0);
      expect(typeof manifest.description).toBe('string');
      expect((manifest.description as string).length).toBeGreaterThan(0);
      names.add(manifest.name as string);
    }

    // Quatre locales, quatre libellés distincts : une clé i18n manquante
    // retomberait sur le français et produirait un doublon ici.
    expect(names.size).toBe(4);
  });

  it('reste installable : display standalone et icônes 192/512', async () => {
    const manifest = await readJson(await getPublicManifest(requestWithLanguage('fr')));
    const icons = manifest.icons as Array<{ sizes: string }>;

    expect(manifest.display).toBe('standalone');
    expect(icons.some((i) => i.sizes === '192x192')).toBe(true);
    expect(icons.some((i) => i.sizes === '512x512')).toBe(true);
  });
});

describe('non-régression : le manifeste du produit RH est intact', () => {
  it('conserve start_url / et ses raccourcis métier', async () => {
    const manifest = await readJson(await getRootManifest(requestWithLanguage('fr')));
    const urls = (manifest.shortcuts as Array<{ url: string }>).map((s) => s.url);

    expect(manifest.start_url).toBe('/');
    expect(manifest.scope).toBe('/');
    expect(urls).toContain('/signup?source=pwa_shortcut');
    expect(manifest.categories).toEqual(['business', 'productivity']);
  });
});
