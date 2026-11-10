/**
 * Couloir 16 (M1) — accès serveur aux CATÉGORIES de la découverte publique.
 *
 * ## Pourquoi un module séparé
 *
 * `public-discovery-api.ts` porte la recherche (`/public/discover`). Les
 * catégories sont un contrat DIFFÉRENT : `GET /public/discovery/categories`
 * renvoie des agrégats par catégorie avec un seuil de visibilité piloté par le
 * Superadmin (`platform_settings.discovery.category_min_pages`). Mélanger les
 * deux dans un même module rendrait le typage confus et ferait dépendre la
 * recherche d'un réglage d'administration.
 *
 * ## Règle de vérité (cahier des charges Couloir 16)
 *
 * Le champ `visible` est la SEULE autorité. Une catégorie dont `visible` est
 * faux ne doit jamais être affichée : elle existe dans les données mais n'a pas
 * assez de Pages publiées pour mériter une entrée de navigation. Afficher une
 * catégorie vide enverrait le visiteur vers une page sans résultat — c'est
 * précisément ce que le seuil évite.
 *
 * ## Best-effort
 *
 * Comme la recherche, toute erreur rend une liste vide plutôt que de propager :
 * la home doit rester servie même si l'API est indisponible. Un échec de
 * catégories ne doit pas vider la page.
 */

import { resolveBackendBaseUrl } from '@/lib/backend-url';

/** Catégorie telle que renvoyée par le contrat public. */
export interface PublicDiscoveryCategory {
  /** Clé canonique (stable, utilisée en `?type=` / `?category=`). */
  key: string;
  /** Libellé fourni par le backend (repli : la clé). */
  label: string;
  /** Nombre de Pages publiées dans cette catégorie. */
  count: number;
  /** Seuil de visibilité appliqué côté serveur. */
  visible: boolean;
}

export interface PublicDiscoveryCategoriesMeta {
  /** Seuil Superadmin appliqué (`category_min_pages`). */
  threshold: number;
  /** Ville filtrée, ou `null` pour l'agrégat global. */
  city: string | null;
}

export interface PublicDiscoveryCategories {
  categories: PublicDiscoveryCategory[];
  meta: PublicDiscoveryCategoriesMeta;
}

interface CategoriesPayload {
  data?: unknown;
  meta?: { threshold?: unknown; city?: unknown };
}

/**
 * Normalise une entrée brute.
 *
 * Le backend est la source de vérité, mais un DTO public ne doit jamais faire
 * planter le rendu : une entrée malformée est écartée, pas interprétée.
 */
function normalizeCategory(raw: unknown): PublicDiscoveryCategory | null {
  if (typeof raw !== 'object' || raw === null) {
    return null;
  }

  const entry = raw as Record<string, unknown>;
  const key = typeof entry.key === 'string' ? entry.key.trim() : '';

  if (key === '') {
    return null;
  }

  return {
    key,
    label: typeof entry.label === 'string' && entry.label !== '' ? entry.label : key,
    count: typeof entry.count === 'number' && Number.isFinite(entry.count) ? entry.count : 0,
    // Fail-closed : sans `visible` explicite, on n'affiche pas.
    visible: entry.visible === true,
  };
}

/**
 * Catégories visibles pour une ville (ou l'agrégat global si `city` est absent).
 *
 * Retourne `null` en cas d'erreur réseau/5xx — l'appelant distingue alors
 * « pas de catégories » (liste vide) de « API indisponible » (`null`), ce qui
 * permet d'afficher un état dégradé honnête plutôt qu'une section vide muette.
 */
export async function getPublicDiscoveryCategories(
  city?: string | null,
  options: { signal?: AbortSignal } = {},
): Promise<PublicDiscoveryCategories | null> {
  const params = new URLSearchParams();
  if (city && city.trim() !== '') {
    params.set('city', city.trim());
  }
  const qs = params.toString();

  try {
    const response = await fetch(
      `${resolveBackendBaseUrl()}/public/discovery/categories${qs ? `?${qs}` : ''}`,
      {
        headers: { Accept: 'application/json' },
        // Les catégories bougent au rythme des publications de Pages : une
        // revalidation horaire suffit et évite de marteler l'API au crawl.
        next: { revalidate: 3600 },
        ...(options.signal ? { signal: options.signal } : {}),
      },
    );

    if (!response.ok) {
      return null;
    }

    const payload = (await response.json()) as CategoriesPayload;
    const rawList = Array.isArray(payload.data) ? payload.data : [];

    const categories = rawList
      .map(normalizeCategory)
      .filter((entry): entry is PublicDiscoveryCategory => entry !== null);

    return {
      categories,
      meta: {
        threshold:
          typeof payload.meta?.threshold === 'number' ? payload.meta.threshold : 0,
        city: typeof payload.meta?.city === 'string' ? payload.meta.city : null,
      },
    };
  } catch {
    return null;
  }
}

/**
 * Sous-ensemble réellement affichable : `visible === true` ET au moins une Page.
 *
 * Le second test n'est pas redondant : le seuil est un réglage Superadmin qui
 * peut être abaissé à 0, auquel cas `visible` serait vrai pour une catégorie
 * vide. On ne fait pas confiance au seul drapeau pour une décision d'affichage.
 */
export function selectDisplayableCategories(
  categories: readonly PublicDiscoveryCategory[],
): PublicDiscoveryCategory[] {
  return categories.filter((category) => category.visible && category.count > 0);
}
