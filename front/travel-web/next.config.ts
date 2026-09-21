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
 * #7980 — Content-Security-Policy stricte. L'API backend est la SEULE origine
 * externe autorisée en connect-src (résolue depuis la variable d'environnement
 * obligatoire au build, #7963). En dev, React Refresh exige 'unsafe-eval' et
 * les websockets HMR. Une CSP à nonce (middleware dédié) reste la tranche
 * suivante — les scripts inline du bootstrap Next l'exigent aujourd'hui.
 */
const isDev = process.env.NODE_ENV === "development";

const apiOrigin = ((): string => {
  const raw = process.env.NEXT_PUBLIC_API_URL;
  if (!raw) return "";
  try {
    return new URL(raw).origin;
  } catch {
    return "";
  }
})();

const contentSecurityPolicy = [
  "default-src 'self'",
  `script-src 'self' 'unsafe-inline'${isDev ? " 'unsafe-eval'" : ""}`,
  "style-src 'self' 'unsafe-inline'",
  "img-src 'self' data: https:",
  "font-src 'self' data:",
  `connect-src 'self'${apiOrigin ? ` ${apiOrigin}` : ""}${isDev ? " ws: wss:" : ""}`,
  "object-src 'none'",
  "base-uri 'self'",
  "form-action 'self'",
  "frame-ancestors 'none'",
].join("; ");

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
          { key: "Content-Security-Policy", value: contentSecurityPolicy },
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
