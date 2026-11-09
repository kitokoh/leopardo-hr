import {
  PUBLIC_PORTAL_LOCALES,
  getPublicPortalCopy,
  publicTypeLabel,
} from './copy';

/**
 * Garde-fou i18n du portail public (module autonome `public-portal`).
 *
 * Le portail est une surface PUBLIQUE servie en fr/en/tr/ar (arabe RTL) : une
 * clé manquante dans une seule langue afficherait un libellé vide ou la clé
 * brute à un visiteur. Ce test verrouille l'invariant, comme les gardes
 * `library/lib/i18n.test.ts` et `toolbox/lib/consistency.test.ts` le font pour
 * leurs modules respectifs.
 *
 * Il vérifie aussi deux propriétés propres au portail :
 *  - chaque type canonique de `GET /public/discover` a un libellé lisible
 *    (jamais `retail_shop` affiché tel quel) ;
 *  - les quatre annuaires de verticale portent des descriptions DISTINCTES
 *    (quatre pages aux métadonnées identiques = contenu dupliqué pour les
 *    moteurs).
 */

/** Aplatit un objet en chemins de clés (`a.b.c`). */
function flattenKeys(value: unknown, prefix = ''): string[] {
  if (value === null || typeof value !== 'object') return [prefix];
  return Object.entries(value as Record<string, unknown>).flatMap(([key, child]) =>
    flattenKeys(child, prefix ? `${prefix}.${key}` : key),
  );
}

/** Types canoniques exposés par `GET /api/v1/public/discover`. */
const CANONICAL_TYPES = [
  'showcase',
  'restaurant',
  'hospitality_property',
  'retail_shop',
  'vtc_operator',
  'trade_company',
] as const;

/** Verticales disposant d'un annuaire d'index dédié. */
const DIRECTORY_TYPES = [
  'retail_shop',
  'vtc_operator',
  'trade_company',
  'hospitality_property',
] as const;

describe('public-portal i18n — locales', () => {
  it('déclare les 4 locales obligatoires (fr, en, tr, ar)', () => {
    expect([...PUBLIC_PORTAL_LOCALES]).toEqual(['fr', 'en', 'tr', 'ar']);
  });

  it('a une structure de clés identique dans les 4 langues', () => {
    const reference = flattenKeys(getPublicPortalCopy('fr')).sort();
    for (const locale of PUBLIC_PORTAL_LOCALES) {
      expect(flattenKeys(getPublicPortalCopy(locale)).sort()).toEqual(reference);
    }
  });

  it('ne laisse aucune chaîne vide dans les 4 langues', () => {
    for (const locale of PUBLIC_PORTAL_LOCALES) {
      const copy = getPublicPortalCopy(locale);
      for (const key of flattenKeys(copy)) {
        const value = key
          .split('.')
          .reduce<unknown>((acc, part) => (acc as Record<string, unknown>)[part], copy);
        expect(typeof value).toBe('string');
        expect((value as string).trim().length).toBeGreaterThan(0);
      }
    }
  });

  it('retombe sur le français pour une locale inconnue', () => {
    expect(getPublicPortalCopy('xx').home.title).toBe(getPublicPortalCopy('fr').home.title);
  });
});

describe('public-portal i18n — types canoniques et annuaires', () => {
  it('résout chaque type canonique de /public/discover dans les 4 langues', () => {
    for (const locale of PUBLIC_PORTAL_LOCALES) {
      const copy = getPublicPortalCopy(locale);
      for (const type of CANONICAL_TYPES) {
        expect(copy.types[type]).toBeTruthy();
      }
    }
  });

  it('résout chaque annuaire de verticale (titre + description) dans les 4 langues', () => {
    for (const locale of PUBLIC_PORTAL_LOCALES) {
      const copy = getPublicPortalCopy(locale);
      for (const type of DIRECTORY_TYPES) {
        expect(copy.directoryByType[type].title.trim().length).toBeGreaterThan(0);
        expect(copy.directoryByType[type].description.trim().length).toBeGreaterThan(0);
      }
    }
  });

  it('donne à chaque annuaire une description DISTINCTE (pas de contenu dupliqué)', () => {
    for (const locale of PUBLIC_PORTAL_LOCALES) {
      const copy = getPublicPortalCopy(locale);
      const descriptions = DIRECTORY_TYPES.map((type) => copy.directoryByType[type].description);
      expect(new Set(descriptions).size).toBe(descriptions.length);
    }
  });

  it('publicTypeLabel renvoie le libellé localisé, ou le type brut si inconnu', () => {
    expect(publicTypeLabel('fr', 'retail_shop')).toBe(getPublicPortalCopy('fr').types.retail_shop);
    expect(publicTypeLabel('en', 'vtc_operator')).toBe(getPublicPortalCopy('en').types.vtc_operator);
    // Fail-open assumé : un type non catalogué s'affiche tel quel plutôt que vide.
    expect(publicTypeLabel('fr', 'type-inconnu')).toBe('type-inconnu');
  });
});

describe('public-portal i18n — arabe (RTL)', () => {
  it('fournit une copie arabe complète', () => {
    const ar = getPublicPortalCopy('ar');
    expect(ar.chrome.brand).toBeTruthy();
    expect(ar.home.heroTitle).toBeTruthy();
    expect(ar.directory.searchLabel).toBeTruthy();
  });

  it('contient des caractères arabes dans les libellés clés', () => {
    const ar = getPublicPortalCopy('ar');
    const arabic = /[\u0600-\u06FF]/;
    expect(arabic.test(ar.home.heroTitle)).toBe(true);
    expect(arabic.test(ar.directory.searchLabel)).toBe(true);
    expect(arabic.test(ar.types.retail_shop)).toBe(true);
  });
});
