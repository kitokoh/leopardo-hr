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

// #4004 : les landing pages portent la locale ?lang= (useVitrineLocale). Le
// middleware la transmet en en-tête `x-lang` pour que generateMetadata() des
// layouts puisse localiser title/description en SSR (les layouts Next ne
// reçoivent pas searchParams). Les chemins dashboard gardent le garde session.
const LANDING_MATCHER = [
  '/', '/about', '/blog/:path*', '/branding', '/careers', '/case-studies',
  '/changelog', '/checkout/:path*', '/comptabilite', '/contact', '/demo',
  '/docs/:path*', '/documents', '/download', '/employes', '/faq',
  '/guides/:path*', '/integrations', '/marketing', '/mobile', '/pricing',
  '/signup', '/testimonials', '/videos',
];

function isLandingPath(pathname: string): boolean {
  return LANDING_MATCHER.some((pattern) => {
    if (pattern === '/') return pathname === '/';
    // Normalise les segments dynamiques du matcher ('/guides/:path*' → '/guides').
    const base = pattern.replace(/:.*$/, '').replace(/\/$/, '');
    return pathname === base || pathname.startsWith(base + '/');
  });
}

export function middleware(request: NextRequest) {
  const { pathname } = request.nextUrl;

  if (isLandingPath(pathname)) {
    const lang = request.nextUrl.searchParams.get('lang');
    if (lang) {
      const requestHeaders = new Headers(request.headers);
      requestHeaders.set('x-lang', lang);
      return NextResponse.next({ request: { headers: requestHeaders } });
    }
    return NextResponse.next();
  }

  const token = request.cookies.get(SESSION_COOKIE_NAME)?.value;


  // NOTE (issue #3522): this is a cosmetic/UX gate only, meant to avoid
  // serving dashboard HTML/JS to obviously unauthenticated visitors before
  // the client-side app mounts. It is NOT a security boundary: a valid
  // shape here does not mean a valid session. Real authentication and
  // authorization are enforced server-side by the API on every request.
  const isValidToken =
    !!token && token.length >= 20 && /^[A-Za-z0-9._-]+$/.test(token);

  if (!isValidToken) {
    const loginUrl = new URL('/auth/login', request.url);
    return NextResponse.redirect(loginUrl);
  }

  return NextResponse.next();
}

export const config = {
  matcher: [
    '/', '/about', '/blog/:path*', '/branding', '/careers', '/case-studies',
    '/changelog', '/checkout/:path*', '/comptabilite', '/contact', '/demo',
    '/docs/:path*', '/documents', '/download', '/employes', '/faq',
    '/guides/:path*', '/integrations', '/marketing', '/mobile', '/pricing',
    '/signup', '/testimonials', '/videos',
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
