/**
 * HOSP-008 (#7950) — accès serveur à la vitrine publique HospitalityManager.
 *
 * Rend la ressource publique `GET /api/v1/public/hospitality/properties/{slug}`
 * (HOSP-006 #7948 — contrat spec `docs/specifications/SOLUTION_HOSPITALITY.md`
 * §6) consommée par la page SSR `/stay/{slug}` — même pattern que
 * `restaurants-public-api.ts` (RESTO-903) : appel direct à l'API Laravel
 * depuis un Server Component (aucune auth, DTO public strict, 404
 * fail-closed → null, `is_public=false` → 404 côté API).
 *
 * Côté navigateur (disponibilités, réservation, suivi/annulation), le
 * composant client `StayBookingPanel` passe par `apiFetch` (proxy
 * same-origin `/api/v1`) — pas par ce module.
 */

import { resolveBackendBaseUrl } from '@/lib/backend-url';

/** Type de chambre publié (prix affiché en unité mineure, devise ISO 4217). */
export interface PublicStayRoomType {
  id: number;
  code: string;
  name: string;
  description: string | null;
  base_price_minor: number;
  currency: string;
}

/** Fiche publique d'un établissement publié (`is_public=true`, slug global). */
export interface PublicStayProperty {
  slug: string;
  name: string;
  /** `hotel` | `residence` | `apartment_building` | `guesthouse`. */
  type: string;
  address: string | null;
  city: string | null;
  /** Code pays ISO 3166-1 alpha-2. */
  country: string | null;
  timezone: string | null;
  currency: string | null;
  phone: string | null;
  email: string | null;
  star_rating: number | null;
  amenities: string[] | null;
  latitude: number | null;
  longitude: number | null;
  room_types: PublicStayRoomType[];
}

/** Ligne de disponibilité par type de chambre (miroir du shape interne HOSP-004). */
export interface PublicStayAvailabilityRoomType {
  room_type_id: number;
  code: string;
  name: string;
  capacity: number;
  held: number;
  available: number;
  base_price_minor: number;
  currency: string;
}

/** Réservation publique créée (statut `pending`, expire à +30 min sans confirmation). */
export interface PublicStayReservationResult {
  reference: string;
  /** Code de suivi présenté UNE seule fois (le serveur n'en stocke que le hash). */
  tracking_code: string;
  status: string;
  check_in: string;
  check_out: string;
  expires_at: string | null;
  total_amount_minor: number | null;
  currency: string | null;
  created: boolean;
}

/** Suivi sans compte (`GET /public/hospitality/reservations/{reference}?code=`). */
export interface PublicStayReservationTrack {
  reference: string;
  status: string;
  guest_name: string;
  check_in: string;
  check_out: string;
  expires_at: string | null;
  total_amount_minor: number | null;
  currency: string | null;
  property: { slug: string; name: string } | null;
  room_type: { code: string; name: string } | null;
}

/**
 * Fiche établissement publiée — SSR (metadata + JSON-LD + rendu serveur).
 * 404 / établissement dé-publié / API indisponible → null (fail-closed).
 */
export async function getPublicStayProperty(slug: string): Promise<PublicStayProperty | null> {
  try {
    const response = await fetch(
      `${resolveBackendBaseUrl()}/public/hospitality/properties/${encodeURIComponent(slug)}`,
      {
        headers: { Accept: 'application/json' },
        next: { revalidate: 60 },
      },
    );

    if (!response.ok) {
      return null;
    }

    const payload = (await response.json()) as { data?: PublicStayProperty };
    return payload.data ?? null;
  } catch {
    return null;
  }
}
