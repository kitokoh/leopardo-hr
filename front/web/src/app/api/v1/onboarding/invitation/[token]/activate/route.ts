/**
 * #R3 — Proxy d'activation de compte (auto-login post-activation).
 *
 * Même architecture que /api/v1/auth/login/route.ts (#1299) :
 *   1. Transmet les credentials au backend Laravel.
 *   2. Extrait le token Sanctum de la réponse (data.token).
 *   3. Le stocke dans un cookie httpOnly `leopardo_token` — jamais exposé au JS.
 *   4. Retourne les données utilisateur sans le token brut.
 *
 * Avant ce proxy, l'activation retournait un token Sanctum que le frontend
 * ignorait, obligeant l'utilisateur à se re-connecter manuellement.
 */

import { cookies } from 'next/headers';
import { NextRequest, NextResponse } from 'next/server';

import { resolveBackendBaseUrl } from '@/lib/backend-url';

const COOKIE_NAME = 'leopardo_token';
const COOKIE_MAX_AGE = 60 * 60 * 24 * 7; // 7 jours (cohérent avec la route login)

export async function POST(
  request: NextRequest,
  { params }: { params: { token: string } },
): Promise<NextResponse> {
  const { token } = params;

  let body: unknown;
  try {
    body = await request.json();
  } catch {
    return NextResponse.json(
      { error: 'INVALID_JSON', message: 'Corps de requête invalide.' },
      { status: 400 },
    );
  }

  const backendUrl = `${resolveBackendBaseUrl()}/api/v1/onboarding/invitation/${encodeURIComponent(token)}/activate`;

  let backendResponse: Response;
  try {
    backendResponse = await fetch(backendUrl, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'Accept-Language': request.headers.get('Accept-Language') ?? 'fr',
      },
      body: JSON.stringify(body),
    });
  } catch {
    return NextResponse.json(
      { error: 'BACKEND_UNREACHABLE', message: 'Serveur inaccessible.' },
      { status: 502 },
    );
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

  // Activation échouée — passer l'erreur telle quelle au client.
  if (!backendResponse.ok) {
    return NextResponse.json(payload, { status: backendResponse.status });
  }

  // Succès — extraire le token Sanctum imbriqué dans data.token.
  const data = payload?.data as Record<string, unknown> | undefined;
  const sanctumToken = data?.token as string | undefined;

  if (sanctumToken) {
    const isSecure =
      request.nextUrl.protocol === 'https:' || process.env.NODE_ENV === 'production';

    const cookieStore = await cookies();
    cookieStore.set(COOKIE_NAME, sanctumToken, {
      httpOnly: true,
      secure: isSecure,
      sameSite: 'strict',
      maxAge: COOKIE_MAX_AGE,
      path: '/',
    });
  }

  // Retourner les données utilisateur sans le token brut.
  const { token: _stripped, ...safeData } = data ?? {};
  const safePayload = { ...payload, data: safeData };

  return NextResponse.json(safePayload, {
    status: 201,
    headers: { 'Cache-Control': 'no-store' },
  });
}
