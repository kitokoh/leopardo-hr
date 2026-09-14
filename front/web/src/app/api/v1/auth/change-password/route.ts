import { cookies } from 'next/headers';
import { NextRequest, NextResponse } from 'next/server';

import { resolveBackendBaseUrl } from '@/lib/backend-url';

const COOKIE_NAME = 'leopardo_token';
const COOKIE_MAX_AGE = 60 * 60 * 24 * 7; // 7 jours (aligné sur Sanctum)

// `resolveBackendBaseUrl()` est importé de @/lib/backend-url (audit #1701).

/**
 * Changement de mot de passe — route handler dédié.
 *
 * Audit 2026-09-13 : le changement de mot de passe révoque **tous** les jetons
 * Sanctum de l'utilisateur (protection contre les jetons volés, cf.
 * `ChangePasswordAction`) et en émet un nouveau **pour l'appareil courant**.
 * Le proxy générique `/api/v1/[...path]` ne réécrit pas le cookie httpOnly :
 * l'utilisateur aurait donc été déconnecté par sa propre action, avec un jeton
 * déjà révoqué. Cette route récupère le nouveau jeton et le repose en cookie —
 * même mécanisme que `/api/v1/auth/login`.
 */
export async function POST(request: NextRequest): Promise<NextResponse> {
  const cookieStore = await cookies();
  const token = cookieStore.get(COOKIE_NAME)?.value;
  const body = await request.text();
  const acceptLanguage = request.headers.get('accept-language');

  let backendResponse: Response;

  try {
    backendResponse = await fetch(`${resolveBackendBaseUrl()}/auth/change-password`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        ...(token ? { Authorization: `Bearer ${token}` } : {}),
        ...(acceptLanguage ? { 'Accept-Language': acceptLanguage } : {}),
      },
      body,
      cache: 'no-store',
      signal: AbortSignal.timeout(15000),
    });
  } catch {
    return NextResponse.json(
      { success: false, error: 'backend_unavailable' },
      { status: 502, headers: { 'Cache-Control': 'no-store' } },
    );
  }

  const payload = (await backendResponse.json().catch(() => null)) as
    | (Record<string, unknown> & { token?: unknown })
    | null;

  if (!backendResponse.ok || !payload) {
    return NextResponse.json(payload ?? { success: false, error: 'CHANGE_PASSWORD_FAILED' }, {
      status: backendResponse.ok ? 502 : backendResponse.status,
      headers: { 'Cache-Control': 'no-store' },
    });
  }

  const newToken = typeof payload.token === 'string' && payload.token !== '' ? payload.token : null;

  if (newToken) {
    cookieStore.set(COOKIE_NAME, newToken, {
      httpOnly: true,
      secure: request.nextUrl.protocol === 'https:' || process.env.NODE_ENV === 'production',
      sameSite: 'strict',
      maxAge: COOKIE_MAX_AGE,
      path: '/',
    });
  }

  // Le jeton ne doit jamais transiter vers le JavaScript de la page.
  const { token: _stripped, ...safePayload } = payload;

  return NextResponse.json(
    { success: true, sessionRefreshed: newToken !== null, ...safePayload },
    { status: 200, headers: { 'Cache-Control': 'no-store' } },
  );
}
