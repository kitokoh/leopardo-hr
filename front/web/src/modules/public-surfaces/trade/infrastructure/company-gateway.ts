/**
 * Adaptateur réseau — verticale B2B.
 *
 * Seule couture vers `@/lib/trade-public-api`. Mapping explicite.
 */

import {
  getPublicTradeCompany,
  type PublicTradeCompanyProfile,
} from '@/lib/trade-public-api';

import type { Company } from '../domain/company';

/** Traduit la fiche publique d'une entreprise vers le modèle de la vitrine. */
export function toCompany(profile: PublicTradeCompanyProfile): Company {
  return {
    slug: profile.slug,
    name: profile.name,
    description: profile.description,
    city: profile.city,
    country: profile.country,
    productCategories: profile.product_categories ?? [],
    currency: profile.currency,
    imports: profile.import_enabled,
    exports: profile.export_enabled,
    domestic: profile.domestic_scope,
  };
}

/**
 * Charge une entreprise par slug.
 *
 * `null` = 404 fail-closed (non publiée) ou API indisponible.
 */
export async function loadCompany(slug: string): Promise<Company | null> {
  const profile = await getPublicTradeCompany(slug);

  return profile === null ? null : toCompany(profile);
}
