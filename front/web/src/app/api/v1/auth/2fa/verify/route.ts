/**
 * Issue #5612 — Proxy POST /auth/2fa/verify avec pose du cookie httpOnly.
 *
 * Même convention que /api/v1/auth/login/route.ts :
 *   1. Transmet le corps de la requête au backend Laravel.
 *   2. Sur succès (200), extrait le token Sanctum et le stocke dans le
 *      cookie httpOnly `leopardo_token` — inaccessible au JS côté page.
 *   3. Retourne { data } sans le token brut.
 *
 * Le `challenge_token` est généré par le backend à la connexion lorsque
 * la 2FA est activée (TwoFactorAuthController::verify — #5436).
 */

import { cookies } from 'next/headers';
import { NextRequest, NextResponse } from 'next/server';

import { resolveBackendBaseUrl } from '@/lib/backend-url';

const COOKIE_NAME = 'leopardo_token';
const COOKIE_MAX_AGE = 60 * 60 * 24 * 7; // 7 jours — cohérent avec login
const VERIFY_TIMEOUT_MS = 30_000;

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

  if (!backendResponse.ok) {
    return NextResponse.json(payload, { status: backendResponse.status });
  }

  // Succès — extraire le token et le stocker dans le cookie httpOnly.
  const token = payload?.token as string | undefined;

  if (!token) {
    return NextResponse.json(payload, { status: backendResponse.status });
  }

  const isSecure =
    request.nextUrl.protocol === 'https:' || process.env.NODE_ENV === 'production';

  const cookieStore = await cookies();
  cookieStore.set(COOKIE_NAME, token, {
    httpOnly: true,
    secure: isSecure,
    sameSite: 'strict',
    maxAge: COOKIE_MAX_AGE,
    path: '/',
  });

  // Retourner les données utilisateur sans le token brut.
  const { token: _stripped, token_type: _type, token_expires_at: _exp, ...safePayload } = payload;

  return NextResponse.json(safePayload, {
    status: 200,
    headers: { 'Cache-Control': 'no-store' },
  });
}
