import { api, unwrap, unwrapList } from './client';
// Import local (en plus du re-export plus bas) : un `export { x } from` ne
// cree pas de liaison utilisable dans ce module.
import { fetchPharmacyProducts } from './verticals';

/**
 * API d'ECRITURE des verticales metier.
 *
 * Chaque fonction appelle l'endpoint REEL du depot `leopardo-hr`
 * (`api/routes/modules/*.php`) avec le payload EXACT attendu par la
 * FormRequest correspondante. Les noms de champs ne sont pas inventes :
 * ils sont recopies des classes `app/Modules/<Module>/.../Requests/*.php`.
 *
 * Regles generales respectees :
 *  - les montants monetaires sont en MINEURS (`*_minor`) la ou l'API
 *    l'exige (restaurant, retail, hospitality, trade) ;
 *  - les dates sont au format `YYYY-MM-DD`, les horaires `HH:mm` ;
 *  - les transitions d'etat passent par les endpoints dedies
 *    (`/confirm`, `/check-in`, `/publish`…) et non par un PATCH generique ;
 *  - les operations sensibles a l'idempotence recoivent une cle
 *    (`idempotency_key`) pour qu'un rejeu ne cree pas de doublon.
 */

// ─────────────────────────────────────────────────────────────────────────────
// Utilitaires
// ─────────────────────────────────────────────────────────────────────────────

/** Convertit une saisie « 12,50 » ou « 12.50 » en montant mineur (1250). */
export function toMinor(value: unknown): number {
  if (value === null || value === undefined || value === '') return 0;
  const normalized = String(value).replace(/\s/g, '').replace(',', '.');
  const numeric = Number(normalized);
  if (Number.isNaN(numeric)) return 0;
  return Math.round(numeric * 100);
}

/** Convertit un montant mineur en saisie lisible (1250 -> "12.50"). */
export function fromMinor(value: unknown): string {
  const numeric = Number(value ?? 0);
  if (Number.isNaN(numeric)) return '0.00';
  return (numeric / 100).toFixed(2);
}

/**
 * Cle d'idempotence. `crypto.randomUUID` n'existe pas sur toutes les
 * versions d'Expo Go : on retombe sur un UUID v4 construit a la main.
 */
export function newIdempotencyKey(): string {
  const globalCrypto = (globalThis as { crypto?: { randomUUID?: () => string } }).crypto;
  if (globalCrypto?.randomUUID) return globalCrypto.randomUUID();

  const hex = '0123456789abcdef';
  let out = '';
  for (let i = 0; i < 36; i += 1) {
    if (i === 8 || i === 13 || i === 18 || i === 23) out += '-';
    else if (i === 14) out += '4';
    else if (i === 19) out += hex[(Math.random() * 4) | 8];
    else out += hex[(Math.random() * 16) | 0];
  }
  return out;
}

/** Date du jour au format `YYYY-MM-DD` (fuseau local). */
export function todayIso(): string {
  const now = new Date();
  const month = `${now.getMonth() + 1}`.padStart(2, '0');
  const day = `${now.getDate()}`.padStart(2, '0');
  return `${now.getFullYear()}-${month}-${day}`;
}

/** Retire les cles vides d'un payload (l'API refuse `null` sur certains champs). */
function compact<T extends Record<string, unknown>>(payload: T): Partial<T> {
  const out: Record<string, unknown> = {};
  for (const [key, value] of Object.entries(payload)) {
    if (value === undefined || value === null || value === '') continue;
    out[key] = value;
  }
  return out as Partial<T>;
}

// ─────────────────────────────────────────────────────────────────────────────
// RESTAURANT — /restaurant
// ─────────────────────────────────────────────────────────────────────────────

export type RestaurantTable = {
  id: number;
  name?: string | null;
  zone_name?: string | null;
  seats?: number | null;
  status?: string | null;
};

export type RestaurantProduct = {
  id: number;
  name?: string | null;
  price?: number | null;
  price_minor?: number | null;
  category_name?: string | null;
};

export async function fetchRestaurantTables(): Promise<RestaurantTable[]> {
  // Le web expose les tables du service sous `/restaurant/server/tables`.
  const { data } = await api.get('/restaurant/server/tables', { params: { per_page: 100 } });
  return unwrapList<RestaurantTable>(data);
}

export async function fetchRestaurantProducts(): Promise<RestaurantProduct[]> {
  const { data } = await api.get('/restaurant/products', { params: { per_page: 100 } });
  return unwrapList<RestaurantProduct>(data);
}

/** POST /restaurant/orders — `StoreRestaurantOrderRequest`. */
export async function createRestaurantOrder(payload: {
  branch_id: number;
  order_type: 'dine_in' | 'takeaway' | 'delivery';
  table_id?: number;
  covers?: number;
}): Promise<unknown> {
  const { data } = await api.post('/restaurant/orders', compact(payload));
  return unwrap(data);
}

