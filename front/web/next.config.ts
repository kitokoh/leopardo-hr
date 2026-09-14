import type { NextConfig } from "next";

/**
 * ── #7305 — racine du workspace explicite (Turbopack) ─────────────────────
 * Le dépôt contient DEUX lockfiles : `/package-lock.json` (stub du monorepo,
 * « no npm workspaces, each sub-project manages its own lockfile ») et
 * `/front/web/package-lock.json` (le vrai, celui de cette app). Next en
 * déduisait la racine du workspace au niveau du monorepo et le build
 * affichait :
 *   ⚠ Warning: Next.js inferred your workspace root, but it may not be correct.
 *     We detected multiple lockfiles and selected the directory of
 *     /…/package-lock.json as the root directory.
 *   ⚠ Detected additional lockfiles: /…/front/web/package-lock.json
 *
 * `turbopack.root` fixé sur le répertoire de CE fichier (= `front/web`) rend la
 * racine déterministe et supprime l'inférence : `front/web` est autonome et
 * n'importe aucun fichier hors de son répertoire (le catalogue i18n partagé y
 * est **recopié** par `shared/i18n/sync/sync-web.js`, il n'est pas résolu à
 * l'exécution).
 *
 * ⚠ Ne PAS pointer cette racine vers le monorepo : Next propage la valeur à
 * `outputFileTracingRoot` (cf. `server/config.js`), qui vaut aujourd'hui
 * `front/web` — c'est le défaut (`config.outputFileTracingRoot || dir`). Élargir
 * la trace au monorepo a déjà cassé le déploiement Vercel de la vitrine
 * (entrée CHANGELOG « déploiement Vercel de la vitrine en échec depuis le
 * 2026-07-19 » → retrait de `outputFileTracingRoot` + `turbopack.root`). Ici la
 * trace ne bouge pas : seul l'avertissement d'inférence disparaît.
 * ───────────────────────────────────────────────────────────────────────────
 */
/**
 * Content-Security-Policy (Report-Only for now).
 *
 * Issue #1300: front/web had every other common security header (HSTS,
 * X-Frame-Options, X-Content-Type-Options, Referrer-Policy,
 * Permissions-Policy) but no CSP at all. We start in
 * `Content-Security-Policy-Report-Only` mode so violations are reported to
 * the browser console/devtools without breaking GA4, Mixpanel, Sentry, or
 * the Stripe-backed checkout flow while the report is reviewed. Once a
 * production report comes back clean, swap the header key below from
 * `Content-Security-Policy-Report-Only` to `Content-Security-Policy` to
 * enforce it.
 *
 * ── DÉCISION DATÉE — CSP vitrine (issue #1607, revue 2026-08-09) ──────────
 * Décision : MAINTENIR `Content-Security-Policy-Report-Only`.
 * Justification :
 *   1. Le passage en enforce exige d'abord de supprimer `'unsafe-inline'` de
 *      `script-src` (bootstrap inline GA4/Mixpanel dans layout.tsx) — ce qui
 *      demande un câblage nonce/hash. Sans lui, enforce casserait
 *      l'analytics, Sentry et le checkout Stripe (régression e2e réelle).
 *   2. Aucun endpoint d'ingestion des rapports (`report-uri`/`report-to`)
 *      n'existe côté API — les violations ne remontent que dans la console
 *      navigateur. Ajouter `report-uri` maintenant sans endpoint produirait
 *      un flux de 404 inexploitable.
 * Plan de passage en enforce (revue datée) :
 *   - [ ] Câbler nonce/hash sur les scripts inline (layout.tsx) ;
 *   - [ ] Ajouter un endpoint d'ingestion CSP côté API
 *         (`POST /api/v1/security/csp-report`) + `report-to` ;
 *   - [ ] Collecter 30 jours de rapports sur la vitrine de prod ;
 *   - [ ] Basculer le header en `Content-Security-Policy` (enforce) ;
 *   - [ ] Vérifier e2e vitrine (login, checkout, docs) + test de headers.
 * Prochaine revue : 2026-09-09 (ou à chaque changement de dépendance tierce).
 * ────────────────────────────────────────────────────────────────────────────
 *
 * Origins below come from what actually gets loaded today:
 *  - script-src: GA4 (googletagmanager.com), Mixpanel (mxpnl.com), Sentry
 *    browser bundle (sentry-cdn.com). 'unsafe-inline' is required because
 *    the GA4/Mixpanel bootstrap snippets in src/app/layout.tsx are inline
 *    <script> tags (no nonce/hash wiring yet).
 *  - connect-src: the Cloud API (NEXT_PUBLIC_API_URL) plus the GA4/Mixpanel/
 *    Sentry ingestion endpoints those SDKs call at runtime.
 *  - img-src/style-src: kept permissive (data:, 'unsafe-inline') because
 *    Tailwind v4 and framer-motion inject inline styles, and GA/Mixpanel
 *    send 1x1 tracking pixels.
 */
