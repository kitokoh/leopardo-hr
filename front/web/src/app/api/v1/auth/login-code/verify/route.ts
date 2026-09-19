/**
 * #7490 — override du proxy générique pour POST /api/v1/auth/login-code/verify.
 *
 * Même posture de sécurité que /api/v1/auth/login (#1299) : le backend renvoie
 * un token Bearer au succès ; on le pose dans le cookie httpOnly
 * `leopardo_token` (inaccessible au JS de la page) et on ne renvoie JAMAIS le
 * token brut au navigateur. Les erreurs (code invalide, verrou, 2FA requise)
 * sont relayées telles quelles — la mise en mots se fait côté client via le
 * catalogue i18n.
 */

import { cookies } from "next/headers";
import { NextRequest, NextResponse } from "next/server";

import { resolveBackendBaseUrl } from "@/lib/backend-url";

const COOKIE_NAME = "leopardo_token";
const COOKIE_MAX_AGE = 60 * 60 * 24 * 30; // 30 jours — aligné sur /auth/login (#7491)
const VERIFY_TIMEOUT_MS = 60_000;

export async function POST(request: NextRequest): Promise<NextResponse> {
  let body: unknown;

  try {
    body = await request.json();
  } catch {
    return NextResponse.json(
      { success: false, error: "INVALID_JSON" },
      { status: 400 },
    );
  }

  const controller = new AbortController();
  const timeout = setTimeout(() => controller.abort(), VERIFY_TIMEOUT_MS);

  let backendResponse: Response;

  try {
    backendResponse = await fetch(
      `${resolveBackendBaseUrl()}/auth/login-code/verify`,
      {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Accept: "application/json",
          "Accept-Language": request.headers.get("Accept-Language") || "fr",
        },
        body: JSON.stringify(body),
        signal: controller.signal,
        cache: "no-store",
      },
    );
  } catch (error) {
    clearTimeout(timeout);
    const isTimeout =
      error instanceof DOMException && error.name === "AbortError";
    return NextResponse.json(
      { success: false, error: isTimeout ? "TIMEOUT" : "NETWORK_ERROR" },
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
      { success: false, error: "BACKEND_ERROR" },
      { status: 502 },
    );
  }

  // Échec (code invalide, verrou 5 tentatives, 2FA requise…) : relayé tel quel.
  if (!backendResponse.ok) {
    return NextResponse.json(payload, { status: backendResponse.status });
  }

  const token = payload?.token as string | undefined;

  if (!token) {
    return NextResponse.json(payload, { status: backendResponse.status });
  }

  const isSecure =
    request.nextUrl.protocol === "https:" ||
    process.env.NODE_ENV === "production";

  const cookieStore = await cookies();
  cookieStore.set(COOKIE_NAME, token, {
    httpOnly: true,
    secure: isSecure,
    sameSite: "strict",
    maxAge: COOKIE_MAX_AGE,
    path: "/",
  });

  // Le token brut ne sort jamais vers le navigateur.
  const { token: _stripped, ...safePayload } = payload;

  return NextResponse.json(safePayload, {
    status: 200,
    headers: { "Cache-Control": "no-store" },
  });
}
