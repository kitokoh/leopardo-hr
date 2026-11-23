/**
 * Adaptateur réseau — verticale Billets : réservation et suivi.
 *
 * Repris de `front/travel-web/src/lib/api.ts` (#9628).
 *
 * ## Ce que la vitrine ne fait pas
 *
 * Elle ne vend pas : elle **présente** et délègue au contrat public existant.
 * La réservation, le suivi et l'annulation restent servis par
 * `/public/travel/marketplace/*` — la vitrine ne réimplémente ni le calcul de
 * prix ni la validation de disponibilité, qui sont des règles serveur.
 */

import { resolveSurfaceApiBaseUrl } from '../../kernel/infrastructure/api-base';

import type { BookingPayload, CreatedBooking } from '../domain/booking';

const MARKETPLACE = 'public/travel/marketplace';

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null && !Array.isArray(value);
}

function toNumber(value: unknown, fallback: number): number {
  return typeof value === 'number' && Number.isFinite(value) ? value : fallback;
}

/** Erreur HTTP portant le statut — l'appelant distingue 404 de 422. */
export class TravelApiError extends Error {
  readonly status: number;

  constructor(message: string, status: number) {
    super(message);
    this.name = 'TravelApiError';
    this.status = status;
  }
}

async function request<T>(path: string, init: RequestInit = {}): Promise<T> {
  const response = await fetch(`${resolveSurfaceApiBaseUrl()}/${path}`, {
    ...init,
    headers: {
      Accept: 'application/json',
      ...(init.body ? { 'Content-Type': 'application/json' } : {}),
      ...init.headers,
    },
    // Session client optionnelle : si le cookie est présent, la réservation est
    // rattachée au compte ; le checkout invité reste possible sans session.
    credentials: 'include',
    cache: 'no-store',
  });

  const text = await response.text();
  let payload: unknown = null;

  if (text) {
    try {
      payload = JSON.parse(text) as unknown;
    } catch {
      payload = null;
    }
  }

  if (!response.ok) {
    const message =
      isRecord(payload) && typeof payload.message === 'string'
        ? payload.message
        : `HTTP ${response.status}`;

    throw new TravelApiError(message, response.status);
  }

  return payload as T;
}

/**
 * Crée une réservation.
 *
 * Lève sur échec : un `null` silencieux ferait croire à une réservation
 * aboutie alors qu'aucun billet n'existe.
 */
export async function createBooking(payload: BookingPayload): Promise<CreatedBooking> {
  const response = await request<unknown>(`${MARKETPLACE}/bookings`, {
    method: 'POST',
    body: JSON.stringify(payload),
  });

  const record = isRecord(response) ? response : {};
  const raw = isRecord(record.data) ? record.data : record;
  const agency = isRecord(record.agency) ? record.agency : {};

  return {
    reference: typeof raw.reference === 'string' ? raw.reference : '',
    status: typeof raw.status === 'string' ? raw.status : 'pending',
    passengerCount: toNumber(raw.passenger_count, 0),
    totalMinor: toNumber(raw.total_amount_minor, 0),
    currency: typeof raw.currency === 'string' ? raw.currency : 'EUR',
    paymentStatus: typeof raw.payment_status === 'string' ? raw.payment_status : 'pending',
    expiresAt: typeof raw.expires_at === 'string' ? raw.expires_at : null,
    agencyName: typeof agency.name === 'string' ? agency.name : null,
  };
}

/**
 * Moyens de paiement proposés au voyageur en ligne.
 *
 * `cash` est volontairement exclu : il n'a de sens qu'au guichet, où un agent
 * encaisse physiquement. L'API accepte la valeur, la vitrine ne la propose pas.
 */
export const ONLINE_PAYMENT_PROVIDERS = ['momo', 'card', 'pvit'] as const;

export type OnlinePaymentProvider = (typeof ONLINE_PAYMENT_PROVIDERS)[number];

/** Paiement initié côté serveur. */
export interface InitiatedPayment {
  readonly reference: string;
  readonly providerReference: string | null;
  readonly status: string;
}

/**
 * Initie le paiement en ligne d'une réservation.
 *
 * Lève sur échec : un `null` silencieux laisserait croire à un paiement engagé
 * alors qu'aucune transaction n'existe. L'idempotence est portée par
 * `idempotencyKey` côté serveur — un rejeu réseau renvoie le MÊME paiement.
 */
export async function initiatePayment(params: {
  readonly bookingReference: string;
  readonly provider: OnlinePaymentProvider;
  readonly idempotencyKey: string;
}): Promise<InitiatedPayment> {
  const response = await request<unknown>(`${MARKETPLACE}/payments/initiate`, {
    method: 'POST',
    body: JSON.stringify({
      booking_reference: params.bookingReference,
      provider_code: params.provider,
      idempotency_key: params.idempotencyKey,
    }),
  });

  const record = isRecord(response) ? response : {};
  const raw = isRecord(record.data) ? record.data : record;

  return {
    reference: typeof raw.reference === 'string' ? raw.reference : '',
    providerReference:
      typeof raw.provider_reference === 'string' ? raw.provider_reference : null,
    status: typeof raw.status === 'string' ? raw.status : 'pending',
  };
}

