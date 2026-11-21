/**
 * Filtrage d'une liste de fiche détail par une requête textuelle.
 *
 * ## Le défaut que ce module corrige
 *
 * Les annuaires (`/boutiques`, `/stay`, `/emploi`…) ont reçu une barre de
 * recherche en #9657/#9697. Les **fiches détail** sont restées en arrière :
 * `/emploi/{companySlug}` lisait déjà `q`, `contract_type` et `remote_policy`
 * depuis l'URL, mais n'affichait aucun champ — le filtre n'était atteignable
 * qu'en éditant l'URL à la main. Les autres fiches (catégories d'une boutique,
 * types de chambre, services d'une officine) n'offraient aucun moyen de
 * retrouver une entrée dans une liste qui peut compter des dizaines de lignes.
 *
 * ## Pourquoi le filtrage vit ici et pas dans chaque composant
 *
 * Une comparaison insensible à la casse et aux accents, un repli sur plusieurs
 * champs, et une requête vide qui ne filtre rien : trois règles qu'on ne veut
 * pas voir diverger entre huit verticales. Le composant décide *quoi* chercher
 * (`pick`), le noyau décide *comment* comparer.
 *
 * Aucune E/S, aucun import de framework.
 */

/**
 * Normalise une chaîne pour la comparaison : minuscules, accents retirés,
 * espaces réduits.
 *
 * `NFD` sépare la lettre de son diacritique, la plage `\u0300-\u036f` retire
 * les diacritiques combinants. Sans ça, « Sévres » ne trouverait pas « Sevres »
 * et un visiteur qui tape sans accent — le cas le plus fréquent sur mobile —
 * obtiendrait zéro résultat.
 */
export function normalizeQuery(value: string): string {
  return value
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
    .replace(/\s+/g, ' ')
    .trim();
}

/**
 * Vrai si `haystack` contient `needle` (déjà normalisé).
 *
 * Exporté pour que les surfaces qui filtrent sur un champ calculé (un libellé
 * de contrat, une fourchette de salaire) réutilisent exactement la même
 * comparaison que `filterByQuery`.
 */
export function matchesQuery(haystack: string | null | undefined, needle: string): boolean {
  if (needle === '') {
    return true;
  }

  if (typeof haystack !== 'string' || haystack === '') {
    return false;
  }

  return normalizeQuery(haystack).includes(needle);
}

/**
 * Filtre une liste par une requête libre.
 *
 * - Une requête vide (ou absente) renvoie la liste **telle quelle** : un
 *   annuaire vierge ne doit pas être filtré par accident.
 * - `pick` renvoie les champs comparables d'un élément ; un champ `null` est
 *   ignoré plutôt que de faire échouer la comparaison.
 * - L'ordre d'origine est conservé : le tri est une décision du domaine, pas du
 *   filtre.
 */
export function filterByQuery<T>(
  items: readonly T[],
  query: string | undefined,
  pick: (item: T) => readonly (string | null | undefined)[],
): readonly T[] {
  const needle = normalizeQuery(query ?? '');

  if (needle === '') {
    return items;
  }

  return items.filter((item) => pick(item).some((field) => matchesQuery(field, needle)));
}

/** Page d'une liste déjà filtrée. */
export interface ListPage<T> {
  readonly items: readonly T[];
  /** Page courante, 1-indexée et bornée à `[1, lastPage]`. */
  readonly page: number;
  /** Dernière page — toujours au moins 1, même sur une liste vide. */
  readonly lastPage: number;
  /** Nombre total d'éléments **avant** découpage. */
  readonly total: number;
}

/**
 * Découpe une liste en pages.
 *
 * ## Pourquoi côté serveur et pas dans le composant
 *
 * Les fiches détail sont rendues côté serveur et indexables. Une pagination
 * calculée dans le navigateur laisserait la page 2 invisible pour un crawler et
 * cassée sans JavaScript — exactement le défaut que `SurfacePagination` a
 * corrigé sur les annuaires. Le découpage se fait donc ici, avant le rendu.
 *
 * ## Bornes
 *
 * Une `page` hors intervalle est ramenée dans `[1, lastPage]` plutôt que de
 * rendre une page vide : un visiteur qui suit un lien périmé (`?page=9` sur une
 * liste réduite à 3 pages) doit voir la dernière page, pas un écran blanc.
 */
export function paginateList<T>(
  items: readonly T[],
  page: number | undefined,
  perPage: number,
): ListPage<T> {
  const size = Math.max(1, Math.floor(perPage));
  const total = items.length;
  const lastPage = Math.max(1, Math.ceil(total / size));
  const current = Math.min(Math.max(1, Math.floor(page ?? 1)), lastPage);
  const start = (current - 1) * size;

  return {
    items: items.slice(start, start + size),
    page: current,
    lastPage,
    total,
  };
}
