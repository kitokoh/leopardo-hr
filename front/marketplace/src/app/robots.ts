import type { MetadataRoute } from "next";

// #7986 (tranche 2) : la marketplace n'avait ni robots ni sitemap alors que
// web et travel-web en ont. L'URL publique canonique est surchargée par
// NEXT_PUBLIC_SITE_URL ; le repli correspond au déploiement Vercel documenté
// dans .github/workflows/marketplace-deploy.yml.
const SITE_URL =
  process.env.NEXT_PUBLIC_SITE_URL || "https://leopardo-marche.vercel.app";

// Pages transactionnelles / session acheteur : jamais indexables (panier,
// paiement, suivi tokenisé, compte). Seules les pages catalogue publiques
// (accueil, boutiques, produits) restent crawlables.
export default function robots(): MetadataRoute.Robots {
  return {
    rules: [
      {
        userAgent: "*",
        allow: "/",
        disallow: [
          "/api/",
          "/commande/",
          "/compte/",
          "/confirmation/",
          "/paiement/",
          "/panier/",
          "/suivi/",
        ],
      },
    ],
    sitemap: `${SITE_URL}/sitemap.xml`,
  };
}