/** POST /restaurant/orders/{id}/items — `StoreRestaurantOrderItemRequest`. */
export async function addRestaurantOrderItem(
  orderId: number,
  payload: { product_id: number; quantity: number },
): Promise<unknown> {
  const { data } = await api.post(`/restaurant/orders/${orderId}/items`, payload);
  return unwrap(data);
}

/** POST /restaurant/orders/{id}/submit — envoi en cuisine. */
export async function submitRestaurantOrder(orderId: number): Promise<unknown> {
  const { data } = await api.post(`/restaurant/orders/${orderId}/submit`);
  return unwrap(data);
}

/** POST /restaurant/orders/{id}/confirm. */
export async function confirmRestaurantOrder(orderId: number): Promise<unknown> {
  const { data } = await api.post(`/restaurant/orders/${orderId}/confirm`);
  return unwrap(data);
}

/** POST /restaurant/orders/{id}/serve. */
export async function serveRestaurantOrder(orderId: number): Promise<unknown> {
  const { data } = await api.post(`/restaurant/orders/${orderId}/serve`);
  return unwrap(data);
}

/** POST /restaurant/orders/{id}/cancel. */
export async function cancelRestaurantOrder(orderId: number): Promise<unknown> {
  const { data } = await api.post(`/restaurant/orders/${orderId}/cancel`);
  return unwrap(data);
}

/**
 * POST /restaurant/orders/{id}/pay — `PayRestaurantOrderRequest`.
 * `provider_code` est restreint a cash | card | mobile_money.
 */
export async function payRestaurantOrder(
  orderId: number,
  payload: { provider_code: 'cash' | 'card' | 'mobile_money'; amount_minor: number; tip_minor?: number },
): Promise<unknown> {
  const { data } = await api.post(
    `/restaurant/orders/${orderId}/pay`,
    compact({ ...payload, idempotency_key: newIdempotencyKey() }),
  );
  return unwrap(data);
}

/** POST /restaurant/kitchen/orders/{id}/start — demarrage en cuisine. */
export async function startRestaurantKitchenOrder(orderId: number): Promise<unknown> {
  const { data } = await api.post(`/restaurant/kitchen/orders/${orderId}/start`);
  return unwrap(data);
}

/** POST /restaurant/kitchen/orders/{id}/ready — plat pret. */
export async function readyRestaurantKitchenOrder(orderId: number): Promise<unknown> {
  const { data } = await api.post(`/restaurant/kitchen/orders/${orderId}/ready`);
  return unwrap(data);
}

/** POST /restaurant/products — `StoreRestaurantProductRequest` (champs minimaux). */
export async function createRestaurantProduct(payload: {
  name: string;
  price_minor: number;
  category_id?: number;
  is_active?: boolean;
}): Promise<unknown> {
  const { data } = await api.post('/restaurant/products', compact(payload));
  return unwrap(data);
}

// ─────────────────────────────────────────────────────────────────────────────
// AGENCE DE VOYAGE — /travel
// ─────────────────────────────────────────────────────────────────────────────

export type TravelRoute = {
  id: number;
  name?: string | null;
  origin_name?: string | null;
  destination_name?: string | null;
};

export type TravelCarrier = { id: number; name?: string | null };

export async function fetchTravelRoutes(): Promise<TravelRoute[]> {
  const { data } = await api.get('/travel/routes', { params: { per_page: 100 } });
  return unwrapList<TravelRoute>(data);
}

export async function fetchTravelCarriers(): Promise<TravelCarrier[]> {
  const { data } = await api.get('/travel/carriers', { params: { per_page: 100 } });
  return unwrapList<TravelCarrier>(data);
}

/** POST /travel/trips — `StoreTravelTripRequest`. */
export async function createTravelTrip(payload: {
  code: string;
  route_id: number;
  departure_date: string;
  departure_time: string;
  arrival_date: string;
  arrival_time: string;
  total_seats: number;
  carrier_id?: number;
  vehicle_id?: number;
}): Promise<unknown> {
  const { data } = await api.post('/travel/trips', compact(payload));
  return unwrap(data);
}

/** POST /travel/trips/{id}/publish — mise en vente. */
export async function publishTravelTrip(tripId: number): Promise<unknown> {
  const { data } = await api.post(`/travel/trips/${tripId}/publish`);
  return unwrap(data);
}

/** POST /travel/trips/{id}/cancel — `CancelTravelTripRequest` (reason). */
export async function cancelTravelTrip(tripId: number, reason: string): Promise<unknown> {
  const { data } = await api.post(`/travel/trips/${tripId}/cancel`, { reason });
  return unwrap(data);
}

/** POST /travel/bookings — `StoreTravelBookingRequest`. */
export async function createTravelBooking(payload: {
  trip_id: number;
  passengers: { full_name: string; document_type?: string }[];
  contact_phone?: string;
  contact_email?: string;
  booking_source?: 'office' | 'phone' | 'partner' | 'online';
}): Promise<unknown> {
  const { data } = await api.post(
    '/travel/bookings',
    compact({ ...payload, idempotency_key: newIdempotencyKey() }),
  );
  return unwrap(data);
}

