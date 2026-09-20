import type { MetadataRoute } from "next";

const SITE_URL =
  process.env.NEXT_PUBLIC_SITE_URL || "https://leopardo-travel.vercel.app";

export default function sitemap(): MetadataRoute.Sitemap {
  return [
    { url: `${SITE_URL}/`, changeFrequency: "daily", priority: 1 },
    { url: `${SITE_URL}/trips`, changeFrequency: "hourly", priority: 0.9 },
    { url: `${SITE_URL}/booking`, changeFrequency: "monthly", priority: 0.5 },
  ];
}
