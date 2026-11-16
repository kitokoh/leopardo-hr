import {
  ALL_CATEGORIES,
  CATALOG_CATEGORY_PARAM,
  CATALOG_QUERY_PARAM,
  EMPTY_CATALOG_QUERY,
  MAX_QUERY_LENGTH,
  buildCatalogQuery,
  hasActiveFilters,
  isKnownCategory,
  normalizeQuery,
  parseCatalogQuery,
} from './catalog-query';

/**
 * Tests de l'état du catalogue dans l'URL.
 *
 * Les cas « connus » sont ceux qui ont motivé le lot : lien partagé, retour
 * arrière, catégorie supprimée du catalogue, URL bricolée à la main.
 */
describe('parseCatalogQuery', () => {
  it('retourne l’état neutre pour une chaîne absente ou vide', () => {
    expect(parseCatalogQuery('')).toEqual(EMPTY_CATALOG_QUERY);
    expect(parseCatalogQuery(null)).toEqual(EMPTY_CATALOG_QUERY);
    expect(parseCatalogQuery(undefined)).toEqual(EMPTY_CATALOG_QUERY);
  });

  it('lit la recherche et la catégorie', () => {
    expect(parseCatalogQuery('?q=tva&cat=finance')).toEqual({
      query: 'tva',
      category: 'finance',
    });
  });

  it('accepte une chaîne sans « ? » initial', () => {
    expect(parseCatalogQuery('q=tva&cat=finance')).toEqual({
      query: 'tva',
      category: 'finance',
    });
  });

  it('écarte une catégorie inconnue (catégorie supprimée du catalogue)', () => {
    expect(parseCatalogQuery('?cat=inexistante')).toEqual(EMPTY_CATALOG_QUERY);
  });

  it('traite cat=all comme l’absence de filtre', () => {
    expect(parseCatalogQuery('?cat=all')).toEqual(EMPTY_CATALOG_QUERY);
  });

  it('retire les espaces de bord de la recherche', () => {
    expect(parseCatalogQuery('?q=%20%20tva%20%20').query).toBe('tva');
  });

  it('borne une recherche démesurée', () => {
    const long = 'a'.repeat(MAX_QUERY_LENGTH + 50);
    expect(parseCatalogQuery(`?q=${long}`).query).toHaveLength(MAX_QUERY_LENGTH);
  });

  it('ignore les paramètres qui ne lui appartiennent pas', () => {
    expect(parseCatalogQuery('?lang=tr&q=tva&s=abc')).toEqual({
      query: 'tva',
      category: ALL_CATEGORIES,
    });
  });

  it('ne lève jamais sur une chaîne de requête malformée', () => {
    expect(() => parseCatalogQuery('%%%')).not.toThrow();
    expect(parseCatalogQuery('%%%')).toEqual(EMPTY_CATALOG_QUERY);
  });
});

describe('buildCatalogQuery', () => {
  it('retourne une chaîne vide quand rien n’est filtré (URL canonique)', () => {
    expect(buildCatalogQuery(EMPTY_CATALOG_QUERY)).toBe('');
  });

  it('n’écrit jamais cat=all', () => {
    expect(buildCatalogQuery({ query: '', category: ALL_CATEGORIES })).toBe('');
  });

  it('n’écrit jamais une recherche vide', () => {
    expect(buildCatalogQuery({ query: '   ', category: ALL_CATEGORIES })).toBe('');
  });

  it('écrit la recherche et la catégorie', () => {
    const qs = buildCatalogQuery({ query: 'tva', category: 'finance' });
    const params = new URLSearchParams(qs);
    expect(params.get(CATALOG_QUERY_PARAM)).toBe('tva');
    expect(params.get(CATALOG_CATEGORY_PARAM)).toBe('finance');
  });

  it('conserve les paramètres existants (lang, s d’un calcul partagé)', () => {
    const qs = buildCatalogQuery({ query: 'tva', category: 'all' }, '?lang=tr&s=abc');
    const params = new URLSearchParams(qs);
    expect(params.get('lang')).toBe('tr');
    expect(params.get('s')).toBe('abc');
    expect(params.get(CATALOG_QUERY_PARAM)).toBe('tva');
  });

  it('supprime les filtres quand l’état redevient neutre, sans toucher au reste', () => {
    const qs = buildCatalogQuery(EMPTY_CATALOG_QUERY, '?lang=tr&q=tva&cat=finance');
    const params = new URLSearchParams(qs);
    expect(params.get('lang')).toBe('tr');
    expect(params.get(CATALOG_QUERY_PARAM)).toBeNull();
    expect(params.get(CATALOG_CATEGORY_PARAM)).toBeNull();
  });

  it('écarte une catégorie inconnue au lieu de l’écrire', () => {
    expect(buildCatalogQuery({ query: '', category: 'inexistante' })).toBe('');
  });

  it('fait un aller-retour stable avec parseCatalogQuery', () => {
    const state = { query: 'marge', category: 'commerce' };
    expect(parseCatalogQuery(buildCatalogQuery(state))).toEqual(state);
  });
});

describe('hasActiveFilters', () => {
  it('est faux sur l’état neutre', () => {
    expect(hasActiveFilters(EMPTY_CATALOG_QUERY)).toBe(false);
  });

  it('est vrai dès qu’une recherche est saisie', () => {
    expect(hasActiveFilters({ query: 'tva', category: ALL_CATEGORIES })).toBe(true);
  });

  it('est vrai dès qu’une catégorie est choisie', () => {
    expect(hasActiveFilters({ query: '', category: 'finance' })).toBe(true);
  });

  it('ignore une recherche faite uniquement d’espaces', () => {
    expect(hasActiveFilters({ query: '   ', category: ALL_CATEGORIES })).toBe(false);
  });
});

describe('normalizeQuery / isKnownCategory', () => {
  it('normalizeQuery rejette les non-chaînes', () => {
    expect(normalizeQuery(null)).toBe('');
    expect(normalizeQuery(42)).toBe('');
  });

  it('isKnownCategory ne reconnaît que les catégories du catalogue', () => {
    expect(isKnownCategory('finance')).toBe(true);
    expect(isKnownCategory('all')).toBe(false);
    expect(isKnownCategory('')).toBe(false);
    expect(isKnownCategory(null)).toBe(false);
  });
});
