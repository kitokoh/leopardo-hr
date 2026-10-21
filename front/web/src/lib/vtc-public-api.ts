/**
 * P1-02 — accès serveur à la fiche publique d'un opérateur VTC/taxi.
 *
 * Rend la ressource publique `GET /api/v1/public/vtc/marketplace/operators/{slug}`
 * (BC-35 VTC, #8423) consommée par la page SSR `/operateurs/{slug}` — même
 * pattern que `restaurants-public-api.ts` : appel direct à l'API Laravel depuis
 * un Server Component (aucune auth, DTO public strict, 404 fail-closed → null).
 *
 * Le DTO est EXACTEMENT celui de l'annuaire (`VtcMarketplaceOperatorResource`) :
 * identité publique uniquement (slug, nom commercial, ville/pays, devise). La
 * grille tarifaire reste interne — un prix s'obtient par le devis public
 * `POST /public/vtc/marketplace/estimate`, jamais depuis cette fiche.
 */

import { resolveBackendBaseUrl } from '@/lib/backend-url';

/** Fiche publique d'un opérateur (GET /public/vtc/marketplace/operators/{slug}). */
export interface PublicVtcOperatorProfile {
  slug: string;
  name: string;
  city: string | null;
  country: string | null;
  currency: string | null;
}

/**
 * Fiche publique par slug — `null` = 404 fail-closed (→ `notFound()`).
 *
 * Le backend résout l'opérateur via `VtcMarketplaceService::operatorBySlug()`,
 * donc avec le même périmètre que l'annuaire : opt-in `active`, feature `vtc`
 * active, société ni `suspended` ni `expired`. Un opérateur dépublié disparaît
 * de l'annuaire ET de sa fiche.
 */
export async function getPublicVtcOperator(
  slug: string,
): Promise<PublicVtcOperatorProfile | null> {
  try {
    const response = await fetch(
      `${resolveBackendBaseUrl()}/public/vtc/marketplace/operators/${encodeURIComponent(slug)}`,
      {
        headers: { Accept: 'application/json' },
        next: { revalidate: 60 },
      },
    );

    if (!response.ok) {
      return null;
    }

    const payload = (await response.json()) as { data?: PublicVtcOperatorProfile };
    return payload.data ?? null;
  } catch {
    return null;
  }
}
