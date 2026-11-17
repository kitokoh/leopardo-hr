/**
 * Domaine public — verticale Hôtellerie (Stay).
 *
 * Un **hébergement** vu par le grand public : nom, type, ville, équipements,
 * note en étoiles, types de chambres et prix d'appel. Aucune E/S.
 */

/** Type de chambre publié (prix en unité mineure, devise ISO 4217). */
export interface RoomType {
  readonly code: string;
  readonly name: string;
  readonly description: string | null;
  readonly basePriceMinor: number;
  readonly currency: string;
}

/** Un hébergement tel que la vitrine le présente. */
export interface Property {
  readonly slug: string;
  readonly name: string;
  /** `hotel` | `residence` | `apartment_building` | `guesthouse`. */
  readonly kind: string;
  readonly city: string | null;
  readonly country: string | null;
  readonly starRating: number | null;
  readonly amenities: readonly string[];
  readonly roomTypes: readonly RoomType[];
}

/**
 * Prix d'appel : le moins cher des types de chambre, ou `null` si aucun.
 *
 * Règle du domaine : « à partir de » doit être le vrai minimum, jamais le
 * premier élément de la liste (qui n'est pas triée par prix).
 */
export function fromPrice(property: Property): { amountMinor: number; currency: string } | null {
  if (property.roomTypes.length === 0) {
    return null;
  }

  let cheapest = property.roomTypes[0];

  for (const roomType of property.roomTypes) {
    if (roomType.basePriceMinor < cheapest.basePriceMinor) {
      cheapest = roomType;
    }
  }

  return { amountMinor: cheapest.basePriceMinor, currency: cheapest.currency };
}

/**
 * Formate un montant en unité mineure (centimes) vers un libellé lisible.
 *
 * `null` si le montant n'est pas un entier positif fini : la vitrine n'affiche
 * pas de prix inventé.
 */
export function formatMoney(amountMinor: number | null, currency: string | null): string | null {
  if (amountMinor === null || !Number.isFinite(amountMinor) || amountMinor < 0) {
    return null;
  }

  const amount = amountMinor / 100;
  const formatted = amount.toLocaleString('fr-FR', {
    minimumFractionDigits: amount % 1 === 0 ? 0 : 2,
    maximumFractionDigits: 2,
  });

  return currency ? `${formatted} ${currency}` : formatted;
}

/** Libellé d'étoiles : `null` si non classé. */
export function starLabel(starRating: number | null): string | null {
  if (starRating === null || starRating <= 0) {
    return null;
  }

  return `${'★'.repeat(Math.min(Math.round(starRating), 5))}`;
}
