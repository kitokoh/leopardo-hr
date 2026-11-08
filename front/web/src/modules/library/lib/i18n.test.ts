import { LIBRARY_LOCALES, getLibraryCopy } from '../data/copy';
import { LIBRARY_CATEGORIES, LIBRARY_RESOURCES } from '../data/catalog';

/**
 * Garde-fou de cohérence i18n de la bibliothèque.
 *
 * Vérifie que les 4 langues (fr/en/tr/ar) sont complètes et de structure
 * identique, que chaque catégorie et chaque modèle d'accès a un libellé dans
 * chaque langue, et que l'arabe (RTL) est bien présent. Toute clé manquante
 * fait échouer le test.
 */

function flattenKeys(value: unknown, prefix = ''): string[] {
  if (value === null || typeof value !== 'object') return [prefix];
  return Object.entries(value as Record<string, unknown>).flatMap(([key, child]) =>
    flattenKeys(child, prefix ? `${prefix}.${key}` : key),
  );
}

describe('library i18n — locales', () => {
  it('déclare les 4 locales obligatoires (fr, en, tr, ar)', () => {
    expect([...LIBRARY_LOCALES]).toEqual(['fr', 'en', 'tr', 'ar']);
  });

  it('a une structure de clés identique dans les 4 langues', () => {
    const reference = flattenKeys(getLibraryCopy('fr')).sort();
    for (const locale of LIBRARY_LOCALES) {
      expect(flattenKeys(getLibraryCopy(locale)).sort()).toEqual(reference);
    }
  });

  it('ne laisse aucune chaîne vide dans les 4 langues', () => {
    for (const locale of LIBRARY_LOCALES) {
      const copy = getLibraryCopy(locale);
      for (const key of flattenKeys(copy)) {
        const value = key.split('.').reduce<unknown>((acc, part) => (acc as Record<string, unknown>)[part], copy);
        expect(typeof value).toBe('string');
        expect((value as string).trim().length).toBeGreaterThan(0);
      }
    }
  });

  it('retombe sur le français pour une locale inconnue', () => {
    expect(getLibraryCopy('xx').home.title).toBe(getLibraryCopy('fr').home.title);
  });
});

describe('library i18n — catégories et accès', () => {
  it('résout chaque catégorie du catalogue dans les 4 langues', () => {
    for (const locale of LIBRARY_LOCALES) {
      const copy = getLibraryCopy(locale);
      for (const category of LIBRARY_CATEGORIES) {
        expect(copy.categories[category.id]).toBeTruthy();
      }
    }
  });

  it('résout chaque modèle d’accès dans les 4 langues', () => {
    for (const locale of LIBRARY_LOCALES) {
      const copy = getLibraryCopy(locale);
      for (const model of ['free', 'read_only', 'purchase', 'subscription', 'private'] as const) {
        expect(copy.access[model]).toBeTruthy();
      }
    }
  });

  it('résout chaque type de document dans les 4 langues', () => {
    for (const locale of LIBRARY_LOCALES) {
      const copy = getLibraryCopy(locale);
      for (const type of ['book', 'ebook', 'guide', 'manual', 'report', 'article'] as const) {
        expect(copy.types[type]).toBeTruthy();
      }
    }
  });

  it('couvre toutes les catégories utilisées par le catalogue', () => {
    const declared = new Set(LIBRARY_CATEGORIES.map((category) => category.id));
    for (const resource of LIBRARY_RESOURCES) {
      expect(declared.has(resource.category)).toBe(true);
    }
  });
});

describe('library i18n — arabe (RTL)', () => {
  it('fournit une copie arabe complète', () => {
    const ar = getLibraryCopy('ar');
    expect(ar.home.title).toBeTruthy();
    expect(ar.home.subtitle).toBeTruthy();
    expect(ar.detail.readOnline).toBeTruthy();
    expect(ar.reader.title).toBeTruthy();
  });

  it('contient des caractères arabes dans les libellés clés', () => {
    const ar = getLibraryCopy('ar');
    const arabic = /[\u0600-\u06FF]/;
    expect(arabic.test(ar.home.subtitle)).toBe(true);
    expect(arabic.test(ar.detail.readOnline)).toBe(true);
    expect(arabic.test(ar.categories['livres-ebooks'])).toBe(true);
  });
});
