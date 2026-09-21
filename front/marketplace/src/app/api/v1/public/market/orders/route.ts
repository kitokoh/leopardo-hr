import { NextRequest, NextResponse } from "next/server";

import { resolveBackendBaseUrl } from "@/lib/backend-url";
import { SESSION_COOKIE_NAME } from "@/lib/session-cookie";

/**
 * Issue #8022 — POST /api/v1/public/market/orders (création de commande).
 * Relais same-origin de l'unique appel authentifié HORS préfixe account/ :
 * si le cookie httpOnly `leopardo_marche_buyer_token` est présent, le jeton
 * est injecté en `Authorization: Bearer` côté serveur et la commande est
 * liée au compte (historique + avis vérifiés) ; sinon checkout invité
 * inchangé. Le JS client ne fournit plus jamais le jeton.
 */

const ORDER_TIMEOUT_MS = 30_000;

export async function POST(request: NextRequest): Promise<NextResponse> {
  const body = await request.arrayBuffer();

  const headers = new Headers();
  headers.set("Accept", "application/json");
  const contentType = request.headers.get("content-type");
  if (contentType) headers.set("Content-Type", contentType);
  const acceptLanguage = request.headers.get("accept-language");
  if (acceptLanguage) headers.set("Accept-Language", acceptLanguage);

  const sessionToken = request.cookies.get(SESSION_COOKIE_NAME)?.value;
  if (sessionToken) {
    headers.set("Authorization", `Bearer ${sessionToken}`);
  }

  let backendResponse: Response;
  try {
    backendResponse = await fetch(`${resolveBackendBaseUrl()}/public/market/orders`, {
      method: "POST",
      headers,
      body,
      redirect: "manual",
      cache: "no-store",
      signal: AbortSignal.timeout(ORDER_TIMEOUT_MS),
    });
  } catch {
    return NextResponse.json({ message: "Backend unreachable." }, { status: 502 });
  }

  const responseHeaders = new Headers();
  backendResponse.headers.forEach((value, key) => {
    const lower = key.toLowerCase();
    if (
      !["connection", "content-length", "host", "transfer-encoding"].includes(lower)
    ) {
      responseHeaders.set(key, value);
    }
  });
  responseHeaders.set("Cache-Control", "no-store");

  return new NextResponse(backendResponse.body, {
    status: backendResponse.status,
    headers: responseHeaders,
  });
}
