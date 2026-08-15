import { NextResponse, type NextRequest } from 'next/server';

/**
 * Middleware de protection serveur de la zone dashboard (QA wave 2026-08-14,
 * T012, issue #2236).
 *
 * Avant : la zone `(dashboard)` n'était protégée que côté client
 * (`layout.tsx` → redirect après montage) → le HTML était servi sans session.
 * Désormais, toute requête vers un chemin de la zone dashboard sans cookie de
 * session `leopardo_token` (httpOnly, posé par `/api/v1/auth/login`) est
 * redirigée vers `/auth/login` avant même le rendu.
 *
 * Les pages du groupe `(dashboard)` vivent à la racine des URLs (le groupe de
 * routes n'ajoute pas de segment) : la liste explicite du matcher est la
 * source de vérité des chemins protégés. Les routes `/api/*`, `/auth/*` et la
 * vitrine `(landing)` ne sont pas matchées.
 */

const SESSION_COOKIE_NAME = 'leopardo_token';

export function middleware(request: NextRequest) {
  const hasSession = request.cookies.has(SESSION_COOKIE_NAME);

  if (!hasSession) {
    const loginUrl = new URL('/auth/login', request.url);
    return NextResponse.redirect(loginUrl);
  }

  return NextResponse.next();
}

export const config = {
  matcher: [
    '/dashboard/:path*',
    '/absences/:path*',
    '/attendance/:path*',
    '/billing/:path*',
    '/contracts/:path*',
    '/employees/:path*',
    '/partner/:path*',
    '/payroll/:path*',
    '/reports/:path*',
    '/training/:path*',
    '/settings/:path*',
    '/smart-attendance/:path*',
    '/social/:path*',
    '/social-marketing/:path*',
  ],
};
