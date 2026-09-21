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
 * #7980/#8022 — la Content-Security-Policy n'est PLUS émise ici : un nonce
 * est par définition par-requête, or les headers de `next.config.ts` sont
 * statiques (`script-src 'unsafe-inline'` survivait). La CSP est construite
 * dans `src/lib/csp.ts` et émise par `src/proxy.ts` avec un nonce par
 * requête (pattern #7650 de front/web). Ce fichier ne porte plus que les
 * en-têtes de sécurité statiques.
 */

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