/** POST /travel/bookings/{id}/confirm. */
export async function confirmTravelBooking(bookingId: number): Promise<unknown> {
  const { data } = await api.post(`/travel/bookings/${bookingId}/confirm`);
  return unwrap(data);
}

/** POST /travel/bookings/{id}/cancel. */
export async function cancelTravelBooking(bookingId: number, reason?: string): Promise<unknown> {
  const { data } = await api.post(`/travel/bookings/${bookingId}/cancel`, compact({ reason }));
  return unwrap(data);
}

/** POST /travel/bookings/{id}/issue-ticket — emission des billets. */
export async function issueTravelTickets(bookingId: number): Promise<unknown> {
  const { data } = await api.post(`/travel/bookings/${bookingId}/issue-ticket`);
  return unwrap(data);
}

// ─────────────────────────────────────────────────────────────────────────────
// COMMERCE (retail) — /retail
// ─────────────────────────────────────────────────────────────────────────────

export type RetailCategory = { id: number; name?: string | null };
export type RetailLocation = { id: number; name?: string | null };
export type RetailPosSession = { id: number; status?: string | null; opened_at?: string | null };

export async function fetchRetailCategories(): Promise<RetailCategory[]> {
  const { data } = await api.get('/retail/categories', { params: { per_page: 100 } });
  return unwrapList<RetailCategory>(data);
}

export async function fetchRetailLocations(): Promise<RetailLocation[]> {
  const { data } = await api.get('/retail/locations', { params: { per_page: 100 } });
  return unwrapList<RetailLocation>(data);
}

export async function fetchRetailPosSessions(): Promise<RetailPosSession[]> {
  const { data } = await api.get('/retail/pos/sessions', { params: { per_page: 50 } });
  return unwrapList<RetailPosSession>(data);
}

/**
 * POST /retail/products — `StoreRetailProductRequest`.
 * `sku` et `price_minor` sont obligatoires ; `name` aussi.
 */
export async function createRetailProduct(payload: {
  name: string;
  sku: string;
  price_minor: number;
  category_id?: number;
  description?: string;
  unit?: string;
  status?: string;
}): Promise<unknown> {
  const { data } = await api.post('/retail/products', compact(payload));
  return unwrap(data);
}

/** PUT /retail/products/{id} — mise a jour partielle. */
export async function updateRetailProduct(
  productId: number,
  payload: Record<string, unknown>,
): Promise<unknown> {
  const { data } = await api.put(`/retail/products/${productId}`, compact(payload));
  return unwrap(data);
}

/** POST /retail/products/{id}/publish — publication en boutique. */
export async function publishRetailProduct(productId: number): Promise<unknown> {
  const { data } = await api.post(`/retail/products/${productId}/publish`);
  return unwrap(data);
}

/** POST /retail/products/{id}/unpublish. */
export async function unpublishRetailProduct(productId: number): Promise<unknown> {
  const { data } = await api.post(`/retail/products/${productId}/unpublish`);
  return unwrap(data);
}

/** POST /retail/stock/movements — entree/sortie de stock. */
export async function createRetailStockMovement(payload: {
  product_id: number;
  location_id: number;
  quantity_delta: number;
  reason_code: string;
  note?: string;
}): Promise<unknown> {
  const { data } = await api.post('/retail/stock/movements', compact(payload));
  return unwrap(data);
}

/** POST /retail/pos/sessions — ouverture de caisse. */
export async function openRetailPosSession(payload: {
  location_id: number;
  opening_float_minor?: number;
}): Promise<unknown> {
  const { data } = await api.post('/retail/pos/sessions', compact(payload));
  return unwrap(data);
}

/** POST /retail/pos/sessions/{id}/close — cloture de caisse. */
export async function closeRetailPosSession(
  sessionId: number,
  payload: { counted_cash_minor?: number; note?: string } = {},
): Promise<unknown> {
  const { data } = await api.post(`/retail/pos/sessions/${sessionId}/close`, compact(payload));
  return unwrap(data);
}

/** POST /retail/pos/orders — `StoreRetailOrderRequest`. */
export async function createRetailOrder(payload: {
  pos_session_id: number;
  lines: { product_id: number; quantity: number }[];
  note?: string;
}): Promise<unknown> {
  const { data } = await api.post(
    '/retail/pos/orders',
    compact({ ...payload, idempotency_key: newIdempotencyKey() }),
  );
  return unwrap(data);
}

/** POST /retail/pos/orders/{id}/payments — encaissement. */
export async function addRetailOrderPayment(
  orderId: number,
  payload: { method: 'cash' | 'card' | 'mobile'; amount_minor: number },
): Promise<unknown> {
  const { data } = await api.post(`/retail/pos/orders/${orderId}/payments`, compact(payload));
  return unwrap(data);
}

