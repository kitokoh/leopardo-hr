/**
 * SUPERAPP-P0 (#8611) — accès serveur à la découverte publique unifiée.
 *
 * Rend la ressource publique `GET /api/v1/public/discover` (agrégateur
 * cross-verticale `PublicDiscoveryService`, providers `PublicDiscoverableProvider`)
 * consommée par la page SSR `/decouvrir` — même pattern que
 * `restaurants-public-api.ts` : appel direct à l'API Laravel depuis un Server
 * Component (aucune auth, DTO public strict, erreur → `null`).
 *
 * Le point d'entrée est NEUTRE : le client n'a pas besoin de connaître la
 * verticale avant de chercher. Chaque item porte son `type` canonique et son
 * `url` de fiche publique verticale déjà opérationnelle.
 *
 * Côté navigateur (géolocalisation, filtres), le composant client passe par
 * `apiFetch` (proxy same-origin `/api/v1`) — pas par ce module.
 */

import { resolveBackendBaseUrl } from '@/lib/backend-url';

/** Item public agrégé (DTO strict — aucune donnée interne). */
export interface PublicDiscoveryItem {
  type: string;
  slug: string;
  name: string;
  category?: string | null;
  city?: string | null;
  description?: string | null;
  image_url?: string | null;
  /** Fiche publique verticale existante (jamais une route inventée). */
  url?: string | null;
  latitude?: number | null;
  longitude?: number | null;
  /** Présent uniquement en recherche par proximité (`near=`). */
  distance_km?: number;
}

export interface PublicDiscoveryMeta {
  total: number;
  per_page: number;
  page: number;
  last_page: number;
  /** Types canoniques autorisés à `?type=` (source des catégories affichées). */
  types: string[];
  radius_km: number | null;
  /**
   * #8761 — destinations navigateur des actions, par type canonique.
   *
   * Distinct de `actions` : `actions` dit qu'un service métier EXISTE,
   * `action_urls` dit si le client peut l'ATTEINDRE. Absence de clé ⇒ aucune
   * destination dans la passerelle ⇒ l'UI affiche « indisponible » au lieu de
   * lier vers la fiche profil (qui n'a pas toujours de parcours d'achat).
   *
   * Gabarit `{slug}` à substituer par le slug de l'item.
   */
  action_urls?: Record<string, Record<string, string | null>>;
  /**
   * #8761 — actions RÉELLES par type canonique, déclarées par chaque verticale
   * côté backend (`PublicDiscoverableProvider::supportedActions()`). Le
   * frontend s'en sert comme unique source de vérité : aucune liste de
   * capacités codée en dur ici, donc aucun bouton inopérant.
   */
  actions?: Record<string, string[]>;
}

export interface PublicDiscoveryParams {
  q?: string;
  city?: string;
  type?: string;
  near?: string;
  radius_km?: number;
  page?: number;
  per_page?: number;
}

/** Bornes du contrat public (miroir de `PublicDiscoveryService`). */
export const DISCOVERY_MAX_PER_PAGE = 50;
export const DISCOVERY_DEFAULT_RADIUS_KM = 10;
export const DISCOVERY_MAX_RADIUS_KM = 50;

/**
 * Construit la query string du contrat public.
 *
 * `type` n'est transmis que s'il fait partie des types canoniques connus :
 * l'API REFUSE un type inconnu (422) — on ne laisse jamais partir une valeur
 * qui produirait une erreur d'utilisateur.
 */
export function buildDiscoveryQuery(
  params: PublicDiscoveryParams,
  knownTypes: readonly string[] = [],
): string {
  const query = new URLSearchParams();
  if (params.q) query.set('q', params.q);
  if (params.city) query.set('city', params.city);
  if (params.type && (knownTypes.length === 0 || knownTypes.includes(params.type))) {
    query.set('type', params.type);
  }
  if (params.near) {
    query.set('near', params.near);
    query.set(
      'radius_km',
      String(
        Math.min(
          Math.max(params.radius_km ?? DISCOVERY_DEFAULT_RADIUS_KM, 0.1),
          DISCOVERY_MAX_RADIUS_KM,
        ),
      ),
    );
  }
  if (params.page && params.page > 1) query.set('page', String(params.page));
  if (params.per_page) {
    query.set('per_page', String(Math.min(params.per_page, DISCOVERY_MAX_PER_PAGE)));
  }
  return query.toString();
}

interface DiscoveryPayload {
  data?: PublicDiscoveryItem[];
  meta?: Partial<PublicDiscoveryMeta>;
}

function normalizeMeta(
  meta: Partial<PublicDiscoveryMeta> | undefined,
  itemCount: number,
): PublicDiscoveryMeta {
  return {
    total: meta?.total ?? itemCount,
    per_page: meta?.per_page ?? itemCount,
    page: meta?.page ?? 1,
    last_page: meta?.last_page ?? 1,
    types: Array.isArray(meta?.types) ? meta.types : [],
    radius_km: meta?.radius_km ?? null,
    actions:
      meta?.actions && typeof meta.actions === 'object' ? meta.actions : undefined,
    // #8761 — les destinations d'action doivent survivre à la normalisation :
    // sans cette ligne, le SSR perdait `action_urls` et TOUS les CTA
    // « Réserver » / « Commander » retombaient sur « indisponible » au premier
    // rendu (le client les récupérait seulement après une recherche AJAX).
    action_urls:
      meta?.action_urls && typeof meta.action_urls === 'object'
        ? meta.action_urls
        : undefined,
  };
}

/**
 * Recherche SSR de la découverte publique. Retourne `null` sur erreur
 * réseau/5xx (la page affiche alors un état d'erreur propre — jamais de throw
 * en SSR).
 */
