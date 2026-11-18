/**
 * Adaptateur réseau — verticale B2B.
 *
 * Seule couture vers `@/lib/trade-public-api`. Mapping explicite.
 */

import {
  getPublicTradeCompany,
  type PublicTradeCompanyProfile,
} from '@/lib/trade-public-api';
import { searchPublicDiscovery } from '@/lib/public-discovery-api';

import type { Company } from '../domain/company';
import type { CompanyDirectoryEntry } from '../domain/company-directory';

/** Résultat d'un chargement d'annuaire : disponibilité explicite + entrées. */
export interface CompanyDirectoryLoadResult {
  readonly available: boolean;
  readonly entries: readonly CompanyDirectoryEntry[];
}

/**
 * Charge l'annuaire des entreprises publiées (#9618).
 *
 * `?type=trade_company` — même contrat public que les fiches.
 *
 * ## Limite assumée
 *
 * L'agrégateur ne publie pas les directions (import/export/domestique) au
 * niveau liste : elles ne sont connues qu'en fiche. On ne les devine donc pas
 * ici — `imports`/`exports`/`domestic` restent `false` dans l'entrée, et la
 * carte d'annuaire n'affiche pas de badge de direction. Afficher un badge
 * inventé serait pire que de ne rien afficher.
 */
export async function loadCompanyDirectory(params: {
  q?: string;
  city?: string;
  page?: number;
}): Promise<CompanyDirectoryLoadResult> {
  const result = await searchPublicDiscovery({
    q: params.q,
    city: params.city,
    type: 'trade_company',
    page: params.page,
  });

  if (result === null) {
    return { available: false, entries: [] };
  }

  return {
    available: true,
    entries: result.items.map((item) => ({
      slug: item.slug,
      name: item.name,
      city: item.city ?? null,
      description: item.description ?? null,
      productCategories: item.category ? [item.category] : [],
      imports: false,
      exports: false,
      domestic: false,
    })),
  };
}

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
