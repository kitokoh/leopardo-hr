/**
 * 2FA challenge verification (issue #5612) — POST /api/v1/auth/2fa/verify
 *
 * Même modèle de sécurité que /api/v1/auth/login (fix #1299) : le flux 2FA
 * ne doit jamais exposer le token Sanctum au JavaScript navigateur.
 *
 *   1. Forward le challenge (code TOTP ou code de récupération) au backend.
 *   2. Extrait le token de la réponse et le stocke dans le cookie httpOnly
 *      `leopardo_token` (inaccessible au JS).
 *   3. Re-transmet le cookie `mfa_remember_*` posé par le backend quand
 *      `remember_device=true` (appareil de confiance, login sans challenge).
 *   4. Retourne au navigateur le payload sans le token brut.
 *
 * Erreurs backend (TWO_FA_INVALID, CHALLENGE_EXPIRED, …) pass-through avec
 * leur statut — le client les affiche via getApiErrorMessage (×4 locales).
 */

import { cookies } from "next/headers";
import { NextRequest, NextResponse } from "next/server";

import { resolveBackendBaseUrl } from "@/lib/backend-url";
import { t as i18nT } from "@/lib/i18n/locale-catalog";
import { normalizeLocale } from "@/lib/i18n";

const COOKIE_NAME = "leopardo_token";
const COOKIE_MAX_AGE = 60 * 60 * 24 * 7; // 7 jours — aligné login/route.ts
const REMEMBER_COOKIE_MAX_AGE = 60 * 60 * 24 * 30; // 30 jours — aligné backend
const VERIFY_TIMEOUT_MS = 30_000;

export async function POST(request: NextRequest): Promise<NextResponse> {
  const locale = normalizeLocale(request.headers.get("Accept-Language"));

  let body: unknown;
  try {
    body = await request.json();
  } catch {
    return NextResponse.json(
      {
        error: "INVALID_JSON",
        message: i18nT(locale, "api.login_invalid_json", "Corps de requête invalide."),
      },
      { status: 400 },
    );
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
      {
        error: isTimeout ? "TIMEOUT" : "NETWORK_ERROR",
        message: isTimeout
          ? i18nT(locale, "api.login_timeout", "Le serveur met trop de temps à répondre. Réessayez dans quelques instants.")
          : i18nT(locale, "api.login_network_error", "Impossible de contacter le serveur."),
      },
      { status: isTimeout ? 408 : 502 },
    );
  } finally {
    clearTimeout(timeout);
  }

  let payload: Record<string, unknown>;
  try {
    payload = (await backendResponse.json()) as Record<string, unknown>;
  } catch {
    return NextResponse.json(
      {
        error: "BACKEND_ERROR",
        message: i18nT(locale, "api.login_backend_error", "Réponse serveur inattendue."),
      },
      { status: 502 },
    );
  }

  // Échec du challenge (code invalide / expiré) — pass-through des erreurs.
  if (!backendResponse.ok) {
    return NextResponse.json(payload, { status: backendResponse.status });
  }

  const token = payload?.token as string | undefined;
  if (!token) {
    // Succès backend sans token — pass-through tel quel.
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

  // remember_device : re-transmettre le cookie mfa_remember_* posé par le
  // backend (le proxy Next.js ne laisse pas passer les Set-Cookie Laravel).
  const setCookie = backendResponse.headers.get("set-cookie");
  if (setCookie) {
    const match = setCookie.match(/(mfa_remember_[^=;]+)=([^;]+)/);
    if (match && match[1] && match[2]) {
      cookieStore.set(match[1], match[2], {
        httpOnly: true,
        secure: isSecure,
        sameSite: "lax",
        maxAge: REMEMBER_COOKIE_MAX_AGE,
        path: "/",
      });
    }
  }

  // Ne jamais renvoyer le token brut au navigateur (cookie httpOnly suffit).
  const { token: _stripped, ...safePayload } = payload;

  return NextResponse.json(safePayload, {
    status: 200,
    headers: { "Cache-Control": "no-store" },
  });
}
