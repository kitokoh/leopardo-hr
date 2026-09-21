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
 * Content-Security-Policy — DÉPLACÉE dans le proxy (issue #8022, tranche 1,
 * pattern #7650 de front/web).
 *
 * Historique : CSP statique posée ici par #7980, avec
 * `script-src 'self' 'unsafe-inline'` — une XSS inline s'exécutait donc
 * toujours. La tranche nonce exige un nonce PAR REQUÊTE : impossible ici
 * (les headers de `next.config.ts` sont statiques). La politique vit
 * désormais dans `src/lib/csp.ts` (source unique des directives,
 * `connect-src` par environnement via `NEXT_PUBLIC_MARKET_API_BASE`) et est
 * émise par `src/proxy.ts` avec `'nonce-…' 'strict-dynamic'` et SANS
 * `'unsafe-inline'` dans script-src. Ne PAS réintroduire de CSP ici : deux
 * politiques enforce s'intersectent et la copie statique (sans nonce)
 * bloquerait tout script. Rollback opérationnel : `CSP_REPORT_ONLY=true`
 * (cf. `src/lib/csp.ts`).
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