const apiOrigin = (() => {
  try {
    return new URL(
      process.env.NEXT_PUBLIC_API_URL ||
        "https://gestionemployerbackend.onrender.com",
    ).origin;
  } catch {
    return "https://gestionemployerbackend.onrender.com";
  }
})();

const cspDirectives = [
  "default-src 'self'",
  `script-src 'self' 'unsafe-inline' https://www.googletagmanager.com https://cdn4.mxpnl.com https://cdn.mxpnl.com https://browser.sentry-cdn.com`,
  "style-src 'self' 'unsafe-inline'",
  "img-src 'self' data: https:",
  "font-src 'self' data:",
  `connect-src 'self' ${apiOrigin} https://www.google-analytics.com https://www.googletagmanager.com https://api.mixpanel.com https://*.sentry.io https://*.ingest.sentry.io`,
  "frame-src 'self' https://js.stripe.com https://checkout.stripe.com",
  "object-src 'none'",
  "base-uri 'self'",
  "form-action 'self'",
  "frame-ancestors 'none'",
  "upgrade-insecure-requests",
].join("; ");

// Le passage en enforcement doit être explicite après validation des rapports.
// Par défaut, on conserve Report-Only pour les environnements qui n'ont pas
// encore migré leurs scripts inline vers des nonces/hashes.
const enforceCsp = process.env.CSP_ENFORCE === "true";

