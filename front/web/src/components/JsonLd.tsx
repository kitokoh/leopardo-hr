import { getPricingPlans } from '@/modules/vitrine/data/pricing';
import type { AppLocale } from '@/lib/i18n';

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

// #4403 — JSON-LD localisé par locale (page) ; les plans « sur devis »
// (Enterprise) n'ont pas de prix machine : schema.org/Offer EXIGE `price`,
// une offre sans prix est invalide (Google Rich Results). On n'émet donc
// que les plans à prix machine (Free/Pilot/Operations). Le prix 0 du plan
// Free est conservé (offre gratuite réelle).
//
// #4707 : description Organisation par locale (plus de FR sur en/tr/ar) et
// devise pilotée par le module vitrine `data/currency.ts` (les tarifs sont
// rédigés en EUR — la devise source du module, jamais un littéral épars).
import { DEFAULT_CURRENCY_OPTION } from '@/modules/vitrine/data/currency';

const ORG_DESCRIPTION: Record<AppLocale, string> = {
  fr: 'Plateforme SaaS de gestion RH pour PME : paie multi-pays, pointage, absences, formations, recrutement.',
  en: 'HR management SaaS platform for SMBs: multi-country payroll, time tracking, leave, training, recruiting.',
  tr: "KOBİ'ler için İK yönetimi SaaS platformu: çok ülkeli bordro, yoklama, izinler, eğitimler, işe alım.",
  ar: 'منصة سحابية لإدارة الموارد البشرية للشركات الصغيرة والمتوسطة: رواتب متعددة الدول، حضور، إجازات، تدريب، توظيف.',
};

export function OrganizationJsonLd({ locale = 'fr' }: { locale?: string }) {
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
        name: 'Leopardo RH',
        applicationCategory: 'BusinessApplication',
        operatingSystem: 'Web, Android',
        description: ORG_DESCRIPTION[(locale ?? 'fr') as AppLocale] ?? ORG_DESCRIPTION.fr,
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
