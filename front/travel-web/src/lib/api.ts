import type {
  AccountAuthPayload,
  Booking,
  BookingInput,
  BookingResponse,
  CustomerAccount,
  CustomerBooking,
  MarketplaceCity,
  MarketplaceTrip,
  MarketplaceTripDetail,
  SearchMeta,
  TrackedBooking,
} from "@/lib/types";
import { resolveBackendBaseUrl } from "@/lib/backend-url";

const MARKETPLACE = "public/travel/marketplace";

export class ApiError extends Error {
  readonly status: number;
  readonly payload: unknown;

  constructor(status: number, message: string, payload: unknown = null) {
    super(message);
    this.name = "ApiError";
    this.status = status;
    this.payload = payload;
  }
}

async function parseJson(response: Response): Promise<unknown> {
  const text = await response.text();
  if (!text) return null;
  try {
    return JSON.parse(text) as unknown;
  } catch {
    return null;
  }
}

function extractMessage(payload: unknown, fallback: string): string {
  if (payload && typeof payload === "object" && "message" in payload) {
    const message = (payload as { message?: unknown }).message;
    if (typeof message === "string" && message.length > 0) return message;
  }
  return fallback;
}

async function request<T>(url: string, init?: RequestInit): Promise<T> {
  const response = await fetch(url, {
    ...init,
    headers: {
      Accept: "application/json",
      ...(init?.body ? { "Content-Type": "application/json" } : {}),
      ...init?.headers,
    },
    // #7841 — session en cookie httpOnly : les appels same-origin doivent
    // toujours embarquer les cookies (comportement fetch par défaut, rendu
    // explicite ici — le token n'est plus jamais attaché par le JS client).
    credentials: "same-origin",
    cache: "no-store",
  });

  const payload = await parseJson(response);

  if (!response.ok) {
    throw new ApiError(
      response.status,
      extractMessage(payload, `HTTP ${response.status}`),
      payload,
    );
  }

  return payload as T;
}

// ── Côté SERVEUR (pages SSR) : appel direct du backend ─────────────────────

export async function searchTripsServer(params: {
  origin_city_id?: string;
  destination_city_id?: string;
  date?: string;
  per_page?: string;
}): Promise<{ data: MarketplaceTrip[]; meta: SearchMeta }> {
  const query = new URLSearchParams();
  if (params.origin_city_id) query.set("origin_city_id", params.origin_city_id);
  if (params.destination_city_id) {
    query.set("destination_city_id", params.destination_city_id);
  }
  if (params.date) query.set("date", params.date);
  if (params.per_page) query.set("per_page", params.per_page);

  const suffix = query.size > 0 ? `?${query.toString()}` : "";
  return request(`${resolveBackendBaseUrl()}/${MARKETPLACE}/trips${suffix}`);
}

export async function getTripServer(
  id: string,
): Promise<{ data: MarketplaceTripDetail }> {
  return request(`${resolveBackendBaseUrl()}/${MARKETPLACE}/trips/${id}`);
}

export async function getCitiesServer(): Promise<{ data: MarketplaceCity[] }> {
  return request(`${resolveBackendBaseUrl()}/${MARKETPLACE}/cities`);
}

// ── Côté NAVIGATEUR : proxy same-origin `/api/v1/*` ────────────────────────

export async function fetchCities(): Promise<MarketplaceCity[]> {
  const payload = await request<{ data: MarketplaceCity[] }>(
    `/api/v1/${MARKETPLACE}/cities`,
  );
  return payload.data;
}

export async function createBooking(input: BookingInput): Promise<{
  booking: Booking;
  agencyName: string | null;
}> {
  const payload = await request<BookingResponse>(
    `/api/v1/${MARKETPLACE}/bookings`,
    {
      method: "POST",
      body: JSON.stringify(input),
      // #7739/#7841 — client connecté : la réservation est rattachée à son
      // compte à la création. Le proxy same-origin lit le cookie httpOnly de
      // session et injecte le Bearer côté serveur (le checkout invité reste
      // possible sans session).
    },
  );
  return {
    booking: payload.data,
    agencyName: payload.agency?.name ?? null,
  };
}

export async function trackBooking(
  reference: string,
  code: string,
): Promise<TrackedBooking> {
  const payload = await request<{ data: TrackedBooking }>(
    `/api/v1/${MARKETPLACE}/bookings/${encodeURIComponent(reference)}?code=${encodeURIComponent(code)}`,
  );
  return payload.data;
}

export async function cancelBooking(
  reference: string,
  code: string,
  reason: string,
): Promise<TrackedBooking> {
  const payload = await request<{ data: TrackedBooking }>(
    `/api/v1/${MARKETPLACE}/bookings/${encodeURIComponent(reference)}/cancel`,
    { method: "POST", body: JSON.stringify({ code, reason }) },
  );
  return payload.data;
}

export async function getTicketPdfUrl(
  ticketId: number,
  code: string,
): Promise<string | null> {
  const payload = await request<{
    data: { pdf_url?: string | null };
  }>(
    `/api/v1/${MARKETPLACE}/tickets/${ticketId}/pdf?code=${encodeURIComponent(code)}`,
  );
  return payload.data.pdf_url ?? null;
}

// ── Compte client grand public (issue #7739) ────────────────────────────────
// Auth par token Sanctum du guard DÉDIÉ `travel_customer`, conservé depuis
// #7841 dans un cookie httpOnly posé par les route handlers Next
// (login/register) et relayé en Bearer par le proxy same-origin uniquement
// sur la surface `account/*` (fail-closed). Le JS client ne voit ni ne
// transmet jamais le token.

const ACCOUNT = `${MARKETPLACE}/account`;

export async function registerAccount(input: {
  name: string;
  email: string;
  phone?: string;
  password: string;
}): Promise<AccountAuthPayload> {
  const payload = await request<{ data: AccountAuthPayload }>(
    `/api/v1/${ACCOUNT}/register`,
    { method: "POST", body: JSON.stringify(input) },
  );
  return payload.data;
}

export async function loginAccount(input: {
  email: string;
  password: string;
}): Promise<AccountAuthPayload> {
  const payload = await request<{ data: AccountAuthPayload }>(
    `/api/v1/${ACCOUNT}/login`,
    { method: "POST", body: JSON.stringify(input) },
  );
  return payload.data;
}

export async function logoutAccount(): Promise<void> {
  await request<{ data: { logged_out: boolean } }>(`/api/v1/${ACCOUNT}/logout`, {
    method: "POST",
  });
}

export async function fetchAccount(): Promise<CustomerAccount> {
  const payload = await request<{ data: { account: CustomerAccount } }>(
    `/api/v1/${ACCOUNT}/me`,
  );
  return payload.data.account;
}

export async function fetchMyBookings(
  perPage = 50,
): Promise<{ data: CustomerBooking[]; meta: SearchMeta }> {
  return request<{ data: CustomerBooking[]; meta: SearchMeta }>(
    `/api/v1/${ACCOUNT}/bookings?per_page=${perPage}`,
  );
}
