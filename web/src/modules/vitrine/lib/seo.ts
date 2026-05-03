import { Metadata } from "next";

const siteUrl = process.env.NEXT_PUBLIC_SITE_URL || "http://localhost:3000";
const siteName = process.env.NEXT_PUBLIC_SITE_NAME || "Leopardo";

export interface SEOMetadata {
  title: string;
  description: string;
  keywords?: string[];
  ogImage?: string;
  ogType?: "website" | "article";
  canonical?: string;
  robots?: string;
  author?: string;
  publishedTime?: string;
  modifiedTime?: string;
}

/**
 * Generate Next.js Metadata object
 */
export function generateMetadata(seo: SEOMetadata): Metadata {
  const url = seo.canonical || siteUrl;
  const image = seo.ogImage || `${siteUrl}/og-image.png`;

  return {
    title: seo.title,
    description: seo.description,
    keywords: seo.keywords,
    authors: seo.author ? [{ name: seo.author }] : undefined,
    robots: seo.robots || "index, follow",
    openGraph: {
      title: seo.title,
      description: seo.description,
      url: url,
      siteName: siteName,
      images: [
        {
          url: image,
          width: 1200,
          height: 630,
          alt: seo.title,
        },
      ],
      type: seo.ogType || "website",
      ...(seo.publishedTime && { publishedTime: seo.publishedTime }),
      ...(seo.modifiedTime && { modifiedTime: seo.modifiedTime }),
    },
    twitter: {
      card: "summary_large_image",
      title: seo.title,
      description: seo.description,
      images: [image],
    },
    alternates: {
      canonical: url,
    },
  };
}

/**
 * SEO metadata for all pages
 */
export const pageMetadata = {
  landing: {
    title: "Gestion Employés, Paie & Documents | Plateforme Complète",
    description:
      "Gérez vos employés, paie et documents en un seul endroit. Essai gratuit 14 jours, sans carte bancaire.",
    keywords: [
      "gestion employés SaaS",
      "logiciel RH PME",
      "paie automatisée",
      "pointage numérique",
      "gestion absences",
    ],
    ogImage: `${siteUrl}/og/landing.png`,
  },

  employes: {
    title: "Gestion RH Complète | Pointage, Absences, Schedules",
    description:
      "Gérez pointage, absences et schedules facilement. Pointage intelligent avec NFC et biométrie. Essai gratuit.",
    keywords: [
      "gestion RH PME",
      "pointage numérique",
      "gestion absences",
      "logiciel RH",
      "paie employés",
    ],
    ogImage: `${siteUrl}/og/employes.png`,
  },

  documents: {
    title: "Cabinet Numérique Sécurisé | Gestion Documents Conformes",
    description:
      "Cabinet numérique avec chiffrement AES-256. Partage sécurisé, archivage automatique, conformité RGPD.",
    keywords: [
      "cabinet numérique",
      "gestion documents sécurisée",
      "partage documents",
      "archivage conformité",
      "RGPD documents",
    ],
    ogImage: `${siteUrl}/og/documents.png`,
  },

  comptabilite: {
    title: "Paie Automatisée & Conformité | Bulletins Générés",
    description:
      "Paie automatisée avec calculs exacts et conformité garantie. Bulletins générés, exports comptables. Essai gratuit.",
    keywords: [
      "paie automatisée",
      "logiciel paie PME",
      "calcul salaire",
      "bulletins de paie",
      "conformité paie",
    ],
    ogImage: `${siteUrl}/og/comptabilite.png`,
  },

  marketing: {
    title: "Marketing Digital Intégré | Email, SMS, Réseaux Sociaux",
    description:
      "Outils marketing complets: email, SMS, réseaux sociaux. Automation, analytics, intégration RH.",
    keywords: [
      "email marketing PME",
      "SMS marketing",
      "automation marketing",
      "campagnes email",
      "marketing automation",
    ],
    ogImage: `${siteUrl}/og/marketing.png`,
  },

  pricing: {
    title: "Tarification Transparente | Plans Flexibles",
    description:
      "Pricing transparent: Starter 29€, Business 79€, Enterprise sur devis. Essai gratuit 14 jours.",
    keywords: [
      "prix logiciel RH",
      "tarification paie",
      "coût gestion employés",
      "plans pricing",
    ],
    ogImage: `${siteUrl}/og/pricing.png`,
  },

  about: {
    title: "À Propos | Notre Mission et Équipe",
    description:
      "Découvrez notre mission, équipe et valeurs. Nous aidons les PME à gérer leurs employés simplement.",
    keywords: ["à propos", "équipe", "mission", "valeurs"],
    ogImage: `${siteUrl}/og/about.png`,
  },

  blog: {
    title: "Blog & Resources | Guides RH et Conseils",
    description:
      "Guides, articles et webinaires sur la gestion RH, paie et productivité pour PME.",
    keywords: [
      "guide RH",
      "conseils paie",
      "gestion employés",
      "tendances RH",
      "automatisation RH",
    ],
    ogImage: `${siteUrl}/og/blog.png`,
  },
};

