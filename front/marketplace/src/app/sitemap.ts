import type { MetadataRoute } from "next";

import { fetchProducts, fetchSellers } from "@/lib/api";

// Voir robots.ts pour la convention d'URL (#7986 tranche 2).
const SITE_URL =
  process.env.NEXT_PUBLIC_SITE_URL || "https://leopardo-marche.vercel.app";

// Le catalogue est dynamique (API publique /public/market/*) — revalidation
// horaire raisonnable, les publications de boutiques/produits sont rares.
export const revalidate = 3600;

export default async function sitemap(): Promise<MetadataRoute.Sitemap> {
  const staticPages: MetadataRoute.Sitemap = [
    { url: `${SITE_URL}/`, changeFrequency: "daily", priority: 1 },
    { url: `${SITE_URL}/boutiques`, changeFrequency: "daily", priority: 0.9 },
    { url: `${SITE_URL}/produits`, changeFrequency: "hourly", priority: 0.9 },
  ];

  // Best-effort (doctrine web/sitemap.ts, RESTO-903 #7748) : une API
  // indisponible ne casse JAMAIS le sitemap — les pages statiques restent
  // servies et les entrées dynamiques sont simplement omises.
  let dynamicPages: MetadataRoute.Sitemap = [];
  try {
    const [sellers, products] = await Promise.all([
      fetchSellers(),
      fetchProducts({ per_page: 100, sort: "recent" }),
    ]);

    dynamicPages = [
      ...sellers.map(
        (seller): MetadataRoute.Sitemap[number] => ({
          url: `${SITE_URL}/boutiques/${seller.slug}`,
          changeFrequency: "weekly",
          priority: 0.7,
        }),
      ),
      // Seuls les produits disponibles sont indexables : une fiche épuisée
      // en sitemap enverrait un signal contradictoire aux crawlers.
      ...products.data
        .filter((product) => product.available)
        .map(
          (product): MetadataRoute.Sitemap[number] => ({
            url: `${SITE_URL}/produits/${product.id}`,
            changeFrequency: "daily",
            priority: 0.6,
          }),
        ),
    ];
  } catch {
    dynamicPages = [];
  }

  return [...staticPages, ...dynamicPages];
}
