import { NextRequest, NextResponse } from 'next/server';
import { cookies } from 'next/headers';

import { resolveBackendBaseUrl } from '@/lib/backend-url';
import { rescopeSessionCookie } from '@/lib/cookie-scope';
import { isValidSessionTokenShape } from '@/lib/session-token';
import { getSiteUrl } from '@/lib/site';

const SESSION_COOKIE_NAME = 'leopardo_token';

// #7491 — session glissante 30 jours : quand l'API pivote le token Sanctum
// (TokenAutoRefreshMiddleware), le nouveau token est re-posé en cookie
// httpOnly avec la même durée que le login.
const ROTATED_COOKIE_MAX_AGE = 60 * 60 * 24 * 30;

const HOP_BY_HOP_HEADERS = new Set([
  'connection',
  'content-length',
  'host',
  'keep-alive',
  'proxy-authenticate',
  'proxy-authorization',
  'te',
  'trailer',
  'transfer-encoding',
  'upgrade',
]);

// resolveBackendBaseUrl importé depuis @/lib/backend-url (audit #1701)

/**
 * Chemins d'INITIATION du flux OAuth Google (le navigateur y est envoyé par un
 * lien). `/auth/google/callback` est exclu : il est déjà servi par sa propre
 * route, qui pose le cookie de session (QA #2277).
 */
function isGoogleAuthInitiation(path: string[]): boolean {
  return path.length === 2 && path[0] === 'auth' && path[1] === 'google';
}

function isRedirectStatus(status: number): boolean {
  return status >= 300 && status < 400;
}

function toBackendUrl(request: NextRequest, path: string[]): string {
  const url = new URL(request.url);
  const backendUrl = new URL(`${resolveBackendBaseUrl()}/${path.join('/')}`);
  backendUrl.search = url.search;
  return backendUrl.toString();
}

function proxyHeaders(request: NextRequest, sessionToken?: string): Headers {
  const headers = new Headers(request.headers);

  for (const header of Array.from(headers.keys())) {
    if (HOP_BY_HOP_HEADERS.has(header.toLowerCase())) {
      headers.delete(header);
    }
  }

  headers.set('Accept', headers.get('Accept') || 'application/json');

  // Security fix (#1299): inject the httpOnly session cookie as a Bearer
  // Authorization header so the token never flows through client-side JS.
  // The browser cannot read `leopardo_token` (httpOnly), but the Next.js
  // server-side proxy reads it here and adds the Authorization header.
  // If the request already carries an explicit Authorization header
  // (e.g. from mobile or server-side fetch), it is preserved unchanged.
  if (sessionToken && !headers.has('authorization')) {
    headers.set('Authorization', `Bearer ${sessionToken}`);
  }

  return headers;
}

async function proxy(request: NextRequest, context: { params: Promise<{ path: string[] }> }) {
  const { path } = await context.params;
  const method = request.method.toUpperCase();
  const body = method === 'GET' || method === 'HEAD' ? undefined : await request.arrayBuffer();

  // Read the httpOnly session cookie — only accessible server-side
  const cookieStore = await cookies();
  const sessionToken = cookieStore.get(SESSION_COOKIE_NAME)?.value;

  // Issue #3523 : panne backend (DNS/socket/timeout) → exception non gérée
  // → page HTML Next au lieu d'une 502 JSON parseable. Wrapper + timeout.
  let response: Response;
  try {
    response = await fetch(toBackendUrl(request, path), {
      method,
      headers: proxyHeaders(request, sessionToken),
      body,
      redirect: 'manual',
      cache: 'no-store',
      signal: AbortSignal.timeout(15_000),
    });
  } catch (error) {
    console.error('[api-proxy] backend unreachable', {
      path: path.join('/'),
      method,
      error: error instanceof Error ? error.message : String(error),
    });

    // Même raison que plus bas pour l'initiation Google : un lien de
    // navigation ne doit jamais rendre du JSON.
    if (isGoogleAuthInitiation(path)) {
      return NextResponse.redirect(new URL('/auth/login?error=google_network', request.url));
    }

    return Response.json(
      {
        error: 'backend_unavailable',
        message: 'Le service backend est temporairement indisponible.',
      },
      { status: 502 },
    );
  }

  const headers = new Headers(response.headers);
  headers.delete('content-encoding');
  headers.delete('content-length');
  headers.set('Cache-Control', 'no-store');

  // QA onboarding 2026-09-14 : le bouton « Continuer avec Google » est un LIEN
  // de navigation. Quand le backend ne peut pas démarrer le flux OAuth, il
  // répond une erreur JSON (503 GOOGLE_OAUTH_NOT_CONFIGURED, 500, …) : la
  // relayer telle quelle affichait une page de JSON brut à l'utilisateur, qui
  // n'avait plus aucun chemin de retour vers le formulaire. On renvoie la
  // personne sur /auth/login avec un code d'erreur — la page sait déjà
  // afficher `google_auth_failed` (bannière dédiée, issue #5173).
  if (isGoogleAuthInitiation(path) && isRedirectStatus(response.status)) {
    const setCookies =
      typeof response.headers.getSetCookie === 'function' ? response.headers.getSetCookie() : [];

    if (setCookies.length > 0) {
      headers.delete('set-cookie');
      for (const cookie of setCookies) {
        headers.append('set-cookie', rescopeSessionCookie(cookie, request.nextUrl.protocol === 'https:'));
      }
    }
  }

  if (isGoogleAuthInitiation(path) && !isRedirectStatus(response.status)) {
    console.error('[api-proxy] google oauth initiation failed', {
      status: response.status,
    });

    return NextResponse.redirect(new URL('/auth/login?error=google_auth_failed', request.url));
  }

  // #7491 — session glissante : quand l'API a pivoté le token Sanctum
  // (TokenAutoRefreshMiddleware → en-tête `X-Token-Refreshed` + corps
  // `_auth.token`), le proxy re-pose le cookie httpOnly avec le NOUVEAU
  // token. Sans cela, le cookie conservait l'ancien token RÉVOQUÉ : la
  // session web mourrait à l'échéance malgré la rotation, et le token en
  // clair transitait jusqu'au JS de la page (le cookie httpOnly perd son
  // intérêt). Le token est retiré du corps relayé au navigateur.
  if (response.headers.get('X-Token-Refreshed') === 'true') {
    const rotated = await readRotatedToken(response);
    if (rotated) {
      const isSecure =
        request.nextUrl.protocol === 'https:' || process.env.NODE_ENV === 'production';

      const next = NextResponse.json(rotated.safeBody, {
        status: response.status,
        headers,
      });
      next.cookies.set(SESSION_COOKIE_NAME, rotated.token, {
        httpOnly: true,
        secure: isSecure,
        sameSite: 'strict',
        maxAge: ROTATED_COOKIE_MAX_AGE,
        path: '/',
      });
      return next;
    }
    // Corps non conforme malgré l'en-tête : on relaie la réponse telle
    // quelle — la session s'arrêtera à l'échéance, comme avant #7491.
  }

  return new Response(response.body, {
    status: response.status,
    statusText: response.statusText,
    headers,
  });
}

