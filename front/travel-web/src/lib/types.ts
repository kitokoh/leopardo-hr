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
  agency: { name: string | null };
};

export type MarketplaceSeat = {
  seat_number: number;
  status: "free";
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
