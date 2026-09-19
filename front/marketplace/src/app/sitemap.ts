import type { MetadataRoute } from "next";

import { SITE_URL } from "@/lib/site";

/**
 * SEO #7809 — sitemap des pages de découverte (le catalogue produit/boutique
 * est dynamique cross-tenant : les fiches sont découvertes par crawl depuis
 * les listes, comme sur travel-web).
 */
export default function sitemap(): MetadataRoute.Sitemap {
  return [
    { url: `${SITE_URL}/`, changeFrequency: "daily", priority: 1 },
    { url: `${SITE_URL}/produits`, changeFrequency: "hourly", priority: 0.9 },
    { url: `${SITE_URL}/boutiques`, changeFrequency: "daily", priority: 0.8 },
  ];
}
