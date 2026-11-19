/**
 * Adaptateur réseau — verticale Mobilité : courses invitées.
 *
 * Repris de `front/travel-web/src/lib/api.ts` (#9628).
 *
 * ## Surface invitée, sans credentials
 *
 * Contrairement au compte acheteur, la surface VTC publique ne porte **aucune**
 * session : les appels partent sans `credentials`, en fail-closed. Un cookie
 * de session marchande n'a rien à faire sur une demande de course.
 */

import { resolveSurfaceApiBaseUrl } from '../../kernel/infrastructure/api-base';

import type { CreatedGuestRide, GeoPoint, GuestRide, RideEstimate, RideStatus } from '../domain/ride';
import { isRideStatus } from '../domain/ride';

const VTC = 'public/vtc/marketplace';

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null && !Array.isArray(value);
}

function toNumber(value: unknown): number | null {
  return typeof value === 'number' && Number.isFinite(value) ? value : null;
}

function toText(value: unknown): string | null {
  return typeof value === 'string' && value !== '' ? value : null;
}

/** Erreur HTTP portant le statut. */
export class VtcApiError extends Error {
  readonly status: number;

  constructor(message: string, status: number) {
    super(message);
    this.name = 'VtcApiError';
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
    // Surface invitée : aucun cookie de session n'est envoyé.
    credentials: 'omit',
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

    throw new VtcApiError(message, response.status);
  }

  return payload as T;
}

function toGuestRide(raw: unknown): GuestRide {
  const record = isRecord(raw) ? raw : {};

  return {
    reference: typeof record.reference === 'string' ? record.reference : '',
    status: isRideStatus(record.status) ? record.status : 'requested',
    pickupAddress: toText(record.pickup_address),
    dropoffAddress: toText(record.dropoff_address),
    estimatedDurationS: toNumber(record.estimated_duration_s),
    estimatedPriceMinor: toNumber(record.estimated_price_minor),
    currency: toText(record.currency),
    requestedAt: toText(record.requested_at),
  };
}

/**
 * Devis public d'une course.
 *
 * Lève sur échec : un devis silencieusement nul ferait afficher « prix à
 * confirmer » alors que l'opérateur a peut-être refusé la demande.
 */
export async function estimateRide(params: {
  operator: string;
  pickup: GeoPoint;
  dropoff: GeoPoint;
}): Promise<RideEstimate> {
  const response = await request<unknown>(`${VTC}/estimate`, {
    method: 'POST',
    body: JSON.stringify(params),
  });

  const record = isRecord(response) ? response : {};
  const raw = isRecord(record.data) ? record.data : record;

  return {
    distanceM: toNumber(raw.distance_m),
    roadDistanceM: toNumber(raw.road_distance_m),
    durationS: toNumber(raw.duration_s),
    priceMinor: toNumber(raw.price_minor),
    currency: toText(raw.currency),
  };
}

/**
 * Crée une course invitée.
 *
 * L'idempotence est portée par la clé générée côté client et **conservée
 * jusqu'au succès** : un double tap ou un retry réseau renvoie la même course
 * (200, sans re-présenter le code), jamais un doublon.
 */
export async function createRide(params: {
  operator: string;
  idempotencyKey: string;
  pickup: GeoPoint;
  dropoff: GeoPoint;
  pickupAddress?: string;
  dropoffAddress?: string;
  passengerName: string;
  passengerPhone: string;
}): Promise<CreatedGuestRide> {
  const response = await request<unknown>(`${VTC}/rides`, {
    method: 'POST',
    body: JSON.stringify({
      operator: params.operator,
      idempotency_key: params.idempotencyKey,
      pickup: params.pickup,
      dropoff: params.dropoff,
      ...(params.pickupAddress !== undefined && params.pickupAddress.trim() !== ''
        ? { pickup_address: params.pickupAddress.trim() }
        : {}),
      ...(params.dropoffAddress !== undefined && params.dropoffAddress.trim() !== ''
        ? { dropoff_address: params.dropoffAddress.trim() }
        : {}),
      passenger_name: params.passengerName.trim(),
      passenger_phone: params.passengerPhone.trim(),
    }),
  });

  const record = isRecord(response) ? response : {};
  const raw = isRecord(record.data) ? record.data : record;

  return {
    ...toGuestRide(raw),
    trackingCode: toText(raw.tracking_code),
  };
}

/**
 * Suit une course par référence + code.
 *
 * `null` = introuvable ou API indisponible. L'API répond un 404 uniforme pour
 * empêcher l'énumération : on ne distingue pas les deux cas.
 */
export async function trackRide(reference: string, code: string): Promise<GuestRide | null> {
  try {
    const response = await request<unknown>(
      `${VTC}/rides/${encodeURIComponent(reference)}?code=${encodeURIComponent(code)}`,
    );

    const record = isRecord(response) ? response : {};

    return toGuestRide(isRecord(record.data) ? record.data : record);
  } catch {
    return null;
  }
}

/** Statut lisible d'une course — libellé français, jamais le code brut. */
export function rideStatusLabel(status: RideStatus): string {
  const labels: Record<RideStatus, string> = {
    requested: 'Demande envoyée',
    dispatching: 'Recherche d’un chauffeur',
    accepted: 'Chauffeur en route',
    arrived: 'Chauffeur sur place',
    in_progress: 'Course en cours',
    completed: 'Course terminée',
    expired: 'Demande expirée',
    cancelled: 'Course annulée',
  };

  return labels[status];
}
