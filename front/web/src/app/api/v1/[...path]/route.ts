import { NextRequest, NextResponse } from 'next/server';
import { cookies } from 'next/headers';

import { resolveBackendBaseUrl } from '@/lib/backend-url';
import { getSiteUrl } from '@/lib/site';

const SESSION_COOKIE_NAME = 'leopardo_token';

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

/**
 * Rescope un cookie de session posé par l'API sur l'origine qui répond
 * (la vitrine).
 *
 * QA onboarding 2026-09-14 : le state anti-CSRF du flux Google vit dans la
 * session de l'API, et cette session doit revenir au callback pour être
 * validée. Or l'API émet son `Set-Cookie` avec SON domaine : relayé tel quel
 * à travers le proxy, le navigateur le refuse sur le domaine vitrine → la
 * session était perdue et tout retour de Google finissait en
 * `INVALID_OAUTH_STATE`. On retire donc `Domain` (le cookie appartient alors à
 * l'hôte qui répond), on force `Path=/`, et on garde le reste (HttpOnly,
 * Secure, Max-Age) inchangé.
 */
function rescopeSessionCookie(cookie: string, request: NextRequest): string {
  const isHttps = request.nextUrl.protocol === 'https:';
  const kept: string[] = [];

  for (const part of cookie.split(';')) {
    const trimmed = part.trim();
    if (trimmed === '') continue;

    const attribute = trimmed.split('=')[0].trim().toLowerCase();
    if (['domain', 'path', 'samesite'].includes(attribute)) continue;
    // Un cookie `Secure` ne peut pas être posé en clair (dev local).
    if (attribute === 'secure' && !isHttps) continue;

    kept.push(trimmed);
  }

  kept.push('Path=/');
  kept.push('SameSite=Lax');

  return kept.join('; ');
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
        headers.append('set-cookie', rescopeSessionCookie(cookie, request));
      }
    }
  }

  if (isGoogleAuthInitiation(path) && !isRedirectStatus(response.status)) {
    console.error('[api-proxy] google oauth initiation failed', {
      status: response.status,
    });

    return NextResponse.redirect(new URL('/auth/login?error=google_auth_failed', request.url));
  }

  return new Response(response.body, {
    status: response.status,
    statusText: response.statusText,
    headers,
  });
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