/** POST /retail/pos/orders/{id}/cancel. */
export async function cancelRetailOrder(orderId: number): Promise<unknown> {
  const { data } = await api.post(`/retail/pos/orders/${orderId}/cancel`);
  return unwrap(data);
}

// ─────────────────────────────────────────────────────────────────────────────
// SANTE — /health-manager
// ─────────────────────────────────────────────────────────────────────────────

export type HealthPractitioner = {
  id: number;
  full_name?: string | null;
  specialty_name?: string | null;
};

export type HealthDepartment = { id: number; name?: string | null };

export async function fetchHealthPractitioners(): Promise<HealthPractitioner[]> {
  const { data } = await api.get('/health-manager/practitioners', { params: { per_page: 100 } });
  return unwrapList<HealthPractitioner>(data);
}

export async function fetchHealthDepartments(): Promise<HealthDepartment[]> {
  const { data } = await api.get('/health-manager/departments', { params: { per_page: 100 } });
  return unwrapList<HealthDepartment>(data);
}

/** POST /health-manager/patients — `StoreHealthPatientRequest`. */
export async function createHealthPatient(payload: {
  full_name: string;
  sex: 'male' | 'female' | 'other';
  birth_date?: string;
  phone?: string;
  email?: string;
  blood_group?: string;
}): Promise<unknown> {
  const { data } = await api.post('/health-manager/patients', compact(payload));
  return unwrap(data);
}

/** POST /health-manager/appointments — `StoreHealthAppointmentRequest`. */
export async function createHealthAppointment(payload: {
  patient_id: number;
  practitioner_id: number;
  starts_at: string;
  ends_at: string;
  department_id?: number;
  reason?: string;
}): Promise<unknown> {
  const { data } = await api.post('/health-manager/appointments', compact(payload));
  return unwrap(data);
}

/** POST /health-manager/appointments/{id}/status — `TransitionHealthAppointmentRequest`. */
export async function transitionHealthAppointment(
  appointmentId: number,
  status: string,
): Promise<unknown> {
  const { data } = await api.post(`/health-manager/appointments/${appointmentId}/status`, { status });
  return unwrap(data);
}

/** POST /health-manager/admissions — `StoreHealthAdmissionRequest`. */
export async function createHealthAdmission(payload: {
  patient_id: number;
  bed_id: number;
  practitioner_id?: number;
  reason?: string;
}): Promise<unknown> {
  const { data } = await api.post('/health-manager/admissions', compact(payload));
  return unwrap(data);
}

/** POST /health-manager/admissions/{id}/discharge. */
export async function dischargeHealthAdmission(
  admissionId: number,
  payload: { summary?: string } = {},
): Promise<unknown> {
  const { data } = await api.post(
    `/health-manager/admissions/${admissionId}/discharge`,
    compact(payload),
  );
  return unwrap(data);
}

/** POST /health-manager/invoices — `StoreHealthInvoiceRequest`. */
export async function createHealthInvoice(payload: {
  patient_id: number;
  lines: { description: string; quantity: number; unit_price_minor: number }[];
}): Promise<unknown> {
  const { data } = await api.post('/health-manager/invoices', compact(payload));
  return unwrap(data);
}

// ─────────────────────────────────────────────────────────────────────────────
// PHARMACIE — /pharmacy
// ─────────────────────────────────────────────────────────────────────────────

/** POST /pharmacy/products — `StorePharmacyProductRequest`. */
export async function createPharmacyProduct(payload: {
  name: string;
  form?: string;
  dosage?: string;
  sale_price?: number;
  purchase_price?: number;
  min_stock_level?: number;
  prescription_required?: boolean;
}): Promise<unknown> {
  const { data } = await api.post('/pharmacy/products', compact(payload));
  return unwrap(data);
}

/**
 * POST /pharmacy/sales — `StorePharmacySaleRequest`.
 * `payment_method` ∈ cash | card | mobile | insurance.
 */
export async function createPharmacySale(payload: {
  payment_method: 'cash' | 'card' | 'mobile' | 'insurance';
  lines: { product_id: number; quantity: number }[];
  customer_name?: string;
}): Promise<unknown> {
  const { data } = await api.post('/pharmacy/sales', compact(payload));
  return unwrap(data);
}

/** POST /pharmacy/sales/{id}/void — `VoidPharmacySaleRequest` (reason). */
export async function voidPharmacySale(saleId: number, reason: string): Promise<unknown> {
  const { data } = await api.post(`/pharmacy/sales/${saleId}/void`, { reason });
  return unwrap(data);
}

/** POST /pharmacy/stock/adjustments — `StorePharmacyStockAdjustmentRequest`. */
export async function createPharmacyStockAdjustment(payload: {
  batch_id: number;
  quantity_delta: number;
  reason: string;
  type?: string;
}): Promise<unknown> {
  const { data } = await api.post('/pharmacy/stock/adjustments', compact(payload));
  return unwrap(data);
}

