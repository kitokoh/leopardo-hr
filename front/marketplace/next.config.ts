import type { NextConfig } from "next";

/**
 * Leopardo Marché — web client grand public (BC-17 RETAIL, phase 2, #7809).
 *
 * Application indépendante de `front/web` : aucune session, aucun proxy —
 * les appels partent directement vers l'API publique
 * (`NEXT_PUBLIC_MARKET_API_BASE`, OBLIGATOIRE — plus aucun défaut en dur,
 * le build échoue sans elle, #7963).
 *
 * `turbopack.root` / `outputFileTracingRoot` sont fixés sur CE dossier : le
 * monorepo contient plusieurs lockfiles et Next « devinerait » sinon une
 * racine au niveau du dépôt (précédent #7305 dans front/web).
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
  const raw = process.env.NEXT_PUBLIC_MARKET_API_BASE;
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
  allowedDevOrigins: ["127.0.0.1"],

  turbopack: {
    root: __dirname,
  },
  outputFileTracingRoot: __dirname,

  // Les visuels produits (`image_url`) sont des URL absolues fournies par les
  // vendeurs, hébergées sur des domaines arbitraires : l'optimiseur d'images
  // Next (proxy + allow-list de domaines) n'est pas applicable ici. Les
  // images sont servies telles quelles ; le composant <ProductImage> gère le
  // fallback élégant quand l'URL est absente ou cassée.
  images: {
    unoptimized: true,
  },

  compress: true,
  poweredByHeader: false,

  headers: async () => [
    {
      source: "/:path*",
      headers: [
        { key: "Content-Security-Policy", value: contentSecurityPolicy },
        { key: "X-Content-Type-Options", value: "nosniff" },
        { key: "X-Frame-Options", value: "DENY" },
        { key: "X-XSS-Protection", value: "1; mode=block" },
        {
          key: "Strict-Transport-Security",
          value: "max-age=31536000; includeSubDomains",
        },
        { key: "Referrer-Policy", value: "strict-origin-when-cross-origin" },
        {
          key: "Permissions-Policy",
          value: "geolocation=(), microphone=(), camera=()",
        },
      ],
    },
  ],

  experimental: {
    optimizePackageImports: ["lucide-react"],
  },
};

export default nextConfig;
