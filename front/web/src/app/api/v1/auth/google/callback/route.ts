import { cookies } from "next/headers";
import { NextRequest, NextResponse } from "next/server";

import { resolveBackendBaseUrl } from "@/lib/backend-url";

/**
 * QA #2277 — callback Google OAuth côté vitrine.
 *
 * Avant : le bouton Google pointait directement vers l'API
 * (`${API}/auth/google`) et `GOOGLE_REDIRECT_URL` ramenait l'utilisateur sur
 * `api.leopardo-rh.com/api/v1/auth/google/callback` → le cookie de session
 * (`leopardo_token`) était posé sur le domaine API, jamais sur le domaine
 * vitrine → session perdue après redirection.
 *
 * Désormais : le bouton passe par le proxy (`/api/v1/auth/google`), Google
 * redirige vers CE callback (GOOGLE_REDIRECT_URL = origine vitrine), qui
 * échange le code auprès du backend, extrait le token et le pose en cookie
 * httpOnly sur le domaine vitrine (même mécanique que POST /auth/login,
 * fix #1299), puis redirige vers /dashboard.
 */
const COOKIE_NAME = "leopardo_token";
const COOKIE_MAX_AGE = 60 * 60 * 24 * 7; // 7 jours — aligné sur Sanctum
const GOOGLE_TIMEOUT_MS = 60_000;

export async function GET(request: NextRequest): Promise<NextResponse> {
  const code = request.nextUrl.searchParams.get("code");

  if (!code) {
    return NextResponse.redirect(
      new URL("/auth/login?error=google", request.url),
    );
  }

  const controller = new AbortController();
  const timeout = setTimeout(() => controller.abort(), GOOGLE_TIMEOUT_MS);

  let backendResponse: Response;

  try {
    const backendUrl = new URL(
      `${resolveBackendBaseUrl()}/auth/google/callback`,
    );
    backendUrl.search = request.nextUrl.search;

    backendResponse = await fetch(backendUrl.toString(), {
      method: "GET",
      headers: {
        Accept: "application/json",
        "Accept-Language": request.headers.get("Accept-Language") || "fr",
      },
      redirect: "manual",
      cache: "no-store",
      signal: controller.signal,
    });
  } catch {
    clearTimeout(timeout);
    return NextResponse.redirect(
      new URL("/auth/login?error=google_network", request.url),
    );
  } finally {
    clearTimeout(timeout);
  }

  if (backendResponse.status >= 400) {
    return NextResponse.redirect(
      new URL("/auth/login?error=google_auth_failed", request.url),
    );
  }

  let payload: Record<string, unknown>;
  try {
    payload = (await backendResponse.json()) as Record<string, unknown>;
  } catch {
    return NextResponse.redirect(
      new URL("/auth/login?error=google_auth_failed", request.url),
    );
  }

  const token = payload?.token as string | undefined;

  if (!token) {
    return NextResponse.redirect(
      new URL("/auth/login?error=google_no_account", request.url),
    );
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

  return NextResponse.redirect(new URL("/dashboard", request.url));
}