/** POST /pharmacy/purchase-orders — `StorePharmacyPurchaseOrderRequest`. */
export async function createPharmacyPurchaseOrder(payload: {
  supplier_id: number;
  lines: { product_id: number; quantity: number; unit_cost?: number }[];
}): Promise<unknown> {
  const { data } = await api.post('/pharmacy/purchase-orders', compact(payload));
  return unwrap(data);
}

/** POST /pharmacy/purchase-orders/{id}/receive — reception. */
export async function receivePharmacyPurchaseOrder(purchaseOrderId: number): Promise<unknown> {
  const { data } = await api.post(`/pharmacy/purchase-orders/${purchaseOrderId}/receive`);
  return unwrap(data);
}

// ─────────────────────────────────────────────────────────────────────────────
// COMMERCE INTERNATIONAL — /trade
// ─────────────────────────────────────────────────────────────────────────────

/** POST /trade/rfqs — `RfqStoreRequest`. */
export async function createTradeRfq(payload: {
  title: string;
  lines: { description: string; quantity: number; unit?: string }[];
  destination_country?: string;
  destination_port?: string;
  incoterm?: string;
  notes?: string;
}): Promise<unknown> {
  const { data } = await api.post('/trade/rfqs', compact(payload));
  return unwrap(data);
}

/** POST /trade/rfqs/{id}/send — diffusion aux contreparties. */
export async function sendTradeRfq(
  rfqId: number,
  payload: { counterparty_ids: number[]; message?: string },
): Promise<unknown> {
  const { data } = await api.post(`/trade/rfqs/${rfqId}/send`, compact(payload));
  return unwrap(data);
}

/** POST /trade/rfqs/{id}/transition — changement d'etat. */
export async function transitionTradeRfq(
  rfqId: number,
  payload: { status: string; awarded_quote_id?: number; close_reason?: string },
): Promise<unknown> {
  const { data } = await api.post(`/trade/rfqs/${rfqId}/transition`, compact(payload));
  return unwrap(data);
}

/** POST /trade/quotes/{id}/accept — acceptation d'une offre. */
export async function acceptTradeQuote(quoteId: number): Promise<unknown> {
  const { data } = await api.post(`/trade/quotes/${quoteId}/accept`);
  return unwrap(data);
}

/** POST /trade/orders/{id}/transition — changement d'etat d'une commande. */
export async function transitionTradeOrder(
  orderId: number,
  payload: { status: string; note?: string },
): Promise<unknown> {
  const { data } = await api.post(`/trade/orders/${orderId}/transition`, compact(payload));
  return unwrap(data);
}

/** POST /trade/counterparties — creation d'une contrepartie. */
export async function createTradeCounterparty(payload: {
  name: string;
  country_code?: string;
  email?: string;
  type?: string;
}): Promise<unknown> {
  const { data } = await api.post('/trade/counterparties', compact(payload));
  return unwrap(data);
}

// ─────────────────────────────────────────────────────────────────────────────
// HOSPITALITY — /hospitality
// ─────────────────────────────────────────────────────────────────────────────

export type HospitalityRoomType = {
  id: number;
  name?: string | null;
  capacity?: number | null;
  base_price_minor?: number | null;
};

export async function fetchHospitalityRoomTypes(
  propertyId: number,
): Promise<HospitalityRoomType[]> {
  const { data } = await api.get(`/hospitality/properties/${propertyId}/room-types`, {
    params: { per_page: 100 },
  });
  return unwrapList<HospitalityRoomType>(data);
}

/** POST /hospitality/reservations — `StoreHospitalityReservationRequest`. */
export async function createHospitalityReservation(payload: {
  property_id: number;
  room_type_id: number;
  guest_name: string;
  check_in: string;
  check_out: string;
  adults?: number;
  children?: number;
  contact_email?: string;
  contact_phone?: string;
}): Promise<unknown> {
  const { data } = await api.post('/hospitality/reservations', compact(payload));
  return unwrap(data);
}

/** Transitions de reservation : confirm | check-in | check-out | cancel | no-show. */
export async function transitionHospitalityReservation(
  reservationId: number,
  action: 'confirm' | 'check-in' | 'check-out' | 'cancel' | 'no-show',
  payload: { reason?: string } = {},
): Promise<unknown> {
  const { data } = await api.post(
    `/hospitality/reservations/${reservationId}/${action}`,
    compact(payload),
  );
  return unwrap(data);
}

/** POST /hospitality/properties — `StoreHospitalityPropertyRequest`. */
export async function createHospitalityProperty(payload: {
  name: string;
  city?: string;
  type?: string;
  address?: string;
}): Promise<unknown> {
  const { data } = await api.post('/hospitality/properties', compact(payload));
  return unwrap(data);
}

