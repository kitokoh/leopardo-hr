/**
 * P1-02 — accès serveur à la fiche publique d'une boutique Leopardo Marché.
 *
 * Rend la ressource publique `GET /api/v1/public/market/sellers/{sellerSlug}`
 * (BC-17 RETAIL, #7807) consommée par la page SSR `/boutiques/{slug}` — même
 * pattern que `restaurants-public-api.ts` : appel direct à l'API Laravel depuis
 * un Server Component (aucune auth, DTO public strict, 404 fail-closed → null).
 *
 * Le périmètre de publication n'est PAS ré-implémenté ici : la route est
 * protégée par le middleware `market.public`, qui résout le tenant par slug et
 * répond 404 pour un slug inconnu, une société suspendue/expirée, une feature
 * `retail` absente ou une boutique non activée. C'est exactement le même
 * périmètre que celui du provider de découverte (`RetailDiscoverableProvider`),
 * donc un résultat de `/decouvrir` a toujours une fiche — et une boutique
 * dépubliée disparaît des deux surfaces simultanément.
 */

import { resolveBackendBaseUrl } from '@/lib/backend-url';

/** Catégorie publique d'une boutique (agrégat de comptage, jamais d'id interne). */
export interface PublicShopCategory {
  id: number;
  name: string;
  products_count: number;
}

/** Fiche publique d'une boutique (GET /public/market/sellers/{slug}). */
export interface PublicShopProfile {
  name: string;
  slug: string;
  city: string | null;
  description: string | null;
  contact_phone: string | null;
  contact_email: string | null;
  currency: string | null;
  products_count: number;
  rating_avg: number | null;
  rating_count: number;
  categories: PublicShopCategory[];
}

/**
 * Fiche publique par slug — `null` = 404 fail-closed (→ `notFound()`).
 *
 * Aucun `throw` : une API indisponible produit un état d'erreur propre côté
 * page, jamais une exception SSR.
 */
export async function getPublicShop(slug: string): Promise<PublicShopProfile | null> {
  try {
    const response = await fetch(
      `${resolveBackendBaseUrl()}/public/market/sellers/${encodeURIComponent(slug)}`,
      {
        headers: { Accept: 'application/json' },
        // Fiche publique : cache court côté serveur (les publications de
        // profils sont rares, la fraîcheur à la minute suffit au SEO).
        next: { revalidate: 60 },
      },
    );

    if (!response.ok) {
      return null;
    }

    const payload = (await response.json()) as { data?: PublicShopProfile };
    return payload.data ?? null;
  } catch {
    return null;
  }
}
