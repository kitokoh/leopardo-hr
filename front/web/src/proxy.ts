import { NextResponse, type NextRequest } from 'next/server';
import { isSupportedLocale, resolveSsrVitrineLang } from '@/lib/i18n';

/**
 * Proxy (ex-`middleware`) : protection serveur de la zone dashboard (QA wave
 * 2026-08-14, T012, issue #2236) + normalisation `?lang=` vitrine (#4004).
 *
 * #7305 — Next 16 a renommé la convention `middleware` en `proxy` (l'ancien nom
 * émet un avertissement de dépréciation à chaque build/dev). Migration
 * sémantiquement NEUTRE : même signature, même `config.matcher`, mêmes règles ;
 * seule la convention change (`src/proxy.ts`, export nommé `proxy`).
 *
 * #7305 — Next 16 déprécie la convention de fichier `middleware` :
 *   ⚠ The "middleware" file convention is deprecated. Please use "proxy" instead.
 * Le fichier est donc `src/proxy.ts` et la fonction exportée `proxy` (exigence
 * de la convention : `proxy` ou un export par défaut — cf.
 * `next/dist/build/templates/middleware.js`). La migration est SANS effet de
 * bord : `config.matcher`, `NextResponse.redirect/next()` et les en-têtes sont
 * identiques. En revanche, un proxy s'exécute TOUJOURS sur le runtime Node.js
 * (plus de segment `runtime` possible) — ce fichier n'en déclarait aucun.
 *
 * Les 3 responsabilités ci-dessous sont verrouillées par
 * `src/lib/__tests__/proxy-responsibilities.test.ts` (#7305) :
 * 1. Zone dashboard : toute requête sans cookie de session `leopardo_token`
 *    est redirigée vers `/auth/login` avant même le rendu (gate cosmétique,
 *    la vraie auth reste serveur — issue #3522).
 * 2. `/signup` sans offre souscriptible (`?plan=`) est renvoyé sur
 *    `/pricing#plans` (le choix de l'offre précède la création du compte).
 * 3. Vitrine `(landing)` : la locale SSR est propagée dans l'en-tête
 *    `x-vitrine-lang` pour les layouts. Next ne passe PAS `searchParams`
 *    aux `generateMetadata` des LAYOUTS (pages seulement) → les layouts
 *    landing lisent `headers()`. `?lang=` (liens hreflang, #4173) prime ;
 *    sinon Accept-Language est normalisé (#4393) — sans cela les metadata
 *    (title/description) restaient FR en dur pour les visiteurs en/tr/ar
 *    alors que le contenu et `<html lang>` étaient déjà localisés.
 */

const SESSION_COOKIE_NAME = 'leopardo_token';

/** Codes d'offres souscriptibles (miroir du catalogue tarifaire). */
const SUPPORTED_PLANS = ['free', 'pilot', 'operations', 'enterprise'];

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
  '/showcase',
];

export function proxy(request: NextRequest) {
  const { pathname } = request.nextUrl;

  // #7238 (retour PM) — on ne met PAS de sélecteur d'offre dans le formulaire :
  // un compte est créé POUR une offre, et l'offre se choisit sur la page tarifs
  // (d'où l'on arrive avec `?plan=<offre>`). Sans offre choisie, on y renvoie.
  if (pathname === '/signup' && !SUPPORTED_PLANS.includes(request.nextUrl.searchParams.get('plan') ?? '')) {
    // `#plans` : la page tarifs affiche alors LES OFFRES directement (sans hero
    // marketing ni sections longues) — le prospect qui clique « Créer un
    // compte » ne doit pas traverser un récit avant de choisir.
    //
    // ⚠️ FRAGMENT, PAS QUERY. Le Navbar pointe vers `/signup`, que Next
    // **précharge** (lien dans le viewport) : le prefetch suit cette redirection.
    // Avec une cible porteuse d'une query (`/pricing?from=signup`), ce prefetch
    // ne se terminait JAMAIS — mesuré en build de production : `page.goto(...,
    // { waitUntil: 'networkidle' })` échouait par timeout à 90 s sur
    // `/signup?plan=pilot` (e2e `marketing-funnel`, job requis de la vitrine),
    // alors que la même page atteint `networkidle` en ~3 s en développement.
    // Un fragment n'est pas envoyé au serveur : le prefetch reste un GET
    // `/pricing` ordinaire. Vérifié : `/pricing` → 3,4 s ; `#plans` → 3,4 s ;
    // `?from=signup` → timeout 90 s.
    return NextResponse.redirect(new URL('/pricing#plans', request.url));
  }
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

  // Audit 2026-09-13 — un navigateur DÉJÀ connecté ne peut pas créer un second
  // espace : `/signup` le renvoie sur son tableau de bord.
  //
  // C'est un filtre COSMÉTIQUE : la garde de fond est côté API
  // (`409 SESSION_ALREADY_ACTIVE`, `SelfServiceTrialController::signup()`), car
  // les clients directs (mobile, cURL, SDK) ne traversent pas ce proxy.
  //
  // `/auth/login` reste volontairement NON gardé : rediriger un visiteur
  // authentifié hors de la page de connexion crée une boucle dès que le cookie
  // est périmé, le proxy ne pouvant pas valider la session (cf. #3522).
  if (pathname === '/signup' && isValidToken) {
    return NextResponse.redirect(new URL('/dashboard', request.url));
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

// #7305 — la convention `proxy` conserve `config.matcher` tel quel (littéraux
// obligatoires : Next les analyse statiquement) ; `protected-prefixes.test.ts`
// garde l'alignement avec `PROTECTED_PREFIXES` / `VITRINE_LANG_PREFIXES`.
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
    '/showcase/:path*', // BC-27 site vitrine tenant (management) — gate session
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