// ─────────────────────────────────────────────────────────────────────────────
// VTC & TAXI — /vtc
// ─────────────────────────────────────────────────────────────────────────────

export type VtcFareProfile = { id: number; name?: string | null };

export async function fetchVtcFareProfiles(): Promise<VtcFareProfile[]> {
  const { data } = await api.get('/vtc/fare-profiles', { params: { per_page: 100 } });
  return unwrapList<VtcFareProfile>(data);
}

/**
 * POST /vtc/rides — `RideStoreRequest`.
 * `pickup` et `dropoff` sont des points geo { lat, lng } obligatoires.
 */
export async function createVtcRide(payload: {
  pickup: { lat: number; lng: number };
  dropoff: { lat: number; lng: number };
  pickup_address?: string;
  dropoff_address?: string;
  passenger_name?: string;
  passenger_phone?: string;
  fare_profile_id?: number;
}): Promise<unknown> {
  const { data } = await api.post(
    '/vtc/rides',
    compact({ ...payload, idempotency_key: newIdempotencyKey() }),
  );
  return unwrap(data);
}

/** POST /vtc/rides/{id}/cancel — `reason` obligatoire. */
export async function cancelVtcRide(rideId: number, reason: string): Promise<unknown> {
  const { data } = await api.post(`/vtc/rides/${rideId}/cancel`, { reason });
  return unwrap(data);
}

/** Transitions chauffeur : accept | decline | arrive | start | complete. */
export async function transitionVtcDriverRide(
  rideId: number,
  action: 'accept' | 'decline' | 'arrive' | 'start' | 'complete',
): Promise<unknown> {
  const { data } = await api.post(`/vtc/driver/rides/${rideId}/${action}`);
  return unwrap(data);
}

/** POST /vtc/driver/availability — passage en ligne / hors ligne. */
export async function setVtcDriverAvailability(available: boolean): Promise<unknown> {
  const { data } = await api.post('/vtc/driver/availability', { available });
  return unwrap(data);
}

// ─────────────────────────────────────────────────────────────────────────────
// STATION-SERVICE — /fuel-station
// ─────────────────────────────────────────────────────────────────────────────

export type FuelStation = { id: number; name?: string | null; city?: string | null };

export async function fetchFuelStations(): Promise<FuelStation[]> {
  const { data } = await api.get('/fuel-station/stations', { params: { per_page: 100 } });
  return unwrapList<FuelStation>(data);
}

/**
 * POST /fuel-station/sales — `StoreFuelSaleRequest`.
 * `product`, `quantity` et `unit_price` sont obligatoires.
 */
export async function createFuelSale(payload: {
  product: string;
  quantity: number;
  unit_price: number;
  station_id?: number;
  pump_id?: number;
  payment_method?: 'cash' | 'card' | 'mobile' | 'credit';
  notes?: string;
}): Promise<unknown> {
  const { data } = await api.post(
    '/fuel-station/sales',
    compact({ ...payload, external_id: newIdempotencyKey() }),
  );
  return unwrap(data);
}

/** POST /fuel-station/sales/{id}/cancel — `reason` (3..500). */
export async function cancelFuelSale(saleId: number, reason: string): Promise<unknown> {
  const { data } = await api.post(`/fuel-station/sales/${saleId}/cancel`, { reason });
  return unwrap(data);
}

/** POST /fuel-station/cash-sessions — ouverture de caisse. */
export async function openFuelCashSession(payload: {
  station_id?: number;
  opening_float?: number;
}): Promise<unknown> {
  const { data } = await api.post('/fuel-station/cash-sessions', compact(payload));
  return unwrap(data);
}

/** POST /fuel-station/cash-sessions/{id}/close. */
export async function closeFuelCashSession(
  sessionId: number,
  payload: { counted_cash?: number; note?: string } = {},
): Promise<unknown> {
  const { data } = await api.post(
    `/fuel-station/cash-sessions/${sessionId}/close`,
    compact(payload),
  );
  return unwrap(data);
}

/** POST /fuel-station/shifts — creation d'une equipe. */
export async function createFuelShift(payload: {
  name: string;
  starts_at: string;
  ends_at: string;
  station_id?: number;
}): Promise<unknown> {
  const { data } = await api.post('/fuel-station/shifts', compact(payload));
  return unwrap(data);
}

/** POST /fuel-station/incidents — declaration d'incident. */
export async function createFuelIncident(payload: {
  title: string;
  description?: string;
  severity?: string;
  station_id?: number;
}): Promise<unknown> {
  const { data } = await api.post('/fuel-station/incidents', compact(payload));
  return unwrap(data);
}

// ─────────────────────────────────────────────────────────────────────────────
// SCOLARITE — /edu-manager
// ─────────────────────────────────────────────────────────────────────────────

export type EduCampus = { id: number; name?: string | null };
export type EduSubject = { id: number; name?: string | null };

