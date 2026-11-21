/**
 * Adaptateur réseau — verticale Hôtellerie.
 *
 * Seule couture vers `@/lib/hospitality-public-api`. Mapping explicite.
 */

import {
  getPublicStayProperty,
  type PublicStayProperty,
} from '@/lib/hospitality-public-api';
import { searchPublicDiscovery } from '@/lib/public-discovery-api';

import type { Property } from '../domain/property';
import type { PropertyDirectoryEntry } from '../domain/property-directory';

/** Résultat d'un chargement d'annuaire : disponibilité explicite + entrées. */
export interface PropertyDirectoryLoadResult {
  readonly available: boolean;
  readonly entries: readonly PropertyDirectoryEntry[];
  /** Dernière page connue de l'API (#9657). */
  readonly lastPage: number;
}

/**
 * Charge l'annuaire des hébergements publiés (#9618).
 *
 * `?type=hospitality_property` — même contrat public que les fiches.
 */
export async function loadPropertyDirectory(params: {
  q?: string;
  city?: string;
  page?: number;
}): Promise<PropertyDirectoryLoadResult> {
  const result = await searchPublicDiscovery({
    q: params.q,
    city: params.city,
    type: 'hospitality_property',
    page: params.page,
  });

  if (result === null) {
    return { available: false, entries: [], lastPage: 1 };
  }

  return {
    available: true,
    entries: result.items.map((item) => ({
      slug: item.slug,
      name: item.name,
      // L'agrégateur publie le type d'hébergement dans `category`.
      kind: item.category ?? null,
      city: item.city ?? null,
      description: item.description ?? null,
    })),
    lastPage: Math.max(1, result.meta.last_page ?? 1),
  };
}

/** Traduit la fiche publique d'un hébergement vers le modèle de la vitrine. */
export function toProperty(profile: PublicStayProperty): Property {
  return {
    slug: profile.slug,
    name: profile.name,
    kind: profile.type,
    city: profile.city,
    country: profile.country,
    starRating: profile.star_rating,
    amenities: profile.amenities ?? [],
    roomTypes: profile.room_types.map((roomType) => ({
      code: roomType.code,
      name: roomType.name,
      description: roomType.description,
      basePriceMinor: roomType.base_price_minor,
      currency: roomType.currency,
    })),
  };
}

/**
 * Charge un hébergement par slug.
 *
 * `null` = 404 fail-closed (non publié) ou API indisponible.
 */
export async function loadProperty(slug: string): Promise<Property | null> {
  const profile = await getPublicStayProperty(slug);

  return profile === null ? null : toProperty(profile);
}
