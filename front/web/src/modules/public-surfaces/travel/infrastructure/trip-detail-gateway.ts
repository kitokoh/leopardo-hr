/**
 * Adaptateur réseau — verticale Billets : fiche trajet et inventaire de sièges.
 *
 * Repris de `front/travel-web/src/lib/api.ts` (#9628). Complète
 * `trip-gateway.ts` (recherche) avec ce que l'app autonome portait en plus :
 * la fiche d'un trajet et son plan de sièges.
 *
 * ## Contrat de panne
 *
 * `null` = API indisponible ou trajet absent. La page distingue les deux par
 * un `notFound()` sur 404 et un état d'erreur explicite sur panne — jamais une
 * fiche vide qui laisserait croire que le trajet n'existe pas.
 */

import { resolveBackendBaseUrl } from '@/lib/backend-url';

import type { SeatInput, SeatLayoutConfig, SeatStatusValue } from '../domain/seat-layout';

/** Forme brute d'un trajet détaillé. */
interface RawTripDetail {
  id?: number;
  reference?: string;
  origin?: string | null;
  destination?: string | null;
  departure_at?: string | null;
  arrival_at?: string | null;
  price_minor?: number | null;
  currency?: string | null;
  seats_available?: number | null;
  total_seats?: number | null;
  vehicle?: { total_seats?: number | null; seat_layout?: SeatLayoutConfig | null } | null;
  seats?: unknown;
}

/** Trajet détaillé tel que la fiche le présente. */
export interface TripDetail {
  readonly reference: string;
  readonly origin: string;
  readonly destination: string;
  readonly departureAt: string | null;
  readonly arrivalAt: string | null;
  readonly priceMinor: number | null;
  readonly currency: string | null;
  readonly seatsAvailable: number | null;
  readonly totalSeats: number;
  readonly seatLayout: SeatLayoutConfig | null;
  readonly seats: readonly SeatInput[];
}

const SEAT_STATUSES: readonly SeatStatusValue[] = [
  'free',
  'selected',
  'held',
  'reserved',
  'pending_payment',
  'sold',
  'blocked',
  'unavailable',
];

function isSeatStatus(value: unknown): value is SeatStatusValue {
  return typeof value === 'string' && (SEAT_STATUSES as readonly string[]).includes(value);
}

/**
 * Normalise l'inventaire de sièges.
 *
 * Une entrée dont le statut est inconnu est **écartée** : la traiter comme
 * `free` vendrait un siège peut-être déjà pris, la traiter comme `sold`
 * retirerait une place disponible. L'absence est le seul choix honnête.
 */
function normalizeSeats(raw: unknown): SeatInput[] {
  if (!Array.isArray(raw)) {
    return [];
  }

  return raw.flatMap((entry) => {
    if (typeof entry !== 'object' || entry === null) {
      return [];
    }

    const record = entry as { seat_number?: unknown; status?: unknown; booking_reference?: unknown };

    if (typeof record.seat_number !== 'number' || !isSeatStatus(record.status)) {
      return [];
    }

    return [
      {
        seat_number: record.seat_number,
        status: record.status,
        booking_reference:
          typeof record.booking_reference === 'string' ? record.booking_reference : null,
      },
    ];
  });
}

/** Traduit un trajet détaillé brut vers le modèle de la vitrine. */
export function toTripDetail(raw: RawTripDetail): TripDetail {
  const totalSeats =
    typeof raw.total_seats === 'number'
      ? raw.total_seats
      : typeof raw.vehicle?.total_seats === 'number'
        ? raw.vehicle.total_seats
        : 0;

  return {
    reference: raw.reference ?? String(raw.id ?? ''),
    origin: raw.origin ?? '',
    destination: raw.destination ?? '',
    departureAt: raw.departure_at ?? null,
    arrivalAt: raw.arrival_at ?? null,
    priceMinor: raw.price_minor ?? null,
    currency: raw.currency ?? null,
    seatsAvailable: raw.seats_available ?? null,
    totalSeats,
    seatLayout: raw.vehicle?.seat_layout ?? null,
    seats: normalizeSeats(raw.seats),
  };
}

/** Charge la fiche d'un trajet ; `null` si absent ou API indisponible. */
export async function loadTripDetail(id: string | number): Promise<TripDetail | null> {
  try {
    const response = await fetch(
      `${resolveBackendBaseUrl()}/public/travel/marketplace/trips/${encodeURIComponent(String(id))}`,
      { headers: { Accept: 'application/json' }, next: { revalidate: 30 } },
    );

    if (!response.ok) {
      return null;
    }

    const payload: unknown = await response.json();
    const record = typeof payload === 'object' && payload !== null ? (payload as Record<string, unknown>) : {};
    const raw = (typeof record.data === 'object' && record.data !== null
      ? record.data
      : record) as RawTripDetail;

    return toTripDetail(raw);
  } catch {
    return null;
  }
}
