/**
 * Adaptateur réseau — verticale Billets : compte voyageur.
 *
 * Repris de `front/travel-web/src/lib/api.ts` (`fetchMyBookings`, #7739).
 *
 * ## Vue cross-agences
 *
 * Le compte voyageur est le seul endroit où les réservations de plusieurs
 * agences se rencontrent. Le bornage au compte est fait **côté backend** : la
 * vitrine ne filtre rien elle-même, elle affiche ce que l'API rend.
 */

import { resolveBackendBaseUrl } from '@/lib/backend-url';

const ACCOUNT = 'public/travel/marketplace/account';

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null && !Array.isArray(value);
}

function toNumber(value: unknown, fallback: number): number {
  return typeof value === 'number' && Number.isFinite(value) ? value : fallback;
}

function toText(value: unknown): string | null {
  return typeof value === 'string' && value !== '' ? value : null;
}

/** Une réservation du compte voyageur. */
export interface CustomerBooking {
  readonly reference: string;
  readonly status: string;
  readonly paymentStatus: string;
  readonly passengerCount: number;
  readonly totalMinor: number;
  readonly currency: string;
  readonly createdAt: string | null;
  readonly agencyName: string | null;
  readonly originCity: string | null;
  readonly destinationCity: string | null;
  readonly departureDate: string | null;
  readonly departureTime: string | null;
  readonly tickets: readonly string[];
}

/**
 * Réservations du voyageur connecté.
 *
 * `null` = API indisponible ou session expirée. L'appelant distingue les deux
 * par le résultat de `restoreSession` ; ici on ne prétend pas savoir.
 */
export async function fetchMyBookings(): Promise<CustomerBooking[] | null> {
  try {
    const response = await fetch(`${resolveBackendBaseUrl()}/${ACCOUNT}/bookings`, {
      headers: { Accept: 'application/json' },
      // Session par cookie HttpOnly : sans `include`, l'API répond 401.
      credentials: 'include',
      cache: 'no-store',
    });

    if (!response.ok) {
      return null;
    }

    const payload: unknown = await response.json();
    const record = isRecord(payload) ? payload : {};
    const data = Array.isArray(record.data) ? record.data : [];

    return data.flatMap((entry) => {
      if (!isRecord(entry)) {
        return [];
      }

      const trip = isRecord(entry.trip) ? entry.trip : {};
      const agency = isRecord(entry.agency) ? entry.agency : {};
      const tickets = Array.isArray(entry.tickets) ? entry.tickets : [];

      return [
        {
          reference: typeof entry.reference === 'string' ? entry.reference : '',
          status: typeof entry.status === 'string' ? entry.status : 'pending',
          paymentStatus:
            typeof entry.payment_status === 'string' ? entry.payment_status : 'pending',
          passengerCount: toNumber(entry.passenger_count, 0),
          totalMinor: toNumber(entry.total_amount_minor, 0),
          currency: typeof entry.currency === 'string' ? entry.currency : 'EUR',
          createdAt: toText(entry.created_at),
          agencyName: toText(agency.name),
          originCity: toText(trip.origin_city),
          destinationCity: toText(trip.destination_city),
          departureDate: toText(trip.departure_date),
          departureTime: toText(trip.departure_time),
          tickets: tickets.flatMap((ticket) =>
            isRecord(ticket) && typeof ticket.ticket_number === 'string'
              ? [ticket.ticket_number]
              : [],
          ),
        },
      ];
    });
  } catch {
    return null;
  }
}
