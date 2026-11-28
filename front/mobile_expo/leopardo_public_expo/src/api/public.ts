import {
  classifyFailure,
  dataOf,
  listOf,
  metaOf,
  newIdempotencyKey,
  newUuidV4,
  publicApi,
} from './client';

/**
 * APPELS AUX ROUTES PUBLIQUES DU DEPOT `leopardo-hr`.
 *
 * Chaque fonction cite la route exacte qu'elle appelle. Aucun chemin n'est
 * invente : tous sont lus dans `api/routes/api.php` et
 * `api/routes/modules/*.php`.
 *
 * ## Regle de rejeu
 *
 * Les mutations portent une cle d'idempotence CONSERVEE par l'appelant
 * jusqu'au succes. Un double tap renvoie donc la meme ressource (201 puis
 * 200), jamais un doublon.
 */

// ─────────────────────────────────────────────────────────────────────────────
// Decouverte unifiee — GET /public/discover
// ─────────────────────────────────────────────────────────────────────────────

export type DiscoveryItem = {
  type: string;
  slug: string;
  name: string;
  category: string | null;
  city: string | null;
  country: string | null;
  description: string | null;
  image_url: string | null;
  latitude: number | null;
  longitude: number | null;
  distance_km?: number;
  url: string | null;
};

export type DiscoveryResult = {
  items: DiscoveryItem[];
  total: number;
  page: number;
  lastPage: number;
  /** Capacites REELLES par type (`meta.actions`), pour ne jamais mentir. */
  actions: Record<string, string[]>;
};

function toNumber(value: unknown): number | null {
  if (typeof value === 'number' && Number.isFinite(value)) return value;
  if (typeof value === 'string' && value.trim() !== '' && Number.isFinite(Number(value))) {
    return Number(value);
  }
  return null;
}

function toText(value: unknown): string | null {
  return typeof value === 'string' && value !== '' ? value : null;
}

function toDiscoveryItem(raw: unknown): DiscoveryItem | null {
  if (!raw || typeof raw !== 'object') return null;
  const row = raw as Record<string, unknown>;
  const slug = toText(row.slug);
  const type = toText(row.type);
  if (!slug || !type) return null;

  return {
    type,
    slug,
    name: toText(row.name) ?? slug,
    category: toText(row.category),
    city: toText(row.city),
    country: toText(row.country),
    description: toText(row.description),
    image_url: toText(row.image_url),
    latitude: toNumber(row.latitude),
    longitude: toNumber(row.longitude),
    distance_km: toNumber(row.distance_km) ?? undefined,
    url: toText(row.url),
  };
}

/**
 * `GET /public/discover` — annuaire cross-verticale.
 *
 * `type` doit appartenir a `meta.types` ; un type inconnu est refuse en 422.
 * On ne l'envoie donc que si la verticale declare un `discoveryType`.
 */
