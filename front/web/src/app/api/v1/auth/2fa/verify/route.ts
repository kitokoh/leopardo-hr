/**
 * #5612 — Proxy POST /auth/2fa/verify : vérifie le code TOTP ou le code de
 * récupération et pose le cookie httpOnly `leopardo_token`.
 *
 * Même architecture que /api/v1/auth/login/route.ts (#1299) :
 *   1. Transmet le challenge_token + code au backend Laravel.
 *   2. Extrait le token Sanctum de la réponse (champ racine `token`).
 *   3. Le stocke dans `leopardo_token` httpOnly, Secure, SameSite=Strict.
 *   4. Retourne les données utilisateur sans le token brut.
 *
 * Gestion remember_device : si le backend retourne un Set-Cookie
 * `mfa_remember_*`, le proxy le transfère au navigateur tel quel pour
 * que les connexions futures sautent le challenge.
 */

import { cookies } from 'next/headers';
import { NextRequest, NextResponse } from 'next/server';

import { resolveBackendBaseUrl } from '@/lib/backend-url';

const COOKIE_NAME = 'leopardo_token';
const COOKIE_MAX_AGE = 60 * 60 * 24 * 7; // 7 jours — cohérent avec login proxy
const VERIFY_TIMEOUT_MS = 15_000;

export async function POST(request: NextRequest): Promise<NextResponse> {
  let body: unknown;
  try {
    body = await request.json();
  } catch {
    return NextResponse.json(
      { error: 'INVALID_JSON', message: 'Corps de requête invalide.' },
      { status: 400 },
    );
  }

  const controller = new AbortController();
  const timeout = setTimeout(() => controller.abort(), VERIFY_TIMEOUT_MS);

  let backendResponse: Response;
  try {
    backendResponse = await fetch(`${resolveBackendBaseUrl()}/auth/2fa/verify`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'Accept-Language': request.headers.get('Accept-Language') || 'fr',
      },
      body: JSON.stringify(body),
      signal: controller.signal,
      cache: 'no-store',
    });
  } catch (error) {
    clearTimeout(timeout);
    const isTimeout = error instanceof DOMException && error.name === 'AbortError';
    return NextResponse.json(
      {
        error: isTimeout ? 'TIMEOUT' : 'NETWORK_ERROR',
        message: isTimeout
          ? 'Le serveur met trop de temps à répondre.'
          : 'Impossible de contacter le serveur.',
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
      { error: 'BACKEND_ERROR', message: 'Réponse serveur inattendue.' },
      { status: 502 },
    );
  }

  // Échec du backend (code invalide, challenge expiré…) — passer l'erreur.
  if (!backendResponse.ok) {
    return NextResponse.json(payload, { status: backendResponse.status });
  }

  // Extraire le token Sanctum (champ racine, même convention que /auth/login).
  const token = payload?.token as string | undefined;

  if (!token) {
    return NextResponse.json(
      { error: 'MISSING_TOKEN', message: 'Token manquant dans la réponse.' },
      { status: 502 },
    );
  }

  const isSecure =
    request.nextUrl.protocol === 'https:' || process.env.NODE_ENV === 'production';

  // Poser le cookie d'authentification principal — httpOnly, inaccessible au JS.
  const cookieStore = await cookies();
  cookieStore.set(COOKIE_NAME, token, {
    httpOnly: true,
    secure: isSecure,
    sameSite: 'strict',
    maxAge: COOKIE_MAX_AGE,
    path: '/',
  });

  // Transférer les cookies remember_device posés par le backend (#5436).
  // Leur nom est `mfa_remember_{employee_id}` et leur valeur est un HMAC
  // calculé par Laravel — on ne peut pas les recalculer ici, on les relaie.
  const rawSetCookie = backendResponse.headers.get('set-cookie');
  const rememberDeviceResponse = NextResponse.json(
    // Retourner les données user sans le token brut.
    (() => {
      const { token: _stripped, ...safePayload } = payload;
      return safePayload;
    })(),
    { status: 200, headers: { 'Cache-Control': 'no-store' } },
  );

  if (rawSetCookie && rawSetCookie.includes('mfa_remember_')) {
    rememberDeviceResponse.headers.append('Set-Cookie', rawSetCookie);
  }

  return rememberDeviceResponse;
}