/**
 * Structured Data (JSON-LD)
 */

export function generateOrganizationSchema() {
  return {
    "@context": "https://schema.org",
    "@type": "Organization",
    name: siteName,
    url: siteUrl,
    logo: `${siteUrl}/logo.png`,
    description: "Plateforme complète de gestion RH pour PME et startups",
    sameAs: [
      "https://twitter.com/leopardo",
      "https://linkedin.com/company/leopardo",
      "https://facebook.com/leopardo",
    ],
    contactPoint: {
      "@type": "ContactPoint",
      contactType: "Customer Support",
      email: "support@leopardo.com",
      availableLanguage: ["fr", "en"],
    },
  };
}

export function generateProductSchema(productName: string, description: string) {
  return {
    "@context": "https://schema.org",
    "@type": "SoftwareApplication",
    name: productName,
    description: description,
    applicationCategory: "BusinessApplication",
    operatingSystem: "Web",
    offers: {
      "@type": "Offer",
      price: "29",
      priceCurrency: "EUR",
      availability: "https://schema.org/InStock",
    },
    aggregateRating: {
      "@type": "AggregateRating",
      ratingValue: "4.9",
      ratingCount: "500",
    },
  };
}

export function generateFAQSchema(
  faqs: Array<{ question: string; answer: string }>
) {
  return {
    "@context": "https://schema.org",
    "@type": "FAQPage",
    mainEntity: faqs.map((faq) => ({
      "@type": "Question",
      name: faq.question,
      acceptedAnswer: {
        "@type": "Answer",
        text: faq.answer,
      },
    })),
  };
}

export function generateReviewSchema(
  author: string,
  rating: number,
  reviewText: string
) {
  return {
    "@context": "https://schema.org",
    "@type": "Review",
    reviewRating: {
      "@type": "Rating",
      ratingValue: rating.toString(),
      bestRating: "5",
    },
    author: {
      "@type": "Person",
      name: author,
    },
    reviewBody: reviewText,
  };
}

export function generateBreadcrumbSchema(
  items: Array<{ name: string; url: string }>
) {
  return {
    "@context": "https://schema.org",
    "@type": "BreadcrumbList",
    itemListElement: items.map((item, index) => ({
      "@type": "ListItem",
      position: index + 1,
      name: item.name,
      item: item.url,
    })),
  };
}

/**
 * Sitemap generation
 */
export interface SitemapEntry {
  url: string;
  lastmod?: string;
  changefreq?: "always" | "hourly" | "daily" | "weekly" | "monthly" | "yearly" | "never";
  priority?: number;
}

export function generateSitemapXML(entries: SitemapEntry[]): string {
  const xml = `<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
${entries
  .map(
    (entry) => `
  <url>
    <loc>${entry.url}</loc>
    ${entry.lastmod ? `<lastmod>${entry.lastmod}</lastmod>` : ""}
    ${entry.changefreq ? `<changefreq>${entry.changefreq}</changefreq>` : ""}
    ${entry.priority ? `<priority>${entry.priority}</priority>` : ""}
  </url>
`
  )
  .join("")}
</urlset>`;

  return xml;
}

/**
 * Robots.txt generation
 */
export function generateRobotsTxt(): string {
  return `User-agent: *
Allow: /
Disallow: /admin
Disallow: /api
Disallow: /.env
Disallow: /.git

Sitemap: ${siteUrl}/sitemap.xml

User-agent: Googlebot
Allow: /

User-agent: Bingbot
Allow: /`;
}

/**
 * Open Graph image generation helper
 */
export function getOGImageUrl(page: string): string {
  return `${siteUrl}/og/${page}.png`;
}

/**
 * Canonical URL helper
 */
export function getCanonicalUrl(path: string): string {
  return `${siteUrl}${path}`;
}
