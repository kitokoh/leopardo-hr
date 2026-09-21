import { NextRequest, NextResponse } from "next/server";

import { resolveBackendBaseUrl } from "@/lib/backend-url";
import { SESSION_COOKIE_NAME } from "@/lib/session-cookie";

/**
 * Issue #8022 — proxy same-origin `/api/v1/public/market/account/*` → backend
 * (pattern #7841 de front/travel-web) : relaie la SEULE surface « compte
 * acheteur authentifié » (`me`, `orders`, `favorites`, `reviews`). Tout autre
 * chemin est refusé (fail-closed).
 *
 * Le jeton acheteur (`mkb_…`) vit dans le cookie httpOnly
 * `leopardo_marche_buyer_token` : ce proxy le lit côté serveur et l'injecte
 * en `Authorization: Bearer`. Le JS de la page ne manipule plus jamais le
 * jeton. Les routes spécifiques `login`/`register`/`logout` priment sur ce
 * proxy (convention de routage Next).
 */

const HOP_BY_HOP_HEADERS = new Set([
  "connection",
  "content-length",
  "host",
  "keep-alive",
  "proxy-authenticate",
  "proxy-authorization",
  "te",
  "trailer",
  "transfer-encoding",
  "upgrade",
  // Jamais de credentials relayés depuis le navigateur (fail-closed) : le
  // jeton est lu côté serveur depuis le cookie httpOnly (#8022).
  "authorization",
  "cookie",
]);

/** Chemins compte relayés (fail-closed) — cf. routes/modules/market_public.php. */
const ALLOWED_PATHS = new Set(["me", "orders", "favorites", "reviews"]);

function isAllowedPath(path: string[]): boolean {
  if (path.length === 1) {
    return ALLOWED_PATHS.has(path[0]);
  }
  // DELETE /favorites/{productId}
  return path.length === 2 && path[0] === "favorites" && /^\d+$/.test(path[1]);
}

const PROXY_TIMEOUT_MS = 30_000;

function toBackendUrl(request: NextRequest, path: string[]): string {
  const url = new URL(request.url);
  const backendUrl = new URL(
    `${resolveBackendBaseUrl()}/public/market/account/${path.map(encodeURIComponent).join("/")}`,
  );
  backendUrl.search = url.search;
  return backendUrl.toString();
}

function proxyHeaders(
  request: NextRequest,
  sessionToken: string | undefined,
): Headers {
  const headers = new Headers();
  const contentType = request.headers.get("content-type");

  headers.set("Accept", request.headers.get("accept") || "application/json");
  if (contentType) {
    headers.set("Content-Type", contentType);
  }
  const acceptLanguage = request.headers.get("accept-language");
  if (acceptLanguage) headers.set("Accept-Language", acceptLanguage);

  if (sessionToken) {
    headers.set("Authorization", `Bearer ${sessionToken}`);
  }

  return headers;
}

async function proxy(
  request: NextRequest,
  context: { params: Promise<{ path: string[] }> },
): Promise<NextResponse> {
  const { path } = await context.params;

  if (!isAllowedPath(path)) {
    return NextResponse.json({ message: "Not found." }, { status: 404 });
  }

  const method = request.method.toUpperCase();
  const body =
    method === "GET" || method === "HEAD"
      ? undefined
      : await request.arrayBuffer();

  // Cookie httpOnly de session — lisible uniquement côté serveur (#8022).
  const sessionToken = request.cookies.get(SESSION_COOKIE_NAME)?.value;

  // Panne backend (DNS/socket/timeout) → 502 JSON parseable, jamais une page
  // HTML d'erreur Next (même piège que front/web, issue #3523).
  let response: Response;
  try {
    response = await fetch(toBackendUrl(request, path), {
      method,
      headers: proxyHeaders(request, sessionToken),
      body,
      redirect: "manual",
      signal: AbortSignal.timeout(PROXY_TIMEOUT_MS),
    });
  } catch {
    return NextResponse.json({ message: "Backend unreachable." }, { status: 502 });
  }

  const responseHeaders = new Headers();
  response.headers.forEach((value, key) => {
    if (!HOP_BY_HOP_HEADERS.has(key.toLowerCase())) {
      responseHeaders.set(key, value);
    }
  });
  responseHeaders.set("Cache-Control", "no-store");

  return new NextResponse(response.body, {
    status: response.status,
    headers: responseHeaders,
  });
}

export const GET = proxy;
export const POST = proxy;
export const DELETE = proxy;