const nextConfig: NextConfig = {
  /**
   * Next bloque les ressources de développement (`/_next/*`) quand l'en-tête
   * `Origin` ne correspond pas à l'hôte du serveur : ouvrir
   * `http://127.0.0.1:3000` (ou l'IP LAN depuis un téléphone) donne une **page
   * affichée mais non interactive** — les chunks JS sont refusés, React ne
   * s'hydrate jamais, et aucun clic ne répond (constaté le 2026-09-14 sur le
   * tunnel d'inscription). `localhost` passe nativement ; on autorise donc
   * explicitement l'hôte de boucle numérique. Développement uniquement.
   */
  allowedDevOrigins: ['127.0.0.1'],

  // #7305 — racine du workspace explicite.
  //
  // Le dépôt est un monorepo à DEUX lockfiles (`/package-lock.json` et
  // `/front/web/package-lock.json`) : Next devait « deviner » la racine et
  // choisissait le dossier PARENT à chaque build, en émettant
  // `⚠ Next.js inferred your workspace root, but it may not be correct.`
  // La racine est donc déclarée : le build (Turbopack) et la trace des fichiers
  // serveur ne dépendent plus d'une heuristique — un build reproductible, et
  // pas de fichiers du monorepo embarqués par erreur dans le bundle.
  turbopack: {
    root: __dirname,
  },
  outputFileTracingRoot: __dirname,

  // Image optimization
  images: {
    formats: ["image/avif", "image/webp"],
    deviceSizes: [640, 750, 828, 1080, 1200, 1920, 2048, 3840],
    imageSizes: [16, 32, 48, 64, 96, 128, 256, 384],
    minimumCacheTTL: 60 * 60 * 24 * 365, // 1 year
    // SVG distant : désactivé par défaut pour éviter les documents SVG actifs.
    // Réactivation uniquement après revue des sources et de la CSP.
    dangerouslyAllowSVG: process.env.NEXT_IMAGE_ALLOW_SVG === "true",
    contentSecurityPolicy: "default-src 'self'; script-src 'none'; sandbox;",
  },

  // Compression
  compress: true,

  // Headers for caching and security
  headers: async () => [
    {
      source: "/images/:path*",
      headers: [
        {
          key: "Cache-Control",
          value: "public, max-age=31536000, immutable",
        },
      ],
    },
    {
      source: "/fonts/:path*",
      headers: [
        {
          key: "Cache-Control",
          value: "public, max-age=31536000, immutable",
        },
      ],
    },
    {
      source: "/:path*",
      headers: [
        {
          key: "X-Content-Type-Options",
          value: "nosniff",
        },
        {
          key: "X-Frame-Options",
          value: "DENY",
        },
        {
          key: "X-XSS-Protection",
          value: "1; mode=block",
        },
        {
          key: "Strict-Transport-Security",
          value: "max-age=31536000; includeSubDomains",
        },
        {
          key: "Referrer-Policy",
          value: "strict-origin-when-cross-origin",
        },
        {
          key: "Permissions-Policy",
          value: "geolocation=(), microphone=(), camera=()",
        },
        {
          // Activation explicite uniquement après revue des rapports CSP.
          key: enforceCsp
            ? "Content-Security-Policy"
            : "Content-Security-Policy-Report-Only",
          value: cspDirectives,
        },
      ],
    },
    {
      // #5429 — portail client : les URL tokenisées /documents/shared/*
      // ne doivent JAMAIS fuiter le token via l'en-tête Referer (polices,
      // analytics, images tiers). no-referrer strict, défini APRÈS la règle
      // globale (les règles postérieures écrasent pour la même clé).
      source: "/documents/shared/:path*",
      headers: [
        {
          key: "Referrer-Policy",
          value: "no-referrer",
        },
        {
          key: "X-Content-Type-Options",
          value: "nosniff",
        },
      ],
    },
    {
      source: "/sitemap.xml",
      headers: [
        {
          key: "Content-Type",
          value: "application/xml; charset=utf-8",
        },
        {
          key: "Cache-Control",
          value: "public, s-maxage=3600, stale-while-revalidate=86400",
        },
      ],
    },
    {
      source: "/robots.txt",
      headers: [
        {
          key: "Content-Type",
          value: "text/plain; charset=utf-8",
        },
        {
          key: "Cache-Control",
          value: "public, s-maxage=3600, stale-while-revalidate=86400",
        },
      ],
    },
  ],

  // Redirects for old image paths and SEO
  redirects: async () => [
    // #7259 : le lien d'activation envoyé par e-mail est construit côté API
    // (`UserInvitationService` → `{FRONTEND_URL}/activate/{token}`) alors que la
    // page réellement servie est `/auth/activate/{token}` : le lien répondait
    // 404 (vérifié en dev et en prod). On redirige ici pour réparer **aussi les
    // e-mails déjà envoyés**, sans dépendre d'un redéploiement de l'API.
    // `permanent: false` (307) volontairement : c'est une compatibilité, pas
    // une règle définitive — un 301 resterait mémorisé par le navigateur si
    // l'emplacement canonique évoluait encore.
    {
      source: "/activate/:token",
      destination: "/auth/activate/:token",
      permanent: false,
    },
    // #7101 : l'i18n vitrine passe par `?lang=` (issue #4004/#4173) — aucune route
    // préfixée n'existe. Les chemins /fr /en /ar /tr (et sous-chemins) répondaient
    // 404 ; on les redirige en 301 vers la forme canonique `?lang=` pour ne jamais
    // servir de 404 sur ces chemins réservés (SEO + liens partagés).
    {
      source: "/:locale(fr|en|tr|ar)/:path*",
      destination: "/:path*?lang=:locale",
      permanent: true,
    },
    // ADR-0016 Phase 5 (#5356) + rename UI (#5440) : /smart-attendance → /attendance/geo
    {
      source: "/smart-attendance/:path*",
      destination: "/attendance/geo/:path*",
      permanent: true,
    },
    {
      source: "/smart-attendance",
      destination: "/attendance/geo",
      permanent: true,
    },
    {
      source: "/images/old/:path*",
      destination: "/images/:path*",
      permanent: true,
    },
    // Redirect old blog URLs if they exist
    {
      source: "/blog/old/:slug",
      destination: "/blog/:slug",
      permanent: true,
    },
  ],

  // ISR configuration
  onDemandEntries: {
    maxInactiveAge: 60 * 60 * 1000, // 1 hour
    pagesBufferLength: 5,
  },

  // Security: remove X-Powered-By header
  poweredByHeader: false,

  // Experimental features for performance
  experimental: {
    optimizePackageImports: ["lucide-react"],
    optimizeCss: true,
    scrollRestoration: true,
  },
};

export default nextConfig;
