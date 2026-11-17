/**
 * Adaptateur réseau — verticale Hôtellerie.
 *
 * Seule couture vers `@/lib/hospitality-public-api`. Mapping explicite.
 */

import {
  getPublicStayProperty,
  type PublicStayProperty,
} from '@/lib/hospitality-public-api';

import type { Property } from '../domain/property';

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