/**
 * Lit le pivot de token Sanctum (#7491). Retourne le nouveau token et le corps
 * assaini (token retiré), ou `null` si le corps n'est pas conforme — la
 * réponse d'origine n'est alors PAS consommée (clone) et peut être relayée.
 */
async function readRotatedToken(
  response: Response,
): Promise<{ token: string; safeBody: Record<string, unknown> } | null> {
  try {
    const payload = (await response.clone().json()) as Record<string, unknown> & {
      _auth?: { token_refreshed?: unknown; token?: unknown; expires_at?: unknown };
    };

    const auth = payload?._auth;
    if (
      auth?.token_refreshed !== true ||
      typeof auth.token !== 'string' ||
      !isValidSessionTokenShape(auth.token)
    ) {
      return null;
    }

    const { _auth: _stripped, ...rest } = payload;

    return {
      token: auth.token,
      safeBody: {
        ...rest,
        _auth: {
          token_refreshed: true,
          expires_at: typeof auth.expires_at === 'string' ? auth.expires_at : undefined,
        },
      },
    };
  } catch {
    return null;
  }
}

export async function GET(request: NextRequest, context: { params: Promise<{ path: string[] }> }) {
  return proxy(request, context);
}

export async function POST(request: NextRequest, context: { params: Promise<{ path: string[] }> }) {
  return proxy(request, context);
}

export async function PUT(request: NextRequest, context: { params: Promise<{ path: string[] }> }) {
  return proxy(request, context);
}

export async function PATCH(request: NextRequest, context: { params: Promise<{ path: string[] }> }) {
  return proxy(request, context);
}

export async function DELETE(request: NextRequest, context: { params: Promise<{ path: string[] }> }) {
  return proxy(request, context);
}

const ALLOWED_CORS_ORIGINS = [
  getSiteUrl(),
  'https://leopardo-rh.com',
  'https://www.leopardo-rh.com',
  'http://localhost:3000',
];

export async function OPTIONS(request: NextRequest) {
  // Audit #1701 : plus de wildcard — on n'echo que les origines connues.
  const origin = request.headers.get('origin') || '';
  const allowOrigin = ALLOWED_CORS_ORIGINS.includes(origin) ? origin : '';

  return new Response(null, {
    status: 204,
    headers: {
      'Access-Control-Allow-Headers': 'Authorization, Content-Type, Accept, Accept-Language, If-None-Match, Idempotency-Key, Idempotent-Replayed',
      'Access-Control-Allow-Methods': 'GET, POST, PUT, PATCH, DELETE, OPTIONS',
      ...(allowOrigin ? { 'Access-Control-Allow-Origin': allowOrigin, 'Vary': 'Origin' } : {}),
      'Cache-Control': 'no-store',
    },
  });
}