export async function fetchEduCampuses(): Promise<EduCampus[]> {
  const { data } = await api.get('/edu-manager/campuses', { params: { per_page: 100 } });
  return unwrapList<EduCampus>(data);
}

export async function fetchEduSubjects(): Promise<EduSubject[]> {
  const { data } = await api.get('/edu-manager/subjects', { params: { per_page: 100 } });
  return unwrapList<EduSubject>(data);
}

/** POST /edu-manager/students — `StoreEduStudentRequest`. */
export async function createEduStudent(payload: {
  student_number: string;
  display_name: string;
  birth_date?: string;
  status?: 'active' | 'inactive' | 'archived';
}): Promise<unknown> {
  const { data } = await api.post('/edu-manager/students', compact(payload));
  return unwrap(data);
}

/** POST /edu-manager/classes — creation d'une classe. */
export async function createEduClass(payload: {
  name: string;
  campus_id: number;
  code?: string;
  level?: string;
  academic_year_id?: number;
}): Promise<unknown> {
  const { data } = await api.post('/edu-manager/classes', compact(payload));
  return unwrap(data);
}

/** POST /edu-manager/admissions — dossier d'admission. */
export async function createEduAdmission(payload: {
  display_name: string;
  campus_id?: number;
  guardian_name?: string;
  guardian_phone?: string;
}): Promise<unknown> {
  const { data } = await api.post('/edu-manager/admissions', compact(payload));
  return unwrap(data);
}

/** POST /edu-manager/admissions/{id}/convert — admission -> eleve. */
export async function convertEduAdmission(admissionId: number): Promise<unknown> {
  const { data } = await api.post(`/edu-manager/admissions/${admissionId}/convert`);
  return unwrap(data);
}

/** POST /edu-manager/classes/{id}/attendances — appel du jour. */
export async function recordEduAttendance(
  classId: number,
  payload: { date: string; entries: { student_id: number; status: string }[] },
): Promise<unknown> {
  const { data } = await api.post(`/edu-manager/classes/${classId}/attendances`, compact(payload));
  return unwrap(data);
}

/** POST /edu-manager/assessments — creation d'une evaluation. */
export async function createEduAssessment(payload: {
  class_id: number;
  subject_id: number;
  title: string;
  max_score?: number;
  date?: string;
}): Promise<unknown> {
  const { data } = await api.post('/edu-manager/assessments', compact(payload));
  return unwrap(data);
}

/** POST /edu-manager/assessments/{id}/grades — saisie des notes. */
export async function gradeEduAssessment(
  assessmentId: number,
  payload: { grades: { student_id: number; score: number }[] },
): Promise<unknown> {
  const { data } = await api.post(`/edu-manager/assessments/${assessmentId}/grades`, payload);
  return unwrap(data);
}

// ─────────────────────────────────────────────────────────────────────────────
// CRM — /crm
// ─────────────────────────────────────────────────────────────────────────────

/** POST /crm/leads — `StoreCrmLeadRequest`. */
export async function createCrmLead(payload: {
  first_name: string;
  last_name?: string;
  company_name?: string;
  email?: string;
  phone?: string;
  source?: string;
  notes?: string;
}): Promise<unknown> {
  const { data } = await api.post('/crm/leads', compact(payload));
  return unwrap(data);
}

/** POST /crm/accounts — `StoreCrmAccountRequest`. */
export async function createCrmAccount(payload: {
  name: string;
  email?: string;
  phone?: string;
  status?: 'active' | 'inactive' | 'archived';
}): Promise<unknown> {
  const { data } = await api.post('/crm/accounts', compact(payload));
  return unwrap(data);
}

/** POST /crm/leads/{id}/convert — lead -> compte/opportunite. */
export async function convertCrmLead(leadId: number): Promise<unknown> {
  const { data } = await api.post(`/crm/leads/${leadId}/convert`);
  return unwrap(data);
}

/** PATCH /crm/opportunities/{id}/stage — changement d'etape. */
export async function transitionCrmOpportunityStage(
  opportunityId: number,
  stage: string,
): Promise<unknown> {
  const { data } = await api.patch(`/crm/opportunities/${opportunityId}/stage`, { stage });
  return unwrap(data);
}

// ─────────────────────────────────────────────────────────────────────────────
// COMPTABILITE — /accounting
// ─────────────────────────────────────────────────────────────────────────────

/** POST /accounting/documents — creation d'un document (facture, devis…). */
export async function createAccountingDocument(payload: {
  type: string;
  contact_id?: number;
  issued_at?: string;
  due_at?: string;
  lines: { description: string; quantity: number; unit_price_minor: number }[];
}): Promise<unknown> {
  const { data } = await api.post('/accounting/documents', compact(payload));
  return unwrap(data);
}

/** POST /accounting/documents/{id}/send — envoi au client. */
export async function sendAccountingDocument(documentId: number): Promise<unknown> {
  const { data } = await api.post(`/accounting/documents/${documentId}/send`);
  return unwrap(data);
}

