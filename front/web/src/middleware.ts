import { NextRequest, NextResponse } from 'next/server';

/**
 * QA wave 2026-08-14 — T012 (#2236) : protection de la zone dashboard côté
 * serveur.
 *
 * Avant : seul le layout client (front/web/src/app/(dashboard)/layout.tsx:46-52)
 * redirigeait vers /auth/login après montage — le HTML de la zone était servi
 * sans session (et les routes API /api/v1/* sont déjà protégées par le proxy
 * Next, cf. front/web/src/app/api/v1/[...path]/route.ts).
 *
 * Désormais, toute requête vers une route de la zone sans cookie
 * `leopardo_token` est redirigée côté serveur vers /auth/login.
 */
const SESSION_COOKIE_NAME = 'leopardo_token';

export function middleware(request: NextRequest): NextResponse {
  const token = request.cookies.get(SESSION_COOKIE_NAME)?.value;

  if (!token) {
    const loginUrl = new URL('/auth/login', request.url);
    return NextResponse.redirect(loginUrl);
  }

  return NextResponse.next();
}

// Les segments racine de la zone dashboard (les route groups (dashboard) /
// (marketing) n'apparaissent pas dans l'URL). /dashboard/* couvre la page
// d'accueil de la zone.
export const config = {
  matcher: [
    '/dashboard/:path*',
    '/absences/:path*',
    '/attendance/:path*',
    '/billing/:path*',
    '/contracts/:path*',
    '/edge-nodes/:path*',
    '/employees/:path*',
    '/partner/:path*',
    '/payroll/:path*',
    '/reports/:path*',
    '/settings/:path*',
    '/smart-attendance/:path*',
    '/social/:path*',
    '/social-marketing/:path*',
    '/training/:path*',
  ],
};
