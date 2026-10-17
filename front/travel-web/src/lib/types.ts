/**
 * Contrats des endpoints publics marketplace (issue #7737) — miroir TypeScript
 * des payloads de `TravelMarketplaceController` et `TravelBookingResource`.
 */

export type MarketplaceCity = {
  id: number;
  name: string;
  country_iso2: string;
  region: string | null;
};

/**
 * #8645 — disposition physique des sièges exposée par l'API.
 *
 * Purement géométrique : nombre de colonnes de sièges et position du couloir.
 * `is_configured` vaut `false` quand la valeur a été déduite de la capacité
 * (véhicule non configuré) et non lue sur le véhicule.
 */
export type SeatLayoutPayload = {
  seats_per_row: number;
  aisle_after: number | null;
  is_configured: boolean;
};

export type MarketplaceCityRef = {
  name: string;
  country_iso2: string;
} | null;

export type TripPrice = {
  class_id: number;
  adult_price_minor: number;
  child_price_minor: number;
  currency: string;
};

export type MarketplaceTrip = {
  id: number;
  code: string;
  departure_date: string;
  departure_time: string | null;
  arrival_date: string;
  arrival_time: string | null;
  means_of_transport: string | null;
  total_seats: number;
  available_seats: number;
  origin_city: MarketplaceCityRef;
  destination_city: MarketplaceCityRef;
  prices: TripPrice[];
  price_from_minor: number | null;
  currency: string | null;
  /**
   * #8645 — disposition PHYSIQUE des sièges du véhicule affecté.
   *
   * `null` quand le trajet n'a pas de véhicule, ou quand le véhicule n'a
   * jamais été configuré : le plan applique alors le repli par capacité.
   * `is_configured` distingue une géométrie LUE d'une géométrie DÉDUITE.
   */
  seat_layout?: SeatLayoutPayload | null;
  agency: { name: string | null };
};

/**
 * #8619 — Un siège de l'inventaire réel du trajet.
 *
 * L'API publique expose désormais l'inventaire COMPLET : `free` (sélectionnable)
 * et les états occupés (`reserved`, `sold`, `blocked`, `unavailable`), afin que
 * le plan de sièges représente la disposition réelle du véhicule.
 *
 * Aucune donnée personnelle ni référence de réservation n'est exposée côté
 * public : le type ne porte donc que le numéro et l'état.
 */
export type MarketplaceSeatStatus =
  | "free"
  | "reserved"
  | "sold"
  | "blocked"
  | "unavailable";

export type MarketplaceSeat = {
  seat_number: number;
  status: MarketplaceSeatStatus;
};

export type MarketplaceTripDetail = MarketplaceTrip & {
  seats: MarketplaceSeat[];
};

export type SearchMeta = {
  current_page: number;
  per_page: number;
  total: number;
  last_page: number;
};

export type AgeCategory = "adult" | "child" | "infant";

export type BookingPassengerInput = {
  full_name: string;
  age_category: AgeCategory;
  class_id: number;
  seat_number?: number | null;
};

export type BookingInput = {
  trip_id: number;
  idempotency_key: string;
  contact_email?: string;
  contact_phone?: string;
  notify_consent?: boolean;
  passengers: BookingPassengerInput[];
};

export type BookingTicket = {
  id: number;
  ticket_number: string;
  status?: string;
  passenger_id?: number;
};

export type Booking = {
  id: number;
  reference: string;
  trip_id: number;
  status: string;
  passenger_count: number;
  total_amount_minor: number;
  currency: string;
  booking_source: string;
  payment_status: string;
  expires_at: string | null;
  contact_email?: string | null;
  contact_phone?: string | null;
  tickets?: BookingTicket[];
};

export type BookingResponse = {
  data: Booking;
  agency?: { name: string | null };
};

/**
 * Suivi public par référence + code de validation (#7395) — payload minimal
 * sans PII renvoyé par `TravelPublicShopController::publicPayload`.
 */
export type TrackedBooking = {
  reference: string;
  status: string;
  payment_status: string;
  passenger_count: number;
  total_amount_minor: number;
  currency: string;
  expires_at?: string | null;
  trip?: {
    id?: number;
    code?: string;
    departure_date?: string;
    departure_time?: string | null;
  } | null;
  tickets?: BookingTicket[];
  [key: string]: unknown;
};

/**
 * Comptes clients grand public (issue #7739) — miroir TypeScript des
 * payloads de `TravelCustomerAccountController` (guard Sanctum dédié
 * `travel_customer`, jamais le guard employés).
 */
export type CustomerAccount = {
  id: number;
  name: string;
  email: string;
  phone: string | null;
  created_at: string | null;
};

export type AccountAuthPayload = {
  account: CustomerAccount;
  // #7841 — le token Sanctum n'atteint plus le navigateur : il est posé en
  // cookie httpOnly par les route handlers login/register et retiré du
  // payload avant de répondre au client.
  claimed_bookings?: number;
};

export type CustomerBookingTicket = {
  id: number;
  ticket_number: string;
  status: string;
};

export type CustomerBooking = {
  reference: string;
  status: string;
  payment_status: string;
  passenger_count: number;
  total_amount_minor: number;
  currency: string;
  created_at: string | null;
  expires_at: string | null;
  trip: {
    code: string;
    departure_date: string;
    departure_time: string | null;
    origin_city: string | null;
    destination_city: string | null;
  } | null;
  tickets: CustomerBookingTicket[];
  agency: { name: string | null };
};

// ── Marketplace publique VTC/taxi (#8423, M-30) ────────────────────────────
// Miroir TypeScript des DTO publics de `VtcMarketplaceController`
// (`/api/v1/public/vtc/marketplace/*`) — aucun identifiant interne, jamais
// de position chauffeur sur la surface invité.

/** Opérateur VTC opt-in publié dans l'annuaire public (identité publique). */
export type VtcOperator = {
  slug: string;
  name: string | null;
  city: string | null;
  country: string | null;
  currency: string | null;
};

/** Point WGS 84 (validation stricte côté API : lat ∈ [-90,90], lng ∈ [-180,180]). */
export type VtcGeoPointInput = {
  lat: number;
  lng: number;
};

/** Devis public — `price_minor` vaut null quand l'opérateur n'a pas de grille ;
 * `road_distance_m` (#8464) est la distance routière, base de tarification du prix. */
export type VtcEstimate = {
  distance_m: number | null;
  road_distance_m: number | null;
  duration_s: number | null;
  price_minor: number | null;
  currency: string | null;
};

/** Cycle de vie d'une course VTC (miroir de `VtcRideStatus`). */
export type VtcRideStatusValue =
  | "requested"
  | "dispatching"
  | "accepted"
  | "arrived"
  | "in_progress"
  | "completed"
  | "expired"
  | "cancelled";

/** Course invitée — DTO strict de création et de suivi. */
export type VtcGuestRide = {
  reference: string;
  status: VtcRideStatusValue;
  pickup_address: string | null;
  dropoff_address: string | null;
  estimated_duration_s: number | null;
  estimated_price_minor: number | null;
  currency: string | null;
  requested_at: string | null;
};

/** Création 201 : `tracking_code` présenté UNE SEULE FOIS (absent au rejeu 200). */
export type VtcGuestRideCreated = VtcGuestRide & {
  tracking_code?: string;
};

/** Suivi invité par référence + code — jamais de position chauffeur. */
export type VtcRideTracking = VtcGuestRide;
