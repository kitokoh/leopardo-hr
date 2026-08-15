import { getPricingPlans } from '@/modules/vitrine/data/pricing';

interface JsonLdProps {
  data: Record<string, unknown>;
}

// Issue #1775 : https://gestionemployer-backend.vercel.app appartient à une entreprise de
// construction US sans rapport — ne jamais l'utiliser dans les données
// structurées. Source unique : getSiteUrl() (#2656) — NEXT_PUBLIC_SITE_URL →
// DEFAULT_SITE_URL (marque) → localhost en dev. (Closes #3852)
import { getSiteUrl } from '@/lib/site-url';

const SITE_URL = getSiteUrl();

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
            url: `${SITE_URL}/logo.png`,
          },
        },
      }}
    />
  );
}

export function OrganizationJsonLd() {
  const offers = getPricingPlans('fr').map((plan) => {
    const price = Number(plan.price);
    const baseOffer = {
      '@type': 'Offer' as const,
      name: plan.name,
      description: plan.description,
      priceCurrency: 'EUR',
      url: `${SITE_URL}/pricing`,
    };

    return Number.isFinite(price)
      ? { ...baseOffer, price }
      : {
          ...baseOffer,
          description: `${plan.description} Tarif sur devis.`,
        };
  });

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
        offers,
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
