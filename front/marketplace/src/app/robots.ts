import type { MetadataRoute } from "next";

import { SITE_URL } from "@/lib/site";

/**
 * SEO #7809 — robots : les pages transactionnelles (panier, checkout,
 * confirmation, suivi) sont hors index, seule la découverte est crawlée.
 */
export default function robots(): MetadataRoute.Robots {
  return {
    rules: [
      {
        userAgent: "*",
        allow: "/",
        disallow: ["/panier", "/commande", "/confirmation", "/suivi"],
      },
    ],
    sitemap: `${SITE_URL}/sitemap.xml`,
  };
}