export async function discover(params: {
  type?: string | null;
  q?: string | null;
  city?: string | null;
  near?: string | null;
  radiusKm?: number | null;
  page?: number;
  perPage?: number;
}): Promise<DiscoveryResult> {
  try {
    const query: Record<string, string | number> = {};
    if (params.type) query.type = params.type;
    if (params.q) query.q = params.q;
    if (params.city) query.city = params.city;
    if (params.near) query.near = params.near;
    if (params.radiusKm) query.radius_km = params.radiusKm;
    if (params.page) query.page = params.page;
    if (params.perPage) query.per_page = params.perPage;

    const response = await publicApi.get('/public/discover', { params: query });
    const meta = metaOf(response.data);
    const actions = (meta.actions ?? {}) as Record<string, string[]>;

    return {
      items: listOf(response.data)
        .map(toDiscoveryItem)
        .filter((item): item is DiscoveryItem => item !== null),
      total: toNumber(meta.total) ?? 0,
      page: toNumber(meta.page) ?? 1,
      lastPage: toNumber(meta.last_page) ?? 1,
      actions,
    };
  } catch (error) {
    throw classifyFailure(error);
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// RESTAURANT — /public/restaurants
// ─────────────────────────────────────────────────────────────────────────────

export type MenuProduct = {
  code: string;
  name: string;
  description: string | null;
  price_minor: number;
  currency: string;
};

export type MenuCategory = { name: string; sort_order: number; products: MenuProduct[] };

export type RestaurantProfile = {
  slug: string;
  name: string;
  establishment_type: string | null;
  cuisine_types: string[] | null;
  city: string | null;
  address: string | null;
  phone: string | null;
  description: string | null;
  cover_image_url: string | null;
  currency: string;
  rating_avg: number | null;
  reviews_count: number;
  hours: unknown;
  menu: MenuCategory[];
};

/** `GET /public/restaurants/{slug}` — profil + menu publie. */
export async function fetchRestaurant(slug: string): Promise<RestaurantProfile> {
  try {
    const response = await publicApi.get(`/public/restaurants/${encodeURIComponent(slug)}`);
    const data = dataOf(response.data);

    const menu: MenuCategory[] = Array.isArray(data.menu)
      ? (data.menu as unknown[]).flatMap((raw) => {
          if (!raw || typeof raw !== 'object') return [];
          const category = raw as Record<string, unknown>;
          const products = Array.isArray(category.products)
            ? (category.products as unknown[]).flatMap((p) => {
                if (!p || typeof p !== 'object') return [];
                const product = p as Record<string, unknown>;
                const code = toText(product.code);
                if (!code) return [];
                return [
                  {
                    code,
                    name: toText(product.name) ?? code,
                    description: toText(product.description),
                    price_minor: toNumber(product.price_minor) ?? 0,
                    currency: toText(product.currency) ?? 'XOF',
                  },
                ];
              })
            : [];
          return [
            {
              name: toText(category.name) ?? 'Carte',
              sort_order: toNumber(category.sort_order) ?? 0,
              products,
            },
          ];
        })
      : [];

    return {
      slug: toText(data.slug) ?? slug,
      name: toText(data.name) ?? slug,
      establishment_type: toText(data.establishment_type),
      cuisine_types: Array.isArray(data.cuisine_types)
        ? (data.cuisine_types as unknown[]).filter((c): c is string => typeof c === 'string')
        : null,
      city: toText(data.city),
      address: toText(data.address),
      phone: toText(data.phone),
      description: toText(data.description),
      cover_image_url: toText(data.cover_image_url),
      currency: toText(data.currency) ?? 'XOF',
      rating_avg: toNumber(data.rating_avg),
      reviews_count: toNumber(data.reviews_count) ?? 0,
      hours: data.hours ?? null,
      menu,
    };
  } catch (error) {
    throw classifyFailure(error);
  }
}

export type RestaurantOrderLine = { product_code: string; quantity: number; note?: string };

export type RestaurantOrderResult = {
  reference: string;
  status: string;
  total_minor: number | null;
  currency: string | null;
  /**
   * Secret de suivi, presente UNE SEULE FOIS a la creation reelle
   * (`tracking_secret` dans la reponse ; `null` au rejeu idempotent).
   * Le nom du champ n'est PAS `tracking_code` : le contrat restaurant
   * l'appelle `tracking_secret` et le suivi l'attend en `?secret=`.
   */
  tracking_secret: string | null;
};

/**
 * `POST /public/restaurants/{slug}/orders`.
 *
 * Contrat `StoreRestaurantPublicSlugOrderRequest` : produits identifies par
 * `product_code` (jamais un ID interne), `order_type` limite a
 * `pickup|takeaway|delivery`, aucun montant accepte du client.
 */
export async function createRestaurantOrder(input: {
  slug: string;
  customerName: string;
  customerPhone: string;
  orderType: 'pickup' | 'delivery';
  note?: string;
  items: RestaurantOrderLine[];
  idempotencyKey: string;
}): Promise<RestaurantOrderResult> {
  try {
    const response = await publicApi.post(
      `/public/restaurants/${encodeURIComponent(input.slug)}/orders`,
      {
        customer_name: input.customerName,
        customer_phone: input.customerPhone,
        order_type: input.orderType,
        note: input.note ?? null,
        idempotency_key: input.idempotencyKey,
        items: input.items.map((line) => ({
          product_code: line.product_code,
          quantity: line.quantity,
          ...(line.note ? { note: line.note } : {}),
        })),
      },
    );
    const data = dataOf(response.data);
    return {
      reference: toText(data.reference) ?? '',
      status: toText(data.status) ?? 'pending',
      total_minor: toNumber(data.total_minor),
      currency: toText(data.currency),
      tracking_secret: toText(data.tracking_secret),
    };
  } catch (error) {
    throw classifyFailure(error);
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// BOUTIQUES — /public/market
// ─────────────────────────────────────────────────────────────────────────────

export type MarketSeller = {
  slug: string;
  name: string;
  city: string | null;
  description: string | null;
  products_count: number;
  rating_avg: number | null;
  rating_count: number;
};

export type MarketProduct = {
  id: number;
  name: string;
  description: string | null;
  price_minor: number;
  currency: string;
  image_url: string | null;
  category: { id: number; name: string } | null;
  seller: { name: string | null; slug: string | null; city: string | null };
  available: boolean;
  rating_avg: number | null;
  rating_count: number;
};

/** `GET /public/market/sellers` — boutiques opt-in. */
export async function fetchSellers(): Promise<MarketSeller[]> {
  try {
    const response = await publicApi.get('/public/market/sellers');
    return listOf(response.data).flatMap((raw) => {
      if (!raw || typeof raw !== 'object') return [];
      const row = raw as Record<string, unknown>;
      const slug = toText(row.slug);
      if (!slug) return [];
      return [
        {
          slug,
          name: toText(row.name) ?? slug,
          city: toText(row.city),
          description: toText(row.description),
          products_count: toNumber(row.products_count) ?? 0,
          rating_avg: toNumber(row.rating_avg),
          rating_count: toNumber(row.rating_count) ?? 0,
        },
      ];
    });
  } catch (error) {
    throw classifyFailure(error);
  }
}

/** `GET /public/market/products?seller=` — catalogue d'une boutique. */
export async function fetchProducts(params: {
  seller?: string | null;
  q?: string | null;
  page?: number;
}): Promise<MarketProduct[]> {
  try {
    const query: Record<string, string | number> = { per_page: 50 };
    if (params.seller) query.seller = params.seller;
    if (params.q) query.q = params.q;
    if (params.page) query.page = params.page;

    const response = await publicApi.get('/public/market/products', { params: query });
    return listOf(response.data).flatMap((raw) => {
      if (!raw || typeof raw !== 'object') return [];
      const row = raw as Record<string, unknown>;
      const id = toNumber(row.id);
      if (id === null) return [];
      const seller = (row.seller ?? {}) as Record<string, unknown>;
      const category = row.category as Record<string, unknown> | null | undefined;
      return [
        {
          id,
          name: toText(row.name) ?? `Produit ${id}`,
          description: toText(row.description),
          price_minor: toNumber(row.price_minor) ?? 0,
          currency: toText(row.currency) ?? 'XOF',
          image_url: toText(row.image_url),
          category:
            category && toNumber(category.id) !== null
              ? { id: toNumber(category.id) as number, name: toText(category.name) ?? '' }
              : null,
          seller: {
            name: toText(seller.name),
            slug: toText(seller.slug),
            city: toText(seller.city),
          },
          available: row.available !== false,
          rating_avg: toNumber(row.rating_avg),
          rating_count: toNumber(row.rating_count) ?? 0,
        },
      ];
    });
  } catch (error) {
    throw classifyFailure(error);
  }
}

export type MarketOrderResult = {
  reference: string;
  tracking_token: string | null;
  total_minor: number | null;
  currency: string | null;
  payment_status: string | null;
  checkout_url: string | null;
};

/**
 * `POST /public/market/orders`.
 *
 * Contrat `StoreMarketOrderRequest` : 1 commande = 1 vendeur, prix relus en
 * base (jamais acceptes du client), `payment_method` limite a `cash|online`,
 * `idempotency_key` OBLIGATOIRE.
 */
export async function createMarketOrder(input: {
  seller: string;
  items: { product_id: number; quantity: number }[];
  customer: { name: string; phone: string; email?: string };
  delivery: { address: string; city: string; notes?: string };
  paymentMethod: 'cash' | 'online';
  idempotencyKey: string;
}): Promise<MarketOrderResult> {
  try {
    const response = await publicApi.post('/public/market/orders', {
      seller: input.seller,
      items: input.items,
      customer: {
        name: input.customer.name,
        phone: input.customer.phone,
        ...(input.customer.email ? { email: input.customer.email } : {}),
      },
      delivery: {
        address: input.delivery.address,
        city: input.delivery.city,
        ...(input.delivery.notes ? { notes: input.delivery.notes } : {}),
      },
      payment_method: input.paymentMethod,
      idempotency_key: input.idempotencyKey,
    });
    const data = dataOf(response.data);
    const payment = (data.payment ?? {}) as Record<string, unknown>;
    return {
      reference: toText(data.reference) ?? '',
      tracking_token: toText(data.tracking_token),
      total_minor: toNumber(data.total_minor),
      currency: toText(data.currency),
      payment_status: toText(payment.status),
      checkout_url: toText(payment.checkout_url),
    };
  } catch (error) {
    throw classifyFailure(error);
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// SEJOURS — /public/hospitality
// ─────────────────────────────────────────────────────────────────────────────

export type RoomType = {
  id: number;
  code: string;
  name: string;
  description: string | null;
  base_price_minor: number;
  currency: string;
};

export type StayProperty = {
  slug: string;
  name: string;
  type: string | null;
  address: string | null;
  city: string | null;
  country: string | null;
  currency: string;
  phone: string | null;
  star_rating: number | null;
  amenities: string[] | null;
  room_types: RoomType[];
};

/** `GET /public/hospitality/properties/{slug}`. */
export async function fetchStayProperty(slug: string): Promise<StayProperty> {
  try {
    const response = await publicApi.get(
      `/public/hospitality/properties/${encodeURIComponent(slug)}`,
    );
    const data = dataOf(response.data);
    const roomTypes = Array.isArray(data.room_types)
      ? (data.room_types as unknown[]).flatMap((raw) => {
          if (!raw || typeof raw !== 'object') return [];
          const row = raw as Record<string, unknown>;
          const id = toNumber(row.id);
          if (id === null) return [];
          return [
            {
              id,
              code: toText(row.code) ?? String(id),
              name: toText(row.name) ?? `Chambre ${id}`,
              description: toText(row.description),
              base_price_minor: toNumber(row.base_price_minor) ?? 0,
              currency: toText(row.currency) ?? 'XOF',
            },
          ];
        })
      : [];

    return {
      slug: toText(data.slug) ?? slug,
      name: toText(data.name) ?? slug,
      type: toText(data.type),
      address: toText(data.address),
      city: toText(data.city),
      country: toText(data.country),
      currency: toText(data.currency) ?? 'XOF',
      phone: toText(data.phone),
      star_rating: toNumber(data.star_rating),
      amenities: Array.isArray(data.amenities)
        ? (data.amenities as unknown[]).filter((a): a is string => typeof a === 'string')
        : null,
      room_types: roomTypes,
    };
  } catch (error) {
    throw classifyFailure(error);
  }
}

export type RoomAvailability = {
  room_type_id: number;
  code: string;
  name: string;
  capacity: number;
  held: number;
  available: number;
  base_price_minor: number;
  currency: string;
};

/** `GET /public/hospitality/properties/{slug}/availability?from=&to=`. */
export async function fetchAvailability(input: {
  slug: string;
  from: string;
  to: string;
  adults?: number;
}): Promise<RoomAvailability[]> {
  try {
    const response = await publicApi.get(
      `/public/hospitality/properties/${encodeURIComponent(input.slug)}/availability`,
      { params: { from: input.from, to: input.to, adults: input.adults ?? 1 } },
    );
    return listOf(response.data).flatMap((raw) => {
      if (!raw || typeof raw !== 'object') return [];
      const row = raw as Record<string, unknown>;
      const id = toNumber(row.room_type_id);
      if (id === null) return [];
      return [
        {
          room_type_id: id,
          code: toText(row.code) ?? String(id),
          name: toText(row.name) ?? `Chambre ${id}`,
          capacity: toNumber(row.capacity) ?? 0,
          held: toNumber(row.held) ?? 0,
          available: toNumber(row.available) ?? 0,
          base_price_minor: toNumber(row.base_price_minor) ?? 0,
          currency: toText(row.currency) ?? 'XOF',
        },
      ];
    });
  } catch (error) {
    throw classifyFailure(error);
  }
}

export type StayReservationResult = {
  reference: string;
  status: string;
  check_in: string;
  check_out: string;
  total_amount_minor: number | null;
  currency: string | null;
  tracking_code: string | null;
  expires_at: string | null;
};

/**
 * `POST /public/hospitality/properties/{slug}/reservations`.
 *
 * Contrat `StoreHospitalityPublicReservationRequest` : le montant est calcule
 * SERVEUR (`base_price_minor` x nuits), jamais accepte du client.
 */
export async function createStayReservation(input: {
  slug: string;
  roomTypeId: number;
  guestName: string;
  email?: string;
  phone?: string;
  checkIn: string;
  checkOut: string;
  adults: number;
  children: number;
  idempotencyKey: string;
}): Promise<StayReservationResult> {
  try {
    const response = await publicApi.post(
      `/public/hospitality/properties/${encodeURIComponent(input.slug)}/reservations`,
      {
        room_type_id: input.roomTypeId,
        guest_name: input.guestName,
        ...(input.email ? { contact_email: input.email } : {}),
        ...(input.phone ? { contact_phone: input.phone } : {}),
        check_in: input.checkIn,
        check_out: input.checkOut,
        adults: input.adults,
        children: input.children,
        idempotency_key: input.idempotencyKey,
      },
    );
    const data = dataOf(response.data);
    return {
      reference: toText(data.reference) ?? '',
      status: toText(data.status) ?? 'pending',
      check_in: toText(data.check_in) ?? input.checkIn,
      check_out: toText(data.check_out) ?? input.checkOut,
      total_amount_minor: toNumber(data.total_amount_minor),
      currency: toText(data.currency),
      tracking_code: toText(data.tracking_code),
      expires_at: toText(data.expires_at),
    };
  } catch (error) {
    throw classifyFailure(error);
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// BILLETS — /public/travel/marketplace
// ─────────────────────────────────────────────────────────────────────────────

export type TravelCity = { id: number; name: string; country_iso2: string | null; region: string | null };

export type TravelTrip = {
  id: number;
  code: string;
  departure_date: string;
  departure_time: string | null;
  arrival_date: string | null;
  arrival_time: string | null;
  means_of_transport: string | null;
  total_seats: number;
  available_seats: number;
  origin_city: { name: string; country_iso2: string | null } | null;
  destination_city: { name: string; country_iso2: string | null } | null;
  prices: { class_id: number; adult_price_minor: number; child_price_minor: number; currency: string }[];
  price_from_minor: number | null;
  currency: string | null;
  agency: { name: string | null };
};

/** `GET /public/travel/marketplace/cities`. */
export async function fetchTravelCities(): Promise<TravelCity[]> {
  try {
    const response = await publicApi.get('/public/travel/marketplace/cities');
    return listOf(response.data).flatMap((raw) => {
      if (!raw || typeof raw !== 'object') return [];
      const row = raw as Record<string, unknown>;
      const id = toNumber(row.id);
      if (id === null) return [];
      return [
        {
          id,
          name: toText(row.name) ?? String(id),
          country_iso2: toText(row.country_iso2),
          region: toText(row.region),
        },
      ];
    });
  } catch (error) {
    throw classifyFailure(error);
  }
}

/** `GET /public/travel/marketplace/trips?origin_city_id=&destination_city_id=&date=`. */
export async function searchTrips(params: {
  originCityId?: number | null;
  destinationCityId?: number | null;
  date?: string | null;
}): Promise<TravelTrip[]> {
  try {
    const query: Record<string, string | number> = { per_page: 50 };
    if (params.originCityId) query.origin_city_id = params.originCityId;
    if (params.destinationCityId) query.destination_city_id = params.destinationCityId;
    if (params.date) query.date = params.date;

    const response = await publicApi.get('/public/travel/marketplace/trips', { params: query });
    return listOf(response.data).flatMap((raw) => {
      if (!raw || typeof raw !== 'object') return [];
      const row = raw as Record<string, unknown>;
      const id = toNumber(row.id);
      if (id === null) return [];
      const origin = row.origin_city as Record<string, unknown> | null | undefined;
      const destination = row.destination_city as Record<string, unknown> | null | undefined;
      const agency = (row.agency ?? {}) as Record<string, unknown>;
      const prices = Array.isArray(row.prices)
        ? (row.prices as unknown[]).flatMap((p) => {
            if (!p || typeof p !== 'object') return [];
            const price = p as Record<string, unknown>;
            const classId = toNumber(price.class_id);
            if (classId === null) return [];
            return [
              {
                class_id: classId,
                adult_price_minor: toNumber(price.adult_price_minor) ?? 0,
                child_price_minor: toNumber(price.child_price_minor) ?? 0,
                currency: toText(price.currency) ?? 'XOF',
              },
            ];
          })
        : [];

      return [
        {
          id,
          code: toText(row.code) ?? String(id),
          departure_date: toText(row.departure_date) ?? '',
          departure_time: toText(row.departure_time),
          arrival_date: toText(row.arrival_date),
          arrival_time: toText(row.arrival_time),
          means_of_transport: toText(row.means_of_transport),
          total_seats: toNumber(row.total_seats) ?? 0,
          available_seats: toNumber(row.available_seats) ?? 0,
          origin_city: origin
            ? { name: toText(origin.name) ?? '', country_iso2: toText(origin.country_iso2) }
            : null,
          destination_city: destination
            ? { name: toText(destination.name) ?? '', country_iso2: toText(destination.country_iso2) }
            : null,
          prices,
          price_from_minor: toNumber(row.price_from_minor),
          currency: toText(row.currency),
          agency: { name: toText(agency.name) },
        },
      ];
    });
  } catch (error) {
    throw classifyFailure(error);
  }
}

export type TravelBookingResult = {
  reference: string;
  status: string;
  total_amount_minor: number | null;
  currency: string | null;
  /**
   * Aucun code de suivi n'est renvoye par ce contrat : `TravelBookingResource`
   * expose `reference`, `status`, `total_amount_minor`, `currency`,
   * `payment_status`, `expires_at` — et rien d'autre. Le suivi se fait donc
   * par reference seule, pas par reference + code.
   */
  payment_status: string | null;
  expires_at: string | null;
};

/**
 * `POST /public/travel/marketplace/bookings`.
 *
 * Contrat `StoreTravelMarketplaceBookingRequest` : `idempotency_key`
 * OBLIGATOIRE, `age_category` et `class_id` requis par passager.
 */
export async function createTravelBooking(input: {
  tripId: number;
  passengers: {
    full_name: string;
    age_category: string;
    class_id: number;
    birth_date?: string;
    seat_number?: number;
  }[];
  contactEmail?: string;
  contactPhone?: string;
  idempotencyKey: string;
}): Promise<TravelBookingResult> {
  try {
    const response = await publicApi.post('/public/travel/marketplace/bookings', {
      trip_id: input.tripId,
      idempotency_key: input.idempotencyKey,
      ...(input.contactEmail ? { contact_email: input.contactEmail } : {}),
      ...(input.contactPhone ? { contact_phone: input.contactPhone } : {}),
      passengers: input.passengers.map((passenger) => ({
        full_name: passenger.full_name,
        age_category: passenger.age_category,
        class_id: passenger.class_id,
        ...(passenger.birth_date ? { birth_date: passenger.birth_date } : {}),
        ...(passenger.seat_number ? { seat_number: passenger.seat_number } : {}),
      })),
    });
    const data = dataOf(response.data);
    return {
      reference: toText(data.reference) ?? '',
      status: toText(data.status) ?? 'pending',
      total_amount_minor: toNumber(data.total_amount_minor),
      currency: toText(data.currency),
      payment_status: toText(data.payment_status),
      expires_at: toText(data.expires_at),
    };
  } catch (error) {
    throw classifyFailure(error);
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// VTC — /public/vtc/marketplace
// ─────────────────────────────────────────────────────────────────────────────

export type VtcOperator = {
  slug: string;
  name: string;
  city: string | null;
  country: string | null;
  currency: string | null;
};

/** `GET /public/vtc/marketplace/operators`. */
export async function fetchVtcOperators(): Promise<VtcOperator[]> {
  try {
    const response = await publicApi.get('/public/vtc/marketplace/operators');
    return listOf(response.data).flatMap((raw) => {
      if (!raw || typeof raw !== 'object') return [];
      const row = raw as Record<string, unknown>;
      const slug = toText(row.slug);
      if (!slug) return [];
      return [
        {
          slug,
          name: toText(row.name) ?? slug,
          city: toText(row.city),
          country: toText(row.country),
          currency: toText(row.currency),
        },
      ];
    });
  } catch (error) {
    throw classifyFailure(error);
  }
}

export type VtcEstimate = {
  price_minor: number | null;
  currency: string | null;
  duration_s: number | null;
  distance_m: number | null;
};

/**
 * `POST /public/vtc/marketplace/estimate`.
 *
 * `price_minor` vaut `null` quand l'operateur n'a pas de grille tarifaire —
 * l'app doit alors dire « prix a confirmer », jamais afficher 0.
 */
export async function estimateRide(input: {
  operator: string;
  pickup: { lat: number; lng: number };
  dropoff: { lat: number; lng: number };
}): Promise<VtcEstimate> {
  try {
    const response = await publicApi.post('/public/vtc/marketplace/estimate', {
      operator: input.operator,
      pickup: input.pickup,
      dropoff: input.dropoff,
    });
    const data = dataOf(response.data);
    return {
      price_minor: toNumber(data.price_minor),
      currency: toText(data.currency),
      duration_s: toNumber(data.estimated_duration_s) ?? toNumber(data.duration_s),
      distance_m: toNumber(data.distance_m),
    };
  } catch (error) {
    throw classifyFailure(error);
  }
}

export type VtcRideResult = {
  reference: string;
  status: string;
  estimated_price_minor: number | null;
  currency: string | null;
  tracking_code: string | null;
};

/**
 * `POST /public/vtc/marketplace/rides`.
 *
 * Contrat `MarketplaceRideStoreRequest` : en-tete **`Idempotency-Key` UUID v4
 * OBLIGATOIRE** (le serveur le rabat dans `idempotency_key`), coordonnees
 * WGS 84 strictes, passager invite (`passenger_name` + `passenger_phone`).
 */
export async function createRide(input: {
  operator: string;
  pickup: { lat: number; lng: number };
  dropoff: { lat: number; lng: number };
  pickupAddress?: string;
  dropoffAddress?: string;
  passengerName: string;
  passengerPhone: string;
  idempotencyKey: string;
}): Promise<VtcRideResult> {
  try {
    const response = await publicApi.post(
      '/public/vtc/marketplace/rides',
      {
        operator: input.operator,
        pickup: input.pickup,
        dropoff: input.dropoff,
        ...(input.pickupAddress ? { pickup_address: input.pickupAddress } : {}),
        ...(input.dropoffAddress ? { dropoff_address: input.dropoffAddress } : {}),
        passenger_name: input.passengerName,
        passenger_phone: input.passengerPhone,
      },
      { headers: { 'Idempotency-Key': input.idempotencyKey } },
    );
    const data = dataOf(response.data);
    return {
      reference: toText(data.reference) ?? '',
      status: toText(data.status) ?? 'pending',
      estimated_price_minor: toNumber(data.estimated_price_minor),
      currency: toText(data.currency),
      tracking_code: toText(data.tracking_code),
    };
  } catch (error) {
    throw classifyFailure(error);
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// ENTREPRISES — /public/trade
// ─────────────────────────────────────────────────────────────────────────────

export type TradeProduct = {
  name: string;
  description: string | null;
  category: string | null;
  origin_country: string | null;
  unit: string | null;
  moq_quantity: number | null;
  /** Prix INDICATIF : le catalogue n'est pas une offre ferme. */
  indicative_unit_price_minor: number | null;
  currency: string | null;
};

export type TradeCompany = {
  slug: string;
  name: string;
  city: string | null;
  country: string | null;
  description: string | null;
  currency: string | null;
  product_categories: string[] | null;
  import_enabled: boolean;
  export_enabled: boolean;
};

/** `GET /public/trade/companies/{slug}` — fiche seule (pas de produits). */
export async function fetchTradeCompany(slug: string): Promise<TradeCompany> {
  try {
    const response = await publicApi.get(`/public/trade/companies/${encodeURIComponent(slug)}`);
    const data = dataOf(response.data);

    return {
      slug: toText(data.slug) ?? slug,
      name: toText(data.name) ?? slug,
      city: toText(data.city),
      country: toText(data.country),
      description: toText(data.description),
      currency: toText(data.currency),
      product_categories: Array.isArray(data.product_categories)
        ? (data.product_categories as unknown[]).filter((c): c is string => typeof c === 'string')
        : null,
      import_enabled: data.import_enabled === true,
      export_enabled: data.export_enabled === true,
    };
  } catch (error) {
    throw classifyFailure(error);
  }
}

/**
 * `GET /public/trade/companies/{slug}/products` — catalogue public.
 *
 * Endpoint SEPARE de la fiche (#9768) : la fiche n'expose que des
 * `product_categories` (des chaines), pas des produits. Les lire sur la fiche
 * renverrait toujours une liste vide.
 */
export async function fetchTradeProducts(slug: string): Promise<TradeProduct[]> {
  try {
    const response = await publicApi.get(
      `/public/trade/companies/${encodeURIComponent(slug)}/products`,
      { params: { per_page: 50 } },
    );
    return listOf(response.data).flatMap((raw) => {
      if (!raw || typeof raw !== 'object') return [];
      const row = raw as Record<string, unknown>;
      const name = toText(row.name);
      if (!name) return [];
      return [
        {
          name,
          description: toText(row.description),
          category: toText(row.category),
          origin_country: toText(row.origin_country),
          unit: toText(row.unit),
          moq_quantity: toNumber(row.moq_quantity),
          indicative_unit_price_minor: toNumber(row.indicative_unit_price_minor),
          currency: toText(row.currency),
        },
      ];
    });
  } catch (error) {
    throw classifyFailure(error);
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// PASSERELLE DE CONTACT — POST /public/contact-requests
// ─────────────────────────────────────────────────────────────────────────────

/**
 * `POST /public/contact-requests` — le SEUL contrat universel.
 *
 * Contrat `StorePublicContactRequest`, lu dans le depot :
 *  - `target_type` doit appartenir a `PublicProfileLocator::types()` ;
 *  - `sender_name` / `sender_email` / `sender_phone` (PAS `name`/`email`/`phone`) ;
 *  - `sender_email` est **obligatoire** (`required|email`) ;
 *  - `consent` est **obligatoire** (`required|accepted`) ;
 *  - `idempotency_key` est **obligatoire** (8 a 80 caracteres).
 *
 * Un type absent (ex. `pharmacy`) est refuse en 422 : l'app ne propose donc le
 * formulaire que pour les types de `CONTACT_TARGET_TYPES`.
 */
export async function sendContactRequest(input: {
  targetType: string;
  targetSlug: string;
  senderName: string;
  senderEmail: string;
  senderPhone?: string;
  message: string;
  idempotencyKey: string;
}): Promise<{ reference: string | null }> {
  try {
    const response = await publicApi.post('/public/contact-requests', {
      target_type: input.targetType,
      target_slug: input.targetSlug,
      sender_name: input.senderName,
      sender_email: input.senderEmail,
      ...(input.senderPhone ? { sender_phone: input.senderPhone } : {}),
      message: input.message,
      // Le formulaire mobile n'a pas de case a cocher separee : l'envoi du
      // formulaire VAUT consentement explicite, et l'ecran l'annonce.
      consent: true,
      idempotency_key: input.idempotencyKey,
    });
    const data = dataOf(response.data);
    return { reference: toText(data.reference) ?? toText(data.id) };
  } catch (error) {
    throw classifyFailure(error);
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// SUIVI — par reference + code
// ─────────────────────────────────────────────────────────────────────────────

export type TrackedOrder = {
  reference: string;
  status: string;
  total_minor: number | null;
  currency: string | null;
  detail: string | null;
};

/**
 * Suivi d'une commande restaurant : `GET /public/restaurants/{slug}/orders/{ref}?secret=`.
 *
 * Le suivi exige le slug ET le secret remis a la creation : sans les deux,
 * le serveur repond 404 (anti-enumeration). L'app stocke donc les deux.
 */
export async function trackRestaurantOrder(input: {
  slug: string;
  reference: string;
  secret: string;
}): Promise<TrackedOrder> {
  try {
    const response = await publicApi.get(
      `/public/restaurants/${encodeURIComponent(input.slug)}/orders/${encodeURIComponent(input.reference)}`,
      { params: { secret: input.secret } },
    );
    const data = dataOf(response.data);
    return {
      reference: toText(data.reference) ?? input.reference,
      status: toText(data.status) ?? 'pending',
      total_minor: toNumber(data.total_minor),
      currency: toText(data.currency),
      detail: toText(data.fulfillment_status) ?? toText(data.order_type),
    };
  } catch (error) {
    throw classifyFailure(error);
  }
}

/** Suivi d'une reservation de sejour : `GET /public/hospitality/reservations/{ref}?code=`. */
export async function trackStayReservation(input: {
  reference: string;
  code: string;
}): Promise<TrackedOrder> {
  try {
    const response = await publicApi.get(
      `/public/hospitality/reservations/${encodeURIComponent(input.reference)}`,
      { params: { code: input.code } },
    );
    const data = dataOf(response.data);
    return {
      reference: toText(data.reference) ?? input.reference,
      status: toText(data.status) ?? 'pending',
      total_minor: toNumber(data.total_amount_minor),
      currency: toText(data.currency),
      detail: [toText(data.check_in), toText(data.check_out)].filter(Boolean).join(' \u2192 ') || null,
    };
  } catch (error) {
    throw classifyFailure(error);
  }
}

/** Suivi d'une course VTC : `GET /public/vtc/marketplace/rides/{ref}?code=`. */
export async function trackRide(input: {
  reference: string;
  code: string;
}): Promise<TrackedOrder> {
  try {
    const response = await publicApi.get(
      `/public/vtc/marketplace/rides/${encodeURIComponent(input.reference)}`,
      { params: { code: input.code } },
    );
    const data = dataOf(response.data);
    return {
      reference: toText(data.reference) ?? input.reference,
      status: toText(data.status) ?? 'pending',
      total_minor: toNumber(data.estimated_price_minor),
      currency: toText(data.currency),
      detail: [toText(data.pickup_address), toText(data.dropoff_address)]
        .filter(Boolean)
        .join(' \u2192 ') || null,
    };
  } catch (error) {
    throw classifyFailure(error);
  }
}

/** Suivi d'une commande boutique : `GET /public/market/orders/{ref}?token=`. */
export async function trackMarketOrder(input: {
  reference: string;
  token: string;
}): Promise<TrackedOrder> {
  try {
    const response = await publicApi.get(
      `/public/market/orders/${encodeURIComponent(input.reference)}`,
      { params: { token: input.token } },
    );
    const data = dataOf(response.data);
    return {
      reference: toText(data.reference) ?? input.reference,
      status: toText(data.fulfillment_status) ?? 'pending',
      total_minor: toNumber(data.total_minor),
      currency: toText(data.currency),
      detail: null,
    };
  } catch (error) {
    throw classifyFailure(error);
  }
}

export { newIdempotencyKey, newUuidV4 };
