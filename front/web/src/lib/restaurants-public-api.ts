/**
 * RESTO-903 (#7748) — accès serveur à l'annuaire public des restaurants.
 *
 * Rend les ressources publiques `/api/v1/public/restaurants*` (RESTO-901
 * #7746 annuaire/profil, RESTO-902 #7747 avis) consommées par les pages SSR
 * `/restaurants` et `/restaurants/{slug}` — même pattern que
 * `showcase-public-api.ts` : appel direct à l'API Laravel depuis un Server
 * Component (aucune auth, DTO public strict, 404 fail-closed → null).
 *
 * Côté navigateur (géolocalisation, panier, commande), les composants client
 * passent par `apiFetch` (proxy same-origin `/api/v1`) — pas par ce module.
 */

import { resolveBackendBaseUrl } from '@/lib/backend-url';

/** Item public de l'annuaire (RestaurantPublicBranchResource). */
export interface PublicRestaurantSummary {
  slug: string;
  name: string;
  establishment_type: string | null;
  cuisine_types: string[] | null;
  city: string | null;
  description: string | null;
  cover_image_url: string | null;
  latitude: number | null;
  longitude: number | null;
  /** Présent uniquement en recherche par proximité (`near=`). */
  distance_km?: number;
  rating_avg: number | null;
  reviews_count: number;
}

export interface PublicRestaurantHour {
  day_of_week: number;
  opens_at: string | null;
  closes_at: string | null;
  is_closed: boolean;
}

export interface PublicMenuProduct {
  code: string;
  name: string;
  description: string | null;
  price_minor: number;
  currency: string;
  image_asset_id?: number | null;
}

export interface PublicMenuCategory {
  name: string;
  sort_order?: number;
  products: PublicMenuProduct[];
}

/** Profil public complet (GET /public/restaurants/{slug}). */
export interface PublicRestaurantProfile {
  slug: string;
  name: string;
  establishment_type: string | null;
  cuisine_types: string[] | null;
  city: string | null;
  address: string | null;
  phone: string | null;
  description: string | null;
  cover_image_url: string | null;
  latitude: number | null;
  longitude: number | null;
  currency: string | null;
  timezone: string | null;
  hours: PublicRestaurantHour[];
  menu: PublicMenuCategory[];
  rating_avg: number | null;
  reviews_count: number;
}

export interface PublicReview {
  author_name: string;
  rating: number;
  comment: string | null;
  date: string | null;
}

export interface PublicListMeta {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}

export interface PublicRestaurantSearchParams {
  q?: string;
  city?: string;
  type?: string;
  cuisine?: string;
  near?: string;
  radius_km?: number;
  page?: number;
  per_page?: number;
}

/** Les 8 types d'établissement du contrat public (openapi RESTO-901). */
export const ESTABLISHMENT_TYPES = [
  'restaurant',
  'fast_food',
  'pizzeria',
  'brasserie',
  'cafe',
  'patisserie',
  'traiteur',
  'autre',
] as const;

export function buildRestaurantSearchQuery(params: PublicRestaurantSearchParams): string {
  const query = new URLSearchParams();
  if (params.q) query.set('q', params.q);
  if (params.city) query.set('city', params.city);
  if (params.type && (ESTABLISHMENT_TYPES as readonly string[]).includes(params.type)) {
    query.set('type', params.type);
  }
  if (params.cuisine) query.set('cuisine', params.cuisine);
  if (params.near) {
    query.set('near', params.near);
    query.set('radius_km', String(Math.min(Math.max(params.radius_km ?? 10, 0.1), 50)));
  }
  if (params.page && params.page > 1) query.set('page', String(params.page));
  if (params.per_page) query.set('per_page', String(Math.min(params.per_page, 50)));
  return query.toString();
}

interface PaginatedPayload<T> {
  data?: T[];
  meta?: Partial<PublicListMeta>;
}

function normalizeMeta(meta: Partial<PublicListMeta> | undefined, itemCount: number): PublicListMeta {
  return {
    current_page: meta?.current_page ?? 1,
    last_page: meta?.last_page ?? 1,
    per_page: meta?.per_page ?? itemCount,
    total: meta?.total ?? itemCount,
  };
}

/**
 * Recherche SSR de l'annuaire public. Retourne `null` sur erreur réseau/5xx
 * (la page affiche alors un état d'erreur propre — jamais de throw en SSR).
 */
export async function searchPublicRestaurants(
  params: PublicRestaurantSearchParams = {},
): Promise<{ items: PublicRestaurantSummary[]; meta: PublicListMeta } | null> {
  const qs = buildRestaurantSearchQuery(params);

  try {
    const response = await fetch(
      `${resolveBackendBaseUrl()}/public/restaurants${qs ? `?${qs}` : ''}`,
      {
        headers: { Accept: 'application/json' },
        // Annuaire public : cache court côté serveur (les publications de
        // profils sont rares, la fraîcheur à la minute suffit au SEO).
        next: { revalidate: 60 },
      },
    );

    if (!response.ok) {
      return null;
    }

    const payload = (await response.json()) as PaginatedPayload<PublicRestaurantSummary>;
    const items = Array.isArray(payload.data) ? payload.data : [];
    return { items, meta: normalizeMeta(payload.meta, items.length) };
  } catch {
    return null;
  }
}

/** Profil public par slug — `null` = 404 fail-closed (→ notFound()). */
export async function getPublicRestaurant(slug: string): Promise<PublicRestaurantProfile | null> {
  try {
    const response = await fetch(
      `${resolveBackendBaseUrl()}/public/restaurants/${encodeURIComponent(slug)}`,
      {
        headers: { Accept: 'application/json' },
        next: { revalidate: 60 },
      },
    );

    if (!response.ok) {
      return null;
    }

    const payload = (await response.json()) as { data?: PublicRestaurantProfile };
    return payload.data ?? null;
  } catch {
    return null;
  }
}

/** Avis publiés (première page SSR — la pagination vit côté client). */
export async function getPublicRestaurantReviews(
  slug: string,
  page = 1,
  perPage = 10,
): Promise<{ items: PublicReview[]; meta: PublicListMeta } | null> {
  try {
    const response = await fetch(
      `${resolveBackendBaseUrl()}/public/restaurants/${encodeURIComponent(slug)}/reviews?per_page=${perPage}${page > 1 ? `&page=${page}` : ''}`,
      {
        headers: { Accept: 'application/json' },
        next: { revalidate: 60 },
      },
    );

    if (!response.ok) {
      return null;
    }

    const payload = (await response.json()) as PaginatedPayload<PublicReview>;
    const items = Array.isArray(payload.data) ? payload.data : [];
    return { items, meta: normalizeMeta(payload.meta, items.length) };
  } catch {
    return null;
  }
}

/**
 * Slugs publics pour le sitemap — pagination bornée (50/page, max 10 pages =
 * 500 slugs) et best-effort : une API indisponible ne casse JAMAIS le
 * sitemap (retour partiel ou vide).
 */
export async function getAllPublicRestaurantSlugs(maxPages = 10): Promise<string[]> {
  const slugs: string[] = [];

  for (let page = 1; page <= maxPages; page += 1) {
    const result = await searchPublicRestaurants({ page, per_page: 50 });
    if (!result || result.items.length === 0) {
      break;
    }
    for (const item of result.items) {
      if (item.slug) {
        slugs.push(item.slug);
      }
    }
    if (page >= result.meta.last_page) {
      break;
    }
  }

  return slugs;
}
