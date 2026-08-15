interface JsonLdProps {
  data: Record<string, unknown>;
}

import { SITE_URL } from '@/lib/site-url';

// Issue #1775 : le domaine Vercel « emprunté » ne doit jamais apparaître dans
// les données structurées. L'URL de marque est centralisée dans
// src/lib/site-url.ts (env NEXT_PUBLIC_SITE_URL prioritaire, fallback
// www.leopardo-rh.com — docs/DEPLOYMENT_PRODUCTION.md).
export function JsonLd({ data }: JsonLdProps) {
  return (
    <script
      type="application/ld+json"
      dangerouslySetInnerHTML={{ __html: JSON.stringify(data) }}
    />
  );
}

export function ArticleJsonLd({
  title,
  description,
  url,
  image,
  datePublished,
  author,
  inLanguage,
}: {
  title: string;
  description: string;
  url: string;
  image: string;
  datePublished: string;
  author: string;
  inLanguage?: string;
}) {
  return (
    <JsonLd
      data={{
        '@context': 'https://schema.org',
        '@type': 'Article',
        headline: title,
        description,
        image,
        url,
        inLanguage,
        datePublished,
        author: {
          '@type': 'Person',
          name: author,
        },
        publisher: {
          '@type': 'Organization',
          name: 'Leopardo RH',
          logo: {
            '@type': 'ImageObject',
            url: `${SITE_URL}/icon.svg`,
          },
        },
      }}
    />
  );
}

export function OrganizationJsonLd() {
  return (
    <JsonLd
      data={{
        '@context': 'https://schema.org',
        '@type': 'SoftwareApplication',
        name: 'Leopardo RH',
        applicationCategory: 'BusinessApplication',
        operatingSystem: 'Web, Android',
        description:
          'Plateforme SaaS de gestion RH pour PME : paie multi-pays, pointage, absences, formations, recrutement.',
        availableLanguage: ['fr', 'en', 'ar', 'tr'],
        offers: {
          '@type': 'AggregateOffer',
          priceCurrency: 'EUR',
          lowPrice: '0',
          highPrice: '99',
          offerCount: '3',
        },
        creator: {
          '@type': 'Organization',
          name: 'Leopardo RH',
          url: SITE_URL,
        },
      }}
    />
  );
}

export function FAQJsonLd({
  items,
}: {
  items: { question: string; answer: string }[];
}) {
  return (
    <JsonLd
      data={{
        '@context': 'https://schema.org',
        '@type': 'FAQPage',
        mainEntity: items.map((item) => ({
          '@type': 'Question',
          name: item.question,
          acceptedAnswer: {
            '@type': 'Answer',
            text: item.answer,
          },
        })),
      }}
    />
  );
}
