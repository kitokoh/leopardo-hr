import type { MetadataRoute } from "next";

const SITE_URL =
  process.env.NEXT_PUBLIC_SITE_URL || "https://leopardo-travel.vercel.app";

export default function robots(): MetadataRoute.Robots {
  return {
    rules: [
      {
        userAgent: "*",
        allow: "/",
        // Pages transactionnelles / proxy : hors index.
        disallow: ["/api/", "/confirmation/"],
      },
    ],
    sitemap: `${SITE_URL}/sitemap.xml`,
  };
}
