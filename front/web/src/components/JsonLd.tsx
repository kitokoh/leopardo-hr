import { getPricingPlans } from '@/modules/vitrine/data/pricing';
import { DEFAULT_CURRENCY_OPTION } from '@/modules/vitrine/data/currency';

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

// #4707 — JSON-LD organisation cohérent par locale : name/description
// localisés (avant : FR pour toutes les locales). La devise des Offers est
// pilotée par le module currency (DEFAULT_CURRENCY_OPTION) au lieu de 'EUR'
// codé en dur — les prix restent autorés en EUR (data/pricing.ts).
const ORG_COPY: Record<string, { name: string; description: string }> = {
  fr: {
    name: 'Leopardo RH',
    description:
      'Plateforme SaaS de gestion RH pour PME : paie multi-pays, pointage, absences, formations, recrutement.',
  },
  en: {
    name: 'Leopardo HR',
    description:
      'HR management SaaS platform for SMBs: multi-country payroll, time tracking, leave, training and recruiting.',
  },
  tr: {
    name: 'Leopardo İK',
    description:
      "KOBİ'ler için İK yönetimi SaaS platformu: çok ülkeli maaş, puantaj, izin, eğitim ve işe alım.",
  },
  ar: {
    name: 'ليوباردو',
    description:
      'منصة موارد بشرية سحابية للشركات الصغيرة والمتوسطة: رواتب متعددة البلدان، حضور، إجازات، تدريب وتوظيف.',
  },
};

// #4403 — JSON-LD localisé par locale (page) ; les plans « sur devis »
// (Enterprise) n'ont pas de prix machine : schema.org/Offer EXIGE `price`,
// une offre sans prix est invalide (Google Rich Results). On n'émet donc
// que les plans à prix machine (Free/Pilot/Operations). Le prix 0 du plan
// Free est conservé (offre gratuite réelle).
export function OrganizationJsonLd({ locale = 'fr' }: { locale?: string }) {
  const orgCopy = ORG_COPY[locale] ?? ORG_COPY.fr;
  const offers = getPricingPlans(locale as Parameters<typeof getPricingPlans>[0])
    .filter((plan) => Number.isFinite(Number(plan.price)))
    .map((plan) => ({
      '@type': 'Offer' as const,
      name: plan.name,
      description: plan.description,
      price: Number(plan.price),
      priceCurrency: DEFAULT_CURRENCY_OPTION.currency,
      url: `${SITE_URL}/pricing`,
    }));

  return (
    <JsonLd
      data={{
        '@context': 'https://schema.org',
        '@type': 'SoftwareApplication',
        name: orgCopy.name,
        applicationCategory: 'BusinessApplication',
        operatingSystem: 'Web, Android',
        description: orgCopy.description,
        availableLanguage: ['fr', 'en', 'ar', 'tr'],
        offers,
        creator: {
          '@type': 'Organization',
          name: orgCopy.name,
          url: SITE_URL,
        },
      }}
    />
  );
}