/** E-billet téléchargeable (URL signée à durée limitée). */
export interface TicketPdfLink {
  readonly ticketNumber: string;
  readonly pdfUrl: string;
  readonly expiresInMinutes: number;
}

/**
 * Demande l'URL signée du PDF d'un billet.
 *
 * Le code de validation est le secret partagé du billet : il est requis à
 * chaque appel et vérifié côté API (403 sinon). L'URL renvoyée expire
 * (`expires_in_minutes`) — elle n'est jamais mise en cache ni stockée.
 */
export async function fetchTicketPdf(
  ticketId: number,
  code: string,
): Promise<TicketPdfLink> {
  const response = await request<unknown>(
    `${MARKETPLACE}/tickets/${ticketId}/pdf?code=${encodeURIComponent(code)}`,
  );

  const record = isRecord(response) ? response : {};
  const raw = isRecord(record.data) ? record.data : record;

  return {
    ticketNumber: typeof raw.ticket_number === 'string' ? raw.ticket_number : '',
    pdfUrl: typeof raw.pdf_url === 'string' ? raw.pdf_url : '',
    expiresInMinutes: toNumber(raw.expires_in_minutes, 30),
  };
}

/** Réservation suivie (payload public minimal, sans PII). */
export interface TrackedBooking {
  readonly reference: string;
  readonly status: string;
  readonly paymentStatus: string;
  readonly passengerCount: number;
  readonly totalMinor: number;
  readonly currency: string;
  readonly expiresAt: string | null;
  readonly tripCode: string | null;
  readonly departureDate: string | null;
  readonly departureTime: string | null;
  readonly tickets: readonly { readonly id: number; readonly number: string; readonly status: string }[];
}

/**
 * Suit une réservation par référence + code.
 *
 * `null` = introuvable ou API indisponible. L'API répond un 404 uniforme pour
 * empêcher l'énumération des références : on ne distingue donc pas les deux
 * cas, et l'UI affiche un message neutre.
 */
export async function trackBooking(
  reference: string,
  code: string,
): Promise<TrackedBooking | null> {
  try {
    const response = await request<unknown>(
      `${MARKETPLACE}/bookings/${encodeURIComponent(reference)}?code=${encodeURIComponent(code)}`,
    );

    const record = isRecord(response) ? response : {};
    const raw = isRecord(record.data) ? record.data : record;
    const trip = isRecord(raw.trip) ? raw.trip : {};
    const tickets = Array.isArray(raw.tickets) ? raw.tickets : [];

    return {
      reference: typeof raw.reference === 'string' ? raw.reference : reference,
      status: typeof raw.status === 'string' ? raw.status : 'pending',
      paymentStatus: typeof raw.payment_status === 'string' ? raw.payment_status : 'pending',
      passengerCount: toNumber(raw.passenger_count, 0),
      totalMinor: toNumber(raw.total_amount_minor, 0),
      currency: typeof raw.currency === 'string' ? raw.currency : 'EUR',
      expiresAt: typeof raw.expires_at === 'string' ? raw.expires_at : null,
      tripCode: typeof trip.code === 'string' ? trip.code : null,
      departureDate: typeof trip.departure_date === 'string' ? trip.departure_date : null,
      departureTime: typeof trip.departure_time === 'string' ? trip.departure_time : null,
      tickets: tickets.flatMap((entry) => {
        if (!isRecord(entry)) {
          return [];
        }

        return [
          {
            id: toNumber(entry.id, 0),
            number: typeof entry.ticket_number === 'string' ? entry.ticket_number : '',
            status: typeof entry.status === 'string' ? entry.status : '',
          },
        ];
      }),
    };
  } catch {
    return null;
  }
}

/**
 * Annule une réservation.
 *
 * Lève sur échec : une annulation refusée (délai dépassé, déjà annulée) doit
 * être dite à l'utilisateur, pas avalée.
 */
export async function cancelBooking(
  reference: string,
  code: string,
  reason: string,
): Promise<TrackedBooking> {
  const response = await request<unknown>(
    `${MARKETPLACE}/bookings/${encodeURIComponent(reference)}/cancel`,
    { method: 'POST', body: JSON.stringify({ code, reason }) },
  );

  const record = isRecord(response) ? response : {};
  const raw = isRecord(record.data) ? record.data : record;

  return {
    reference: typeof raw.reference === 'string' ? raw.reference : reference,
    status: typeof raw.status === 'string' ? raw.status : 'cancelled',
    paymentStatus: typeof raw.payment_status === 'string' ? raw.payment_status : 'pending',
    passengerCount: toNumber(raw.passenger_count, 0),
    totalMinor: toNumber(raw.total_amount_minor, 0),
    currency: typeof raw.currency === 'string' ? raw.currency : 'EUR',
    expiresAt: typeof raw.expires_at === 'string' ? raw.expires_at : null,
    tripCode: null,
    departureDate: null,
    departureTime: null,
    tickets: [],
  };
}
