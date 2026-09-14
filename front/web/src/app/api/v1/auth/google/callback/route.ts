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
/** Identité Google vérifiée, en transit vers le formulaire d'inscription. */
const GOOGLE_SIGNUP_COOKIE = "leopardo_google_signup";
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
        // QA onboarding 2026-09-14 : le state anti-CSRF vit dans la session de
        // l'API (`session(['google_oauth_state' => …])`). Sans ce relais, le
        // callback repartait sur une session vierge et TOUT retour de Google
        // échouait en INVALID_OAUTH_STATE (400) — panne masquée jusqu'ici par
        // l'absence des clés OAuth (503 en amont).
        ...(request.headers.get("cookie")
          ? { cookie: request.headers.get("cookie") as string }
          : {}),
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

  // Issue #5171 : le 401 UNKNOWN_ACCOUNT (email Google inconnu) doit afficher
  // le message dédié « demandez une invitation » (labels.login.errors.googleNoAccount),
  // pas l'erreur générique — sinon le parcours invitation-first est muet pour
  // le nouvel utilisateur. Tout autre échec backend reste générique.
  let payload: Record<string, unknown> | null = null;
  try {
    payload = (await backendResponse.json()) as Record<string, unknown>;
  } catch {
    payload = null;
  }

  if (backendResponse.status >= 400) {
    const backendError = typeof payload?.error === 'string' ? payload.error : '';
    const errorCode =
      backendError === 'UNKNOWN_ACCOUNT' ? 'google_no_account' : 'google_auth_failed';

    return NextResponse.redirect(
      new URL(`/auth/login?error=${errorCode}`, request.url),
    );
  }

  // QA onboarding 2026-09-14 — parcours « créer mon compte avec Google » : le
  // backend a vérifié l'identité (email_verified) SANS créer de tenant (#3724).
  // On dépose l'identité dans un cookie httpOnly à usage unique, relu par la
  // page d'inscription via /api/forms/google-signup : l'e-mail ne transite donc
  // jamais dans l'URL.
  const signupData = payload?.data as
    | { signup?: unknown; email?: unknown; first_name?: unknown; last_name?: unknown; plan?: unknown }
    | null
    | undefined;

  if (signupData?.signup === true && typeof signupData.email === 'string' && signupData.email !== '') {
    const isSecureForSignup =
      request.nextUrl.protocol === "https:" || process.env.NODE_ENV === "production";

    const cookieStoreForSignup = await cookies();
    cookieStoreForSignup.set(GOOGLE_SIGNUP_COOKIE, JSON.stringify({
      email: signupData.email,
      first_name: typeof signupData.first_name === 'string' ? signupData.first_name : '',
      last_name: typeof signupData.last_name === 'string' ? signupData.last_name : '',
      plan: typeof signupData.plan === 'string' ? signupData.plan : null,
    }), {
      httpOnly: true,
      secure: isSecureForSignup,
      sameSite: "lax",
      maxAge: 600, // 10 minutes : le temps de remplir le tunnel
      path: "/",
    });

    const signupUrl = new URL("/signup", request.url);
    signupUrl.searchParams.set("google", "1");
    if (typeof signupData.plan === 'string' && signupData.plan !== '') {
      signupUrl.searchParams.set("plan", signupData.plan);
    }

    return NextResponse.redirect(signupUrl);
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
