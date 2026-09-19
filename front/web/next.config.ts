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
 * Content-Security-Policy — DÉPLACÉE dans le proxy (issue #7650).
 *
 * Historique : née Report-Only (#1300), maintenue Report-Only par la décision
 * datée du 2026-08-09 (#1607, checklist de bascule jamais cochée, `CSP_ENFORCE`
 * jamais activé) — une CSP report-only ne bloque rien. L'audit #7650 acte la
 * bascule en ENFORCE, ce qui exige un nonce PAR REQUÊTE : impossible ici (les
 * headers de `next.config.ts` sont statiques). La politique vit désormais dans
 * `src/lib/csp.ts` (source unique des directives, `connect-src` par
 * environnement via `NEXT_PUBLIC_API_URL`) et est émise par `src/proxy.ts`
 * avec `'nonce-…' 'strict-dynamic'` et SANS `'unsafe-inline'` dans
 * script-src. Ne PAS réintroduire de CSP ici : deux polices enforce
 * s'intersectent et la copie statique (sans nonce) bloquerait tout.
 * Rollback opérationnel : `CSP_REPORT_ONLY=true` (cf. `src/lib/csp.ts`).
 */
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
        // #7650 — plus de CSP ici : elle est émise par le proxy en mode
        // enforce avec un nonce par requête (cf. bloc de décision ci-dessus).
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
    // #7662 : URLs « devinables » tapées/partagées par les prospects — vérifiées
    // 404 en prod le 2026-09-19. Les alias d'auth/inscription sont en 307
    // (compatibilité, l'emplacement canonique peut encore bouger — précédent
    // #7259) ; les slugs FR des pages canoniques EN sont en 301 (règle SEO
    // définitive, précédent #7101). La query (`?lang=`, `?source=`) est
    // transférée automatiquement par Next.
    {
      source: "/login",
      destination: "/auth/login",
      permanent: false,
    },
    {
      source: "/connexion",
      destination: "/auth/login",
      permanent: true,
    },
    {
      source: "/register",
      destination: "/signup",
      permanent: false,
    },
    {
      source: "/inscription",
      destination: "/signup",
      permanent: true,
    },
    // L'onboarding self-service commence sur /signup (aucune page /onboarding
    // n'existe — seule l'API d'activation par invitation vit sous ce nom).
    {
      source: "/onboarding",
      destination: "/signup",
      permanent: false,
    },
    {
      source: "/tarifs",
      destination: "/pricing",
      permanent: true,
    },
    {
      source: "/a-propos",
      destination: "/about",
      permanent: true,
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
    // #7531 — `optimizeCss` retiré : l'option exige `critters`, qui n'est
    // déclaré NI dans `package.json` NI dans `package-lock.json` (donc absent
    // d'un install propre → panne au build). Elle n'a par ailleurs aucun effet
    // sous App Router (le portail web est entièrement en `app/`), et son
    // `require('critters')` différé masquait l'erreur réelle derrière un 500.
    scrollRestoration: true,
  },
};

export default nextConfig;
