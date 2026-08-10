/**
 * Security fix (#1299): Override logout to clear the httpOnly session cookie.
 *
 * Forwards the logout to Laravel (invalidates the Sanctum token server-side),
 * then removes the `leopardo_token` cookie from the browser so the session
 * cannot be replayed after logout.
 */

import { cookies } from 'next/headers';
import { NextRequest, NextResponse } from 'next/server';

import { resolveBackendBaseUrl } from '@/lib/backend-url';
const COOKIE_NAME = 'leopardo_token';

// resolveBackendBaseUrl importé depuis @/lib/backend-url (audit #1701)

export async function POST(request: NextRequest): Promise<NextResponse> {
  const cookieStore = await cookies();
  const token = cookieStore.get(COOKIE_NAME)?.value;

  // Best-effort: forward the logout to the backend to invalidate the token
  if (token) {
    try {
      await fetch(`${resolveBackendBaseUrl()}/auth/logout`, {
        method: 'POST',
        headers: {
          'Accept': 'application/json',
          'Authorization': `Bearer ${token}`,
        },
        cache: 'no-store',
      });
    } catch {
      // Ignore network errors — we still clear the cookie locally
    }
  }

  // Always clear the httpOnly cookie regardless of backend response
  cookieStore.set(COOKIE_NAME, '', {
    httpOnly: true,
    secure: request.nextUrl.protocol === 'https:' || process.env.NODE_ENV === 'production',
    sameSite: 'strict',
    maxAge: 0, // Expire immediately
    path: '/',
  });

  return NextResponse.json({ success: true }, {
    status: 200,
    headers: { 'Cache-Control': 'no-store' },
  });
}
