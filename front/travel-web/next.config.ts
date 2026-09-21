import type { NextConfig } from "next";

/**
 * Issue #7738 — front/travel-web : site public de réservation de billets
 * inter-agences (épic #7736).
 *
 * Même contrainte monorepo que front/web (#7305) : le dépôt contient
 * plusieurs lockfiles — la racine du workspace est donc déclarée
 * explicitement sur CE dossier pour que le build (Turbopack) et la trace de
 * fichiers serveur soient déterministes. `front/travel-web` est autonome et
 * n'importe rien hors de son répertoire.
 */

/**
 * Content-Security-Policy — DÉPLACÉE dans le proxy (issue #8022, tranche 1,
 * pattern #7650 de front/web).
 *
 * Historique : CSP statique posée ici par #7980, avec
 * `script-src 'self' 'unsafe-inline'` — une XSS inline s'exécutait donc
 * toujours. La tranche nonce exige un nonce PAR REQUÊTE : impossible ici
 * (les headers de `next.config.ts` sont statiques). La politique vit
 * désormais dans `src/lib/csp.ts` (source unique des directives,
 * `connect-src` par environnement via `NEXT_PUBLIC_API_URL`, repli dev/test
 * vers `http://localhost:8000` — tranche 3) et est émise par `src/proxy.ts`
 * avec `'nonce-…' 'strict-dynamic'` et SANS `'unsafe-inline'` dans
 * script-src. Ne PAS réintroduire de CSP ici : deux politiques enforce
 * s'intersectent et la copie statique (sans nonce) bloquerait tout script.
 * Rollback opérationnel : `CSP_REPORT_ONLY=true` (cf. `src/lib/csp.ts`).
 */
const nextConfig: NextConfig = {
  // Dev depuis 127.0.0.1 / IP LAN (même piège que front/web, 2026-09-14).
  allowedDevOrigins: ["127.0.0.1"],

  turbopack: {
    root: __dirname,
  },
  outputFileTracingRoot: __dirname,

  images: {
    formats: ["image/avif", "image/webp"],
  },

  poweredByHeader: false,

  async headers() {
    return [
      {
        source: "/(.*)",
        headers: [
          { key: "X-Content-Type-Options", value: "nosniff" },
          { key: "X-Frame-Options", value: "DENY" },
          { key: "Referrer-Policy", value: "strict-origin-when-cross-origin" },
          {
            key: "Permissions-Policy",
            value: "geolocation=(), microphone=(), camera=()",
          },
        ],
      },
    ];
  },
};

export default nextConfig;
