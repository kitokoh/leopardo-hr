import { NextRequest, NextResponse } from "next/server";

import { resolveBackendBaseUrl } from "@/lib/backend-url";

/**
 * Issue #7738 — proxy same-origin `/api/v1/*` → backend (pattern #7297 de
 * front/web), restreint à la SEULE surface publique voyage : ce site n'a ni
 * session ni token, il relaie uniquement les endpoints publics
 * `public/travel/*` (marketplace #7737 + billets/suivi #7395). Tout autre
 * chemin est refusé (fail-closed) — ce proxy ne doit jamais devenir un
 * relais ouvert vers l'API authentifiée.
 */

const ALLOWED_PREFIX = ["public", "travel"] as const;

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
  // Jamais de credentials relayés depuis le navigateur sur cette surface.
  "authorization",
  "cookie",
]);

const PROXY_TIMEOUT_MS = 30_000;

function isAllowedPath(path: string[]): boolean {
  return (
    path.length > ALLOWED_PREFIX.length &&
    path[0] === ALLOWED_PREFIX[0] &&
    path[1] === ALLOWED_PREFIX[1]
  );
}

function toBackendUrl(request: NextRequest, path: string[]): string {
  const url = new URL(request.url);
  const backendUrl = new URL(
    `${resolveBackendBaseUrl()}/${path.map(encodeURIComponent).join("/")}`,
  );
  backendUrl.search = url.search;
  return backendUrl.toString();
}

function proxyHeaders(request: NextRequest): Headers {
  const headers = new Headers();
  const contentType = request.headers.get("content-type");

  headers.set("Accept", request.headers.get("accept") || "application/json");
  if (contentType && !HOP_BY_HOP_HEADERS.has("content-type")) {
    headers.set("Content-Type", contentType);
  }
  const acceptLanguage = request.headers.get("accept-language");
  if (acceptLanguage) headers.set("Accept-Language", acceptLanguage);

  return headers;
}

async function proxy(
  request: NextRequest,
  context: { params: Promise<{ path: string[] }> },
): Promise<NextResponse> {
  const { path } = await context.params;

  if (!isAllowedPath(path)) {
    return NextResponse.json(
      { message: "Not found." },
      { status: 404 },
    );
  }

  const method = request.method.toUpperCase();
  const body =
    method === "GET" || method === "HEAD"
      ? undefined
      : await request.arrayBuffer();

  // Panne backend (DNS/socket/timeout) → 502 JSON parseable, jamais une page
  // HTML d'erreur Next (même piège que front/web, issue #3523).
  let response: Response;
  try {
    response = await fetch(toBackendUrl(request, path), {
      method,
      headers: proxyHeaders(request),
      body,
      redirect: "manual",
      signal: AbortSignal.timeout(PROXY_TIMEOUT_MS),
    });
  } catch {
    return NextResponse.json(
      { message: "Backend unreachable." },
      { status: 502 },
    );
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