export async function searchPublicDiscovery(
  params: PublicDiscoveryParams = {},
  options: { signal?: AbortSignal } = {},
): Promise<{ items: PublicDiscoveryItem[]; meta: PublicDiscoveryMeta } | null> {
  const qs = buildDiscoveryQuery(params);

  try {
    const response = await fetch(
      `${resolveBackendBaseUrl()}/public/discover${qs ? `?${qs}` : ''}`,
      {
        headers: { Accept: 'application/json' },
        // Découverte publique : cache court côté serveur (les publications de
        // profils sont rares, la fraîcheur à la minute suffit au SEO).
        next: { revalidate: 60 },
        // #9357 — sans signal, un backend injoignable bloque jusqu'au timeout
        // TCP du runtime : le sitemap dépassait alors les 60 s de Next et le
        // build échouait (`Failed to build /sitemap.xml/route`). Le signal est
        // fourni par l'appelant (collecte sitemap) ; absent, comportement
        // inchangé pour les pages SSR.
        ...(options.signal ? { signal: options.signal } : {}),
      },
    );

    if (!response.ok) {
      return null;
    }

    const payload = (await response.json()) as DiscoveryPayload;
    const items = Array.isArray(payload.data) ? payload.data : [];
    return { items, meta: normalizeMeta(payload.meta, items.length) };
  } catch {
    return null;
  }
}

/**
 * Chemins d'annuaire par type canonique — miroir des pages servies par
 * `src/app/{boutiques,operateurs,entreprises,stay}`.
 *
 * Un type absent de cette table n'a pas d'annuaire : ses fiches ne sont donc
 * pas déclarées au sitemap (mieux vaut une fiche non listée qu'une URL 404).
 */
const VERTICAL_PATHS: Record<string, string> = {
  retail_shop: '/boutiques',
  vtc_operator: '/operateurs',
  trade_company: '/entreprises',
  hospitality_property: '/stay',
};

/**
 * Collecte les fiches publiques des verticales pour le sitemap.
 *
 * ## Pourquoi
 *
 * `/boutiques/{slug}`, `/operateurs/{slug}`, `/entreprises/{slug}` et
 * `/stay/{slug}` étaient servies mais déclarées NULLE PART : elles
 * n'apparaissaient dans aucun sitemap et n'étaient liées par aucun index, donc
 * invisibles pour le crawl. On les énumère ici via le MÊME contrat public que
 * les annuaires (`GET /api/v1/public/discover`), page par page.
 *
 * ## Garanties
 *
 * - **Best-effort** : une verticale indisponible (API down, 5xx) renvoie une
 *   liste vide — le sitemap reste servi, jamais de throw.
 * - **Borné** : `maxPages` par verticale évite qu'un catalogue géant ne fasse
 *   exploser le temps de génération du sitemap.
 * - **Dédupliqué** : un slug n'est émis qu'une fois par verticale.
 * - **Fail-closed** : seuls les profils que le backend déclare publiés
 *   apparaissent (le filtre opt-in reste côté serveur).
 */
export async function collectVerticalProfilePages(
  siteUrl: string,
  maxPages = 10,
  budgetMs = 20_000,
): Promise<Array<{ url: string; changeFrequency: 'daily'; priority: number }>> {
  // #9357 — BUDGET DE TEMPS GLOBAL.
  //
  // La version précédente bouclait 6 verticales × 10 pages EN SÉRIE, sans
  // timeout : 60 requêtes séquentielles, chacune pouvant bloquer jusqu'au
  // timeout TCP du runtime. Résultat mesuré en CI : `Failed to build
  // /sitemap.xml/route ... took more than 60 seconds` → build rouge.
  //
  // Trois garde-fous, dans cet ordre :
  //   1. `AbortSignal.timeout(budgetMs)` borne CHAQUE requête et l'ensemble ;
  //   2. les verticales sont collectées EN PARALLÈLE (6 requêtes simultanées
  //      au lieu de 60 séquentielles) ;
  //   3. le budget est partagé : une verticale lente n'empêche pas les autres
  //      de publier leurs fiches — on perd des URLs, jamais le sitemap.
  //
  // Le sitemap reste donc servi même si l'API publique est totalement
  // injoignable : c'est la garantie « best-effort » du contrat, désormais
  // réellement tenue en temps borné.
  const controller = new AbortController();
  const timer = setTimeout(() => controller.abort(), budgetMs);

  const collectOne = async (
    type: string,
    basePath: string,
  ): Promise<Array<{ url: string; changeFrequency: 'daily'; priority: number }>> => {
    const collected: Array<{ url: string; changeFrequency: 'daily'; priority: number }> = [];
    const seen = new Set<string>();

    for (let page = 1; page <= maxPages; page += 1) {
      if (controller.signal.aborted) {
        break;
      }

      const result = await searchPublicDiscovery(
        { type, page, per_page: 50 },
        { signal: controller.signal },
      );

      if (!result || result.items.length === 0) {
        break;
      }

      for (const item of result.items) {
        if (!item.slug || seen.has(item.slug)) {
          continue;
        }
        seen.add(item.slug);
        collected.push({
          url: `${siteUrl}${basePath}/${item.slug}`,
          changeFrequency: 'daily',
          priority: 0.6,
        });
      }

      if (page >= (result.meta.last_page ?? 1)) {
        break;
      }
    }

    return collected;
  };

  try {
    const perVertical = await Promise.all(
      Object.entries(VERTICAL_PATHS).map(([type, basePath]) => collectOne(type, basePath)),
    );
    return perVertical.flat();
  } finally {
    clearTimeout(timer);
  }
}
