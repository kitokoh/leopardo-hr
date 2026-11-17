/**
 * Cas d'usage — verticale Hôtellerie.
 *
 * Charge un hébergement et prépare son affichage (prix d'appel, étoiles).
 */

import {
  formatMoney,
  fromPrice,
  starLabel,
  type Property,
} from '../domain/property';
import { loadProperty } from '../infrastructure/property-gateway';

/** Hébergement prêt à l'affichage. */
export interface PropertyView {
  readonly property: Property;
  readonly priceLabel: string | null;
  readonly stars: string | null;
}

/** Charge la vitrine d'un hébergement ; `null` si introuvable ou API en panne. */
export async function loadPropertyView(slug: string): Promise<PropertyView | null> {
  const property = await loadProperty(slug);

  if (property === null) {
    return null;
  }

  const price = fromPrice(property);

  return {
    property,
    priceLabel: price === null ? null : formatMoney(price.amountMinor, price.currency),
    stars: starLabel(property.starRating),
  };
}
