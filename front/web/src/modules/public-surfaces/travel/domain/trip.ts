/**
 * Domaine public — verticale Billets (Travel).
 *
 * Un **trajet** vendu au grand public : origine, destination, départ, arrivée,
 * prix, places restantes. Aucune E/S.
 *
 * ## Note de périmètre
 *
 * Le contrat public de la verticale Travel est servi par
 * `/public/travel/shop/*` et `/public/travel/marketplace/*` (recherche de
 * trajets, réservation, suivi). La vitrine ne réimplémente pas ces parcours :
 * elle présente les trajets et renvoie vers les parcours existants.
 */

/** Un trajet tel que la vitrine le présente. */
export interface Trip {
  readonly reference: string;
  readonly origin: string;
  readonly destination: string;
  readonly departureAt: string | null;
  readonly arrivalAt: string | null;
  readonly priceMinor: number | null;
  readonly currency: string | null;
  readonly seatsAvailable: number | null;
}

/**
 * Vrai si le trajet est encore réservable.
 *
 * Règle du domaine : un trajet sans places connues n'est pas déclaré complet —
 * on ne cache pas une offre par prudence excessive, mais on n'invente pas non
 * plus de disponibilité.
 */
export function isBookable(trip: Trip): boolean {
  return trip.seatsAvailable === null || trip.seatsAvailable > 0;
}

/**
 * Libellé de places : `null` si inconnu (jamais « 0 place » par défaut).
 *
 * Les mots viennent du catalogue de copie (4 locales) : le domaine ne porte que
 * la règle de pluriel et le cas complet.
 */
export function seatsLabel(
  seatsAvailable: number | null,
  words: { place: string; places: string; soldOut: string },
): string | null {
  if (seatsAvailable === null || !Number.isFinite(seatsAvailable)) {
    return null;
  }

  if (seatsAvailable <= 0) {
    return words.soldOut;
  }

  return `${seatsAvailable} ${seatsAvailable > 1 ? words.places : words.place}`;
}

/**
 * Formate un prix en unité mineure ; `null` si inconnu.
 */
export function formatTripPrice(
  priceMinor: number | null,
  currency: string | null,
): string | null {
  if (priceMinor === null || !Number.isFinite(priceMinor) || priceMinor < 0) {
    return null;
  }

  const amount = priceMinor / 100;
  const formatted = amount.toLocaleString('fr-FR', {
    minimumFractionDigits: amount % 1 === 0 ? 0 : 2,
    maximumFractionDigits: 2,
  });

  return currency ? `${formatted} ${currency}` : formatted;
}

/** Itinéraire lisible : « Lyon → Paris ». */
export function tripRoute(trip: Trip): string {
  return `${trip.origin} → ${trip.destination}`;
}

/** Vrai si l'itinéraire est complet (origine et destination connues). */
export function hasRoute(trip: Trip): boolean {
  return trip.origin.trim() !== '' && trip.destination.trim() !== '';
}
