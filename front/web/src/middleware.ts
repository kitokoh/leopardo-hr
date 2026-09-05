import { NextResponse, type NextRequest } from 'next/server';
import { isSupportedLocale, resolveSsrVitrineLang } from '@/lib/i18n';

/**
 * Middleware de protection serveur de la zone dashboard (QA wave 2026-08-14,
 * T012, issue #2236) + normalisation `?lang=` vitrine (issue #4004).
 *
 * 1. Zone dashboard : toute requête sans cookie de session `leopardo_token`
 *    est redirigée vers `/auth/login` avant même le rendu (gate cosmétique,
 *    la vraie auth reste serveur — issue #3522).
 * 2. Vitrine `(landing)` : la locale SSR est propagée dans l'en-tête
 *    `x-vitrine-lang` pour les layouts. Next 15 ne passe PAS `searchParams`
 *    aux `generateMetadata` des LAYOUTS (pages seulement) → les layouts
 *    landing lisent `headers()`. `?lang=` (liens hreflang, #4173) prime ;
 *    sinon Accept-Language est normalisé (#4393) — sans cela les metadata
 *    (title/description) restaient FR en dur pour les visiteurs en/tr/ar
 *    alors que le contenu et `<html lang>` étaient déjà localisés.
 */

const SESSION_COOKIE_NAME = 'leopardo_token';

const DASHBOARD_PREFIXES = [  '/dashboard',
  '/absences',
  '/attendance',
  '/billing',
  '/contracts',
  '/employees',
  '/partner',
  '/payroll',
  '/reports',
  '/training',
  '/settings',
  '/social',
  '/social-marketing',
  '/restaurant',
];

export function middleware(request: NextRequest) {
  const { pathname } = request.nextUrl;
  const isDashboard = DASHBOARD_PREFIXES.some((prefix) => pathname.startsWith(prefix));

  const token = request.cookies.get(SESSION_COOKIE_NAME)?.value;

  // NOTE (issue #3522): this is a cosmetic/UX gate only, meant to avoid
  // serving dashboard HTML/JS to obviously unauthenticated visitors before
  // the client-side app mounts. It is NOT a security boundary: a valid
  // shape here does not mean a valid session. Real authentication and
  // authorization are enforced server-side by the API on every request.
  //
  // Deux formats de cookie sont acceptés (issue #6726/#6679) :
  //  - token opaque historique (>= 20 caractères alnum/._-) ;
  //  - token Sanctum `{id}|{plaintext}` (ex. « 990|1FVyYnVzSbMu8F1OCOtk… »),
  //    posé par le route handler `app/api/v1/auth/login/route.ts` — le `|`
  //    était exclu du regex d'origine, rendant le dashboard inaccessible ;
  //  - même forme avec pipe URL-encodé `%7C` (clients qui encodent le cookie).
  const isValidToken =
    !!token &&
    token.length >= 20 &&
    (/^[A-Za-z0-9._-]+$/.test(token) ||
      /^\d+\|[A-Za-z0-9._-]+$/.test(token) ||
      /^\d+%7C[A-Za-z0-9._-]+$/.test(token));

  // BC-25 Restaurant : le portail client occupe `/restaurant` (hub + sous-routes
  // kitchen/pos/…). La page vitrine « Je suis restaurateur » vit sur
  // `/restaurateur` — deux pages Next ne peuvent pas coexister au même chemin.
  // Visiteur sans session sur `/restaurant` (exact) → page vitrine ; session
  // valide → hub applicatif. Les sous-routes `/restaurant/*` restent protégées
  // comme le reste de la zone dashboard (gate ci-dessous).
  if (pathname === '/restaurant' && !isValidToken) {
    const marketingUrl = new URL('/restaurateur', request.url);
    return NextResponse.redirect(marketingUrl);
  }

  if (isDashboard && !isValidToken) {
    const loginUrl = new URL('/auth/login', request.url);
    return NextResponse.redirect(loginUrl);
  }

  // Vitrine : propager la locale SSR aux layouts via un en-tête (issues #4004,
  // #4393). `?lang=` prime sur Accept-Language (comportement #4173) ; le
  // header est TOUJOURS posé (défaut fr) pour un comportement déterministe.
  const urlLang = request.nextUrl.searchParams.get('lang');
  const lang = urlLang && isSupportedLocale(urlLang)
    ? urlLang
    : resolveSsrVitrineLang(null, request.headers.get('accept-language'));
  const response = NextResponse.next();
  response.headers.set('x-vitrine-lang', lang);

  return response;
}

export const config = {
  matcher: [
    // Zone dashboard protégée (source PROTECTED_PREFIXES, garde #3377).
    '/dashboard/:path*',
    '/absences/:path*',
    '/attendance/:path*',
    '/attendance/geo/:path*', // géo pointage (source unique #3377)
    '/billing/:path*',
    '/contracts/:path*',
    '/employees/:path*',
    '/partner/:path*',
    '/payroll/:path*',
    '/reports/:path*',
    '/training/:path*',
    '/settings/:path*',
    '/social/:path*',
    '/social-marketing/:path*',
    '/restaurant/:path*', // BC-25 portail client (split /restaurant → /restaurateur)
    // Vitrine landing — ?lang= → en-tête x-vitrine-lang (issue #4004).
    // Routes statiques (exactes) + préfixes dynamiques (source
    // VITRINE_LANG_PREFIXES, garde protected-prefixes.test.ts).
    '/',
    '/employes',
    '/documents',
    '/documents/:path*',
    '/comptabilite',
    '/marketing',
    '/integrations',
    '/pricing',
    '/about',
    '/changelog',
    '/docs',
    '/download',
    '/contact',
    '/demo',
    '/faq',
    '/testimonials',
    '/videos',
    '/branding',
    '/careers',
    '/mobile',
    '/signup',
    '/restaurateur', // vitrine « Je suis restaurateur » (BC-25, ex-/restaurant)
    '/blog/:path*',
    '/guides/:path*',
    '/case-studies/:path*',
    '/checkout/:path*',
  ],
};
