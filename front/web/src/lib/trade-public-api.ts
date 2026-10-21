/**
 * P2-01 (#8802) — accès serveur à la fiche publique d'une entreprise de
 * commerce international / B2B (BC-36 TRADE).
 *
 * Rend la ressource publique `GET /api/v1/public/trade/companies/{slug}`
 * consommée par la page SSR `/commerce/{slug}` — même pattern que
 * `vtc-public-api.ts` et `retail-public-api.ts` : appel direct à l'API Laravel
 * depuis un Server Component (aucune auth, DTO public strict, 404 fail-closed
 * → `null`).
 *
 * Le DTO ne contient QUE du contenu que le professionnel a explicitement
 * publié : nom commercial, ville, pays, description, catégories produits et
 * directions (import/export). Aucun identifiant de société, aucun volume
 * commercial, aucune marge — le backend ne les sérialise jamais.
 */

import { resolveBackendBaseUrl } from '@/lib/backend-url';

/** Fiche publique d'une entreprise de commerce. */
export interface PublicTradeCompanyProfile {
  slug: string;
  name: string;
  description: string | null;
  city: string | null;
  country: string | null;
  product_categories: string[];
  currency: string | null;
  import_enabled: boolean;
  export_enabled: boolean;
  domestic_scope: boolean;
}

/**
 * Fiche publique par slug — `null` = 404 fail-closed (→ `notFound()`).
 *
 * Le backend résout le slug via `TradeProfileResolver` (opt-in `is_public`,
 * slug unique global, société non suspendue/expirée, feature `trade`), puis
 * revalide `is_public` à la lecture : une entreprise dépubliée disparaît de
 * l'annuaire ET de sa fiche, indistinguablement d'un slug inexistant.
 */
export async function getPublicTradeCompany(
  slug: string,
): Promise<PublicTradeCompanyProfile | null> {
  try {
    const response = await fetch(
      `${resolveBackendBaseUrl()}/public/trade/companies/${encodeURIComponent(slug)}`,
      {
        headers: { Accept: 'application/json' },
        next: { revalidate: 60 },
      },
    );

    if (!response.ok) {
      return null;
    }

    const payload = (await response.json()) as { data?: PublicTradeCompanyProfile };
    return payload.data ?? null;
  } catch {
    return null;
  }
}
