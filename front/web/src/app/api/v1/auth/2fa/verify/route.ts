/**
 * Proxy serveur pour la vérification du code 2FA (#5612).
 *
 * Même motif que /api/v1/auth/login/route.ts : on intercepte la réponse
 * du backend, on extrait le token Sanctum et on le pose dans un cookie
 * httpOnly pour que le JS de la page ne puisse jamais le lire.
 *
 * Flux :
 *   POST /api/v1/auth/2fa/verify
 *     ← { challenge_token, code, device_name?, remember_device? }
 *   → Forward vers Laravel POST /auth/2fa/verify
 *   ← { data: { id, email }, token, token_type, token_expires_at }
 *   → Extrait token → cookie httpOnly leopardo_token
 *   → Renvoie { data: { id, email } } (sans le token brut)
 */

import { cookies } from "next/headers";
import { NextRequest, NextResponse } from "next/server";
import { resolveBackendBaseUrl } from "@/lib/backend-url";

const COOKIE_NAME = "leopardo_token";
const COOKIE_MAX_AGE = 60 * 60 * 24 * 7; // 7 jours
const VERIFY_TIMEOUT_MS = 30_000;

export async function POST(request: NextRequest): Promise<NextResponse> {
  let body: unknown;

  try {
    body = await request.json();
  } catch {
    return NextResponse.json({ error: "INVALID_JSON", message: "Corps de requête invalide." }, { status: 400 });
  }

  const controller = new AbortController();
  const timeout = setTimeout(() => controller.abort(), VERIFY_TIMEOUT_MS);

  let backendResponse: Response;

  try {
    backendResponse = await fetch(`${resolveBackendBaseUrl()}/auth/2fa/verify`, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
        "Accept-Language": request.headers.get("Accept-Language") || "fr",
      },
      body: JSON.stringify(body),
      signal: controller.signal,
      cache: "no-store",
    });
  } catch (error) {
    clearTimeout(timeout);
    const isTimeout = error instanceof DOMException && error.name === "AbortError";
    return NextResponse.json(
      { error: isTimeout ? "TIMEOUT" : "NETWORK_ERROR", message: isTimeout ? "Le serveur ne répond pas." : "Impossible de contacter le serveur." },
      { status: isTimeout ? 408 : 502 },
    );
  } finally {
    clearTimeout(timeout);
  }

  let payload: Record<string, unknown>;

  try {
    payload = (await backendResponse.json()) as Record<string, unknown>;
  } catch {
    return NextResponse.json({ error: "BACKEND_ERROR", message: "Réponse serveur inattendue." }, { status: 502 });
  }

  if (!backendResponse.ok) {
    return NextResponse.json(payload, { status: backendResponse.status });
  }

  const token = payload?.token as string | undefined;

  if (!token) {
    return NextResponse.json(payload, { status: backendResponse.status });
  }

  const isSecure =
    request.nextUrl.protocol === "https:" || process.env.NODE_ENV === "production";

  const cookieStore = await cookies();
  cookieStore.set(COOKIE_NAME, token, {
    httpOnly: true,
    secure: isSecure,
    sameSite: "strict",
    maxAge: COOKIE_MAX_AGE,
    path: "/",
  });

  // On renvoie uniquement les données user — le token reste côté serveur.
  const { token: _stripped, token_type: _type, token_expires_at: _exp, ...safePayload } = payload;

  return NextResponse.json(safePayload, {
    status: 200,
    headers: { "Cache-Control": "no-store" },
  });
}
