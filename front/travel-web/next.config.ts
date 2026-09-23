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
 * #7980 — les headers de sécurité STATIQUES restent ici. La
 * Content-Security-Policy, elle, est émise par `src/middleware.ts` avec un
 * nonce PAR REQUÊTE (#8022, même bascule que front/web #7650) — plus aucun
 * `'unsafe-inline'` en script-src, et le repli dev du connect-src vers le
 * backend local (localhost:8000) vit dans `src/lib/csp.ts`.
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