/** POST /accounting/documents/{id}/payments — encaissement. */
export async function addAccountingPayment(
  documentId: number,
  payload: { amount_minor: number; method?: string; paid_at?: string },
): Promise<unknown> {
  const { data } = await api.post(
    `/accounting/documents/${documentId}/payments`,
    compact(payload),
  );
  return unwrap(data);
}

/** POST /accounting/contacts — creation d'un contact. */
export async function createAccountingContact(payload: {
  name: string;
  email?: string;
  phone?: string;
  type?: 'customer' | 'supplier';
}): Promise<unknown> {
  const { data } = await api.post('/accounting/contacts', compact(payload));
  return unwrap(data);
}

// ─────────────────────────────────────────────────────────────────────────────
// MARKETING — /marketing
// ─────────────────────────────────────────────────────────────────────────────

/** POST /marketing/social-posts — creation d'une publication. */
export async function createSocialPost(payload: {
  content: string;
  target_platforms?: string[];
  scheduled_at?: string;
}): Promise<unknown> {
  const { data } = await api.post('/marketing/social-posts', compact(payload));
  return unwrap(data);
}

/** POST /marketing/social-posts/{id}/publish — publication immediate. */
export async function publishSocialPost(postId: number): Promise<unknown> {
  const { data } = await api.post(`/marketing/social-posts/${postId}/publish`);
  return unwrap(data);
}

/** POST /marketing/leads/{id}/qualify — qualification d'un lead marketing. */
export async function qualifyMarketingLead(leadId: number): Promise<unknown> {
  const { data } = await api.post(`/marketing/leads/${leadId}/qualify`);
  return unwrap(data);
}

// ─────────────────────────────────────────────────────────────────────────────
// Listes complementaires (alimentent les selecteurs des formulaires)
// ─────────────────────────────────────────────────────────────────────────────

export type PharmacySaleRow = {
  id: number;
  number?: string | null;
  customer_name?: string | null;
  status?: string | null;
  total_amount?: string | number | null;
};

/** GET /pharmacy/sales — ventes de l'officine. */
export async function fetchPharmacySales(): Promise<PharmacySaleRow[]> {
  const { data } = await api.get('/pharmacy/sales', { params: { per_page: 50 } });
  return unwrapList<PharmacySaleRow>(data);
}

export type EduAdmissionRow = {
  id: number;
  display_name?: string | null;
  status?: string | null;
  campus_name?: string | null;
};

/** GET /edu-manager/admissions — dossiers d'admission. */
export async function fetchEduAdmissions(): Promise<EduAdmissionRow[]> {
  const { data } = await api.get('/edu-manager/admissions', { params: { per_page: 50 } });
  return unwrapList<EduAdmissionRow>(data);
}

// ─────────────────────────────────────────────────────────────────────────────
// Re-exports des lectures existantes
// ─────────────────────────────────────────────────────────────────────────────
// Le registre d'actions (`verticals/actions.ts`) n'importe qu'un seul module
// d'API : on reexpose ici les listes deja implementees dans `verticals.ts`
// pour alimenter les selecteurs des formulaires.

export {
  fetchRestaurantBranches,
  fetchRestaurantServerOrders,
  fetchTravelTrips,
  fetchTravelBookings,
  fetchRetailProducts,
  fetchHealthPatients,
  fetchHealthAppointments,
  fetchPharmacyProducts,
  fetchTradeRfqs,
  fetchTradeQuotes,
  fetchTradeOrders,
  fetchHospitalityProperties,
  fetchHospitalityReservations,
  fetchVtcDispatchRides,
  fetchFuelSales,
  fetchEduClasses,
  fetchCrmLeads,
  fetchCrmOpportunities,
  fetchAccountingDocuments,
  fetchSocialPosts,
} from './verticals';

export type PharmacyBatch = {
  id: number;
  batch_number?: string | null;
  product_name?: string | null;
  quantity_remaining?: number | null;
  expires_at?: string | null;
};

/**
 * GET /pharmacy/stock/products/{product}/batches — lots d'un produit.
 * L'ajustement de stock porte sur un LOT, pas sur le produit : on agrege les
 * lots des produits deja charges pour alimenter le selecteur.
 */
export async function fetchPharmacyBatches(): Promise<PharmacyBatch[]> {
  const products = await fetchPharmacyProducts();
  const batches: PharmacyBatch[] = [];

  // On interroge les lots des 20 premiers produits : au-dela, le selecteur
  // devient inutilisable sur mobile et le web reste disponible.
  for (const product of products.slice(0, 20)) {
    try {
      const { data } = await api.get(`/pharmacy/stock/products/${product.id}/batches`);
      const rows = unwrapList<PharmacyBatch>(data);
      for (const row of rows) {
        batches.push({ ...row, product_name: row.product_name ?? product.name ?? null });
      }
    } catch {
      // Un produit sans lot ne doit pas casser le selecteur.
    }
  }

  return batches;
}
