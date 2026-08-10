import type { NextConfig } from "next";

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
      process.env.NEXT_PUBLIC_API_URL || "https://gestionemployerbackend.onrender.com"
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

const nextConfig: NextConfig = {
  // Image optimization
  images: {
    formats: ["image/avif", "image/webp"],
    deviceSizes: [640, 750, 828, 1080, 1200, 1920, 2048, 3840],
    imageSizes: [16, 32, 48, 64, 96, 128, 256, 384],
    minimumCacheTTL: 60 * 60 * 24 * 365, // 1 year
    dangerouslyAllowSVG: true,
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
          // Report-only for now — see comment above cspDirectives. Flip to
          // "Content-Security-Policy" once the report is verified clean.
          key: "Content-Security-Policy-Report-Only",
          value: cspDirectives,
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
