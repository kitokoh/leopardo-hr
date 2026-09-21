import { cookies } from "next/headers";
import { NextRequest, NextResponse } from "next/server";

import { resolveBackendBaseUrl } from "@/lib/backend-url";
import {
  SESSION_COOKIE_NAME,
  sessionCookieOptions,
} from "@/lib/session-cookie";

/**
 * Issue #8022 (suivi #7979, pattern #7841 de front/travel-web) — socle commun
 * des route handlers d'authentification du compte acheteur
 * (`account/login` et `account/register`) :
 *   1. relaie les identifiants au backend Laravel (`resolveBackendBaseUrl()`) ;
 *   2. extrait le jeton opaque (`mkb_…`) de la réponse `{ data: { buyer, token } }` ;
 *   3. le pose en cookie httpOnly `leopardo_marche_buyer_token` (Secure,
 *      SameSite=Lax, path=/) — jamais accessible au JS de la page ;
 *   4. renvoie le payload au navigateur SANS le jeton.
 *
 * Les appels authentifiés suivants passent par le proxy same-origin
 * `/api/v1/public/market/account/[...path]` qui relit ce cookie côté serveur
 * et injecte le header `Authorization: Bearer` avant de relayer vers Laravel.
 */

const AUTH_TIMEOUT_MS = 30_000;

const ACCOUNT_BACKEND_PREFIX = "public/market/account";

function jsonError(status: number, message: string): NextResponse {
  return NextResponse.json({ message }, { status });
}

export async function relayAuthAndSetSessionCookie(
  request: NextRequest,
  endpoint: "login" | "register",
): Promise<NextResponse> {
  let body: unknown;
  try {
    body = await request.json();
  } catch {
    return jsonError(400, "Corps de requête invalide.");
  }

  let backendResponse: Response;
  try {
    backendResponse = await fetch(
      `${resolveBackendBaseUrl()}/${ACCOUNT_BACKEND_PREFIX}/${endpoint}`,
      {
        method: "POST",
        headers: {
          Accept: "application/json",
          "Content-Type": "application/json",
          "Accept-Language": request.headers.get("accept-language") || "fr",
        },
        body: JSON.stringify(body),
        cache: "no-store",
        signal: AbortSignal.timeout(AUTH_TIMEOUT_MS),
      },
    );
  } catch {
    return jsonError(502, "Backend unreachable.");
  }

  let payload: unknown;
  try {
    payload = await backendResponse.json();
  } catch {
    return jsonError(502, "Réponse serveur inattendue.");
  }

  // Échec (identifiants invalides, validation 422…) : relayer tel quel — la
  // réponse d'erreur ne contient pas de jeton.
  if (!backendResponse.ok) {
    return NextResponse.json(payload, {
      status: backendResponse.status,
      headers: { "Cache-Control": "no-store" },
    });
  }

  const data =
    payload && typeof payload === "object" && "data" in payload
      ? (payload as { data?: unknown }).data
      : undefined;
  const token =
    data && typeof data === "object" && "token" in data
      ? (data as { token?: unknown }).token
      : undefined;

  if (typeof token !== "string" || token.length === 0) {
    // Succès backend sans jeton : contrat inattendu — ne rien poser et
    // relayer tel quel (le client traitera l'absence de session).
    return NextResponse.json(payload, {
      status: backendResponse.status,
      headers: { "Cache-Control": "no-store" },
    });
  }

  const cookieStore = await cookies();
  cookieStore.set(SESSION_COOKIE_NAME, token, sessionCookieOptions(request));

  // Le jeton ne doit JAMAIS atteindre le JS client : on le retire du payload.
  const { token: _stripped, ...safeData } = data as Record<string, unknown>;
  void _stripped;

  return NextResponse.json(
    { ...(payload as Record<string, unknown>), data: safeData },
    { status: 200, headers: { "Cache-Control": "no-store" } },
  );
}
