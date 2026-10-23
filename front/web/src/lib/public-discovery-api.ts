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
  };
}

/**
 * Recherche SSR de la découverte publique. Retourne `null` sur erreur
 * réseau/5xx (la page affiche alors un état d'erreur propre — jamais de throw
 * en SSR).
 */
export async function searchPublicDiscovery(
  params: PublicDiscoveryParams = {},
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
