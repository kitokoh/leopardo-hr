/**
 * Security fix (#1299): Override the generic /api/v1/[...path] proxy for
 * POST /api/v1/auth/login to implement httpOnly cookie-based token storage.
 *
 * Instead of returning the Bearer token to the browser (where localStorage
 * or any JS code could read it — vulnerable to XSS), this route:
 *   1. Forwards the login credentials to the Laravel backend.
 *   2. Extracts the token from the backend response.
 *   3. Stores it in a httpOnly, Secure, SameSite=Strict cookie named
 *      `leopardo_token` — inaccessible to JavaScript on the page.
 *   4. Returns the user data + success status to the browser, but NOT
 *      the token itself. The cookie carries future authentication silently.
 *
 * Subsequent API calls go through /api/v1/[...path]/route.ts which reads
 * `leopardo_token` from the incoming request cookie and injects it as a
 * Bearer Authorization header before proxying to Laravel.
 *
 * Mobile apps continue to use the token directly (Bearer) — they do not
 * go through this Next.js proxy layer.
 */

import { cookies } from 'next/headers';
import { NextRequest, NextResponse } from 'next/server';

const DEFAULT_BACKEND_API_URL = 'https://gestionemployerbackend.onrender.com/api/v1';
const COOKIE_NAME = 'leopardo_token';
const COOKIE_MAX_AGE = 60 * 60 * 24 * 7; // 7 days — matches Sanctum SANCTUM_TOKEN_EXPIRATION default (10080 min)
const LOGIN_TIMEOUT_MS = 60_000;

function resolveBackendBaseUrl(): string {
  return (
    process.env.API_PROXY_TARGET ||
    process.env.BACKEND_API_URL ||
    process.env.NEXT_PUBLIC_API_URL ||
    DEFAULT_BACKEND_API_URL
  ).replace(/\/$/, '');
}

export async function POST(request: NextRequest): Promise<NextResponse> {
  let body: unknown;

  try {
    body = await request.json();
  } catch {
    return NextResponse.json({ error: 'INVALID_JSON', message: 'Corps de requête invalide.' }, { status: 400 });
  }

  const controller = new AbortController();
  const timeout = setTimeout(() => controller.abort(), LOGIN_TIMEOUT_MS);

  let backendResponse: Response;

  try {
    backendResponse = await fetch(`${resolveBackendBaseUrl()}/auth/login`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'Accept-Language': request.headers.get('Accept-Language') || 'fr',
        'X-Forwarded-For': request.headers.get('x-forwarded-for') || request.headers.get('x-real-ip') || '',
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
          ? 'Le serveur met trop de temps à répondre. Réessayez dans quelques instants.'
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
    return NextResponse.json({ error: 'BACKEND_ERROR', message: 'Réponse serveur inattendue.' }, { status: 502 });
  }

  // 2FA required — pass through without touching token
  if (backendResponse.status === 202 || payload?.error === 'TWO_FA_REQUIRED') {
    return NextResponse.json(payload, { status: backendResponse.status });
  }

  // Login failed — pass through the error to the client
  if (!backendResponse.ok) {
    return NextResponse.json(payload, { status: backendResponse.status });
  }

  // Success — extract the token and store it in a httpOnly cookie
  const token = payload?.token as string | undefined;

  if (!token) {
    // Backend succeeded but returned no token — pass through as-is
    return NextResponse.json(payload, { status: backendResponse.status });
  }

  const isSecure = request.nextUrl.protocol === 'https:' || process.env.NODE_ENV === 'production';

  // Store the token in a httpOnly cookie — never accessible to page JS
  const cookieStore = await cookies();
  cookieStore.set(COOKIE_NAME, token, {
    httpOnly: true,
    secure: isSecure,
    sameSite: 'strict',
    maxAge: COOKIE_MAX_AGE,
    path: '/',
  });

  // Return user data to the browser, but strip the raw token from the JSON
  // payload. The client only needs the user object and confirmation of success.
  const { token: _stripped, ...safePayload } = payload;

  return NextResponse.json(safePayload, {
    status: 200,
    headers: { 'Cache-Control': 'no-store' },
  });
}
