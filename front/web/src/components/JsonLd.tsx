import { getPricingPlans } from '@/modules/vitrine/data/pricing';
import { PRICING_CURRENCY } from '@/modules/vitrine/data/currency';

interface JsonLdProps {
  data: Record<string, unknown>;
}

// Issue #1775/#6683 : le domaine Vercel est le déploiement web ACTUEL
// (DOMAINS.md, statut live) mais il ne doit JAMAIS apparaître dans les
// données structurées/canonicals — source unique : getSiteUrl() (#2656) —
// NEXT_PUBLIC_SITE_URL → DEFAULT_SITE_URL (marque) → localhost en dev.
// Migration cible : leopardo-rh.com (#3452). (Closes #3852)
import { getSiteUrl } from '@/lib/site-url';
// #AI-SEO : nom de marque canonique + alias — une seule entité pour les
// moteurs et les assistants IA (avant : « Leopardo RH » figé en dur alors que
// les <title> alternent RH/HR/İK/ليوباردو).
import { BRAND_ALTERNATE_NAMES, BRAND_NAME } from '@/modules/vitrine/lib/seo';

const SITE_URL = getSiteUrl();

/**
 * Profils publics officiels de la marque (schema.org `sameAs`).
 *
 * Consolidation d'entité : c'est le signal qui rattache les mentions de
 * « Leopardo RH » sur le web à une même organisation pour les moteurs et les
 * LLM. Seul le dépôt GitHub est vérifié (cf. Footer.tsx — le LinkedIn
 * `linkedin.com/company/leopardo` renvoyait 404 au 2026-09-10 et a été
 * retiré ; ne PAS l'ajouter tant qu'il n'est pas de nouveau résolvable).
 */
const SAME_AS = ['https://github.com/kitokoh/leopardo-hr'] as const;

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
        mainEntityOfPage: {
          '@type': 'WebPage',
          '@id': url,
        },
        author: {
          '@type': 'Person',
          name: author,
        },
        publisher: {
          '@type': 'Organization',
          name: BRAND_NAME,
          alternateName: BRAND_ALTERNATE_NAMES,
          url: SITE_URL,
          sameAs: [...SAME_AS],
          logo: {
            '@type': 'ImageObject',
            url: `${SITE_URL}/logo.png`,
          },
        },
      }}
    />
  );
}

/**
 * #AI-SEO — nœud `WebSite` racine : identité du site, langue servie et
 * éditeur. Absent jusqu'ici (seul un `SoftwareApplication` existait) : sans
 * nœud WebSite/Organization, les moteurs de réponse n'ont pas d'entité
 * « site » à laquelle rattacher les pages et les alias de marque.
 *
 * Pas de `potentialAction` SearchAction : le site n'expose aucune recherche
 * publique — un SearchAction sans endpoint est du balisage invalide.
 */
export function WebSiteJsonLd({ locale = 'fr' }: { locale?: string }) {
  return (
    <JsonLd
      data={{
        '@context': 'https://schema.org',
        '@type': 'WebSite',
        name: BRAND_NAME,
        alternateName: BRAND_ALTERNATE_NAMES,
        url: SITE_URL,
        inLanguage: locale,
        publisher: {
          '@type': 'Organization',
          name: BRAND_NAME,
          alternateName: BRAND_ALTERNATE_NAMES,
          url: SITE_URL,
          sameAs: [...SAME_AS],
          logo: {
            '@type': 'ImageObject',
            url: `${SITE_URL}/logo.png`,
          },
        },
      }}
    />
  );
}

/**
 * #AI-SEO — fil d'Ariane structuré (`BreadcrumbList`).
 *
 * Deux bénéfices : affichage du chemin dans les SERP, et hiérarchie
 * explicite page → section → accueil pour les moteurs de réponse (AEO), qui
 * s'appuient sur la structure plutôt que sur l'ordre visuel.
 */
export function BreadcrumbJsonLd({
  items,
}: {
  items: Array<{ name: string; url: string }>;
}) {
  return (
    <JsonLd
      data={{
        '@context': 'https://schema.org',
        '@type': 'BreadcrumbList',
        itemListElement: items.map((item, index) => ({
          '@type': 'ListItem',
          position: index + 1,
          name: item.name,
          item: item.url,
        })),
      }}
    />
  );
}

// #4403 — JSON-LD localisé par locale (page) ; les plans « sur devis »
// (Enterprise) n'ont pas de prix machine : schema.org/Offer EXIGE `price`,
// une offre sans prix est invalide (Google Rich Results). On n'émet donc
// que les plans à prix machine (Free/Pilot/Operations). Le prix 0 du plan
// Free est conservé (offre gratuite réelle).
// #4707 : description Organisation localisée ×4 (avant : FR pour toutes les locales).
const organizationDescription: Record<string, string> = {
  fr: 'Plateforme SaaS de gestion RH pour PME : paie multi-pays, pointage, absences, formations, recrutement.',
  en: 'SaaS HR platform for SMBs: multi-country payroll, time tracking, leave, training and recruiting.',
  tr: "KOBİ'ler için SaaS İK platformu: çok ülkeli bordro, yoklama, izin, eğitim ve işe alım.",
  ar: 'منصة موارد بشرية سحابية للشركات الصغيرة والمتوسطة: رواتب متعددة البلدان، حضور، إجازات، تدريب وتوظيف.',
};

export function OrganizationJsonLd({ locale = 'fr' }: { locale?: string }) {
  const offers = getPricingPlans(locale as Parameters<typeof getPricingPlans>[0])
    .filter((plan) => Number.isFinite(Number(plan.price)))
    .map((plan) => ({
      '@type': 'Offer' as const,
      name: plan.name,
      description: plan.description,
      price: Number(plan.price),
      priceCurrency: PRICING_CURRENCY,
      url: `${SITE_URL}/pricing`,
    }));

  return (
    <JsonLd
      data={{
        '@context': 'https://schema.org',
        '@type': 'SoftwareApplication',
        name: BRAND_NAME,
        alternateName: BRAND_ALTERNATE_NAMES,
        applicationCategory: 'BusinessApplication',
        operatingSystem: 'Web, Android',
        description: organizationDescription[locale] ?? organizationDescription.fr,
        url: SITE_URL,
        inLanguage: locale,
        availableLanguage: ['fr', 'en', 'ar', 'tr'],
        sameAs: [...SAME_AS],
        offers,
        creator: {
          '@type': 'Organization',
          name: BRAND_NAME,
          alternateName: BRAND_ALTERNATE_NAMES,
          url: SITE_URL,
          sameAs: [...SAME_AS],
        },
      }}
    />
  );
}
