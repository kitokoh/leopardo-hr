/**
 * Leopardo Toolbox — état du catalogue dans l'URL (`?q=…&cat=…`).
 *
 * ## Le problème que ce module résout
 *
 * `ToolboxCatalog` gardait sa recherche et sa catégorie dans deux `useState`.
 * L'état ne quittait donc jamais l'onglet :
 *
 * - un catalogue filtré n'était **pas partageable** — le lien ouvrait toujours
 *   les 15 outils ;
 * - le **retour arrière** perdait le filtre (ouvrir un outil puis revenir
 *   ramenait au catalogue complet) ;
 * - le sélecteur de langue documentait « conserve les filtres du catalogue »,
 *   alors qu'il n'y avait jamais eu de filtre dans l'URL à conserver.
 *
 * ## Règle d'écriture
 *
 * L'URL reste **canonique** : on n'écrit jamais une valeur par défaut.
 * `cat=all` et `q=` (vide) sont donc absents — même convention que `?lang=fr`
 * dans `locale-href.ts`, que `sitemap.ts` et les `alternates.hreflang`
 * attendent. Un catalogue non filtré garde exactement l'URL `/toolbox`.
 *
 * ## Robustesse
 *
 * Le contenu de l'URL n'est pas fiable (lien tronqué, paramètre bricolé à la
 * main, catégorie supprimée du catalogue depuis) : toute valeur non conforme
 * est **écartée** plutôt que propagée dans l'interface.
 *
 * Fonctions PURES : aucun accès réseau, aucun état, testées directement.
 */

import { TOOL_CATEGORIES } from '../data/catalog';

/** Paramètre d'URL de la recherche plein-texte. */
export const CATALOG_QUERY_PARAM = 'q';

/** Paramètre d'URL de la catégorie active. */
export const CATALOG_CATEGORY_PARAM = 'cat';

/** Valeur de catégorie signifiant « toutes les catégories ». */
export const ALL_CATEGORIES = 'all';

/**
 * Longueur maximale d'une requête conservée.
 *
 * Une recherche plus longue n'a aucun sens sur 15 outils et gonflerait l'URL
 * au-delà de ce qu'un lien partagé peut porter sans risque de troncature.
 */
export const MAX_QUERY_LENGTH = 80;

/** État du catalogue tel qu'il est persisté dans l'URL. */
export interface CatalogQueryState {
  /** Recherche plein-texte (chaîne vide = pas de filtre). */
  query: string;
  /** Catégorie active (`all` = pas de filtre). */
  category: string;
}

/** État neutre — catalogue complet, URL canonique. */
export const EMPTY_CATALOG_QUERY: CatalogQueryState = {
  query: '',
  category: ALL_CATEGORIES,
};

/** Identifiants de catégorie réellement déclarés dans le catalogue. */
const CATEGORY_IDS = new Set<string>(TOOL_CATEGORIES.map((category) => category.id));

/** Vrai si la valeur est une catégorie connue du catalogue. */
export function isKnownCategory(value: unknown): value is string {
  return typeof value === 'string' && CATEGORY_IDS.has(value);
}

/**
 * Normalise une recherche : espaces de bord retirés, longueur bornée.
 *
 * On ne touche ni à la casse ni aux accents — c'est le filtrage qui compare
 * en forme normalisée (`normalize` dans `ToolboxCatalog`), pas l'URL : le lien
 * partagé doit rester lisible tel que l'utilisateur l'a tapé.
 */
export function normalizeQuery(raw: unknown): string {
  if (typeof raw !== 'string') return '';
  return raw.trim().slice(0, MAX_QUERY_LENGTH);
}

/**
 * Lit l'état du catalogue depuis une chaîne de requête.
 *
 * Accepte `'?q=…&cat=…'` comme `'q=…&cat=…'`. Une catégorie inconnue (ou
 * `all`) retombe sur `all` ; une requête absente ou vide retombe sur `''`.
 */
export function parseCatalogQuery(search: string | null | undefined): CatalogQueryState {
  if (typeof search !== 'string' || search === '') return { ...EMPTY_CATALOG_QUERY };

  let params: URLSearchParams;
  try {
    params = new URLSearchParams(search.startsWith('?') ? search.slice(1) : search);
  } catch {
    // Chaîne de requête non analysable : on repart du catalogue complet.
    return { ...EMPTY_CATALOG_QUERY };
  }

  const rawCategory = params.get(CATALOG_CATEGORY_PARAM);
  const category = isKnownCategory(rawCategory) ? rawCategory : ALL_CATEGORIES;

  return {
    query: normalizeQuery(params.get(CATALOG_QUERY_PARAM)),
    category,
  };
}

/**
 * Construit la chaîne de requête à écrire dans l'URL.
 *
 * Retourne `''` quand rien n'est filtré : l'appelant peut alors retirer le
 * `?` au lieu d'écrire une URL avec un point d'interrogation orphelin.
 * Les paramètres déjà présents (`lang`, `s` d'un calcul partagé) sont
 * **conservés** — ce module ne possède que `q` et `cat`.
 */
export function buildCatalogQuery(
  state: CatalogQueryState,
  existingSearch = '',
): string {
  const params = new URLSearchParams(
    existingSearch.startsWith('?') ? existingSearch.slice(1) : existingSearch,
  );

  const query = normalizeQuery(state.query);
  if (query === '') {
    params.delete(CATALOG_QUERY_PARAM);
  } else {
    params.set(CATALOG_QUERY_PARAM, query);
  }

  if (!isKnownCategory(state.category) || state.category === ALL_CATEGORIES) {
    params.delete(CATALOG_CATEGORY_PARAM);
  } else {
    params.set(CATALOG_CATEGORY_PARAM, state.category);
  }

  return params.toString();
}

/**
 * Vrai si l'état filtre réellement le catalogue.
 *
 * Sert à décider de l'affichage du bouton « effacer les filtres » : un
 * catalogue complet n'a rien à effacer.
 */
export function hasActiveFilters(state: CatalogQueryState): boolean {
  return normalizeQuery(state.query) !== '' || state.category !== ALL_CATEGORIES;
}
