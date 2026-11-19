/**
 * Adaptateur réseau — verticale Billets.
 *
 * Seule couture vers le contrat public Travel. La recherche de trajets est
 * servie par `GET /public/travel/marketplace/trips` (throttle `shop-public`,
 * aucune auth). On ne réimplémente ni la réservation ni le paiement : la
 * vitrine présente, les parcours existants vendent.
 */

import { resolveSurfaceApiBaseUrl } from '../../kernel/infrastructure/api-base';

import type { Trip } from '../domain/trip';

/** Forme brute d'un trajet dans le contrat public (sous-ensemble utile). */
interface RawTrip {
  reference?: string;
  id?: number;
  origin?: string | null;
  destination?: string | null;
  departure_at?: string | null;
  arrival_at?: string | null;
  price_minor?: number | null;
  currency?: string | null;
  seats_available?: number | null;
}

/** Traduit un trajet brut vers le modèle de la vitrine. */
export function toTrip(raw: RawTrip): Trip {
  return {
    reference: raw.reference ?? String(raw.id ?? ''),
    // Pas de libellé de repli ici : un trajet sans origine connue doit être
    // signalé comme tel par l'UI (via le catalogue de copie), pas masqué par un
    // mot en dur dans l'adaptateur.
    origin: raw.origin ?? '',
    destination: raw.destination ?? '',
    departureAt: raw.departure_at ?? null,
    arrivalAt: raw.arrival_at ?? null,
    priceMinor: raw.price_minor ?? null,
    currency: raw.currency ?? null,
    seatsAvailable: raw.seats_available ?? null,
  };
}

/**
 * Recherche des trajets publics.
 *
 * `null` = API indisponible : la page affiche un état d'erreur explicite, jamais
 * une liste vide qui laisserait croire qu'aucun trajet n'existe.
 */
export async function searchTrips(params: {
  origin?: string;
  destination?: string;
  page?: number;
}): Promise<readonly Trip[] | null> {
  const query = new URLSearchParams();

  if (params.origin) {
    query.set('origin', params.origin);
  }

  if (params.destination) {
    query.set('destination', params.destination);
  }

  if (params.page && params.page > 1) {
    query.set('page', String(params.page));
  }

  const qs = query.toString();

  try {
    const response = await fetch(
      `${resolveSurfaceApiBaseUrl()}/public/travel/marketplace/trips${qs ? `?${qs}` : ''}`,
      {
        headers: { Accept: 'application/json' },
        next: { revalidate: 60 },
      },
    );

    if (!response.ok) {
      return null;
    }

    const payload = (await response.json()) as { data?: RawTrip[] };
    const items = Array.isArray(payload.data) ? payload.data : [];

    return items.map(toTrip);
  } catch {
    return null;
  }
}
