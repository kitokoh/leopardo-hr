import { Metadata } from 'next';
import { headers } from 'next/headers';
import { SITE_URL } from '@/lib/site-url';
import { generateMetadata as generateSEOMetadata } from '@/modules/vitrine/lib/seo';
import type { AppLocale } from '@/lib/i18n';
import { BreadcrumbJsonLd } from '@/components/JsonLd';
import { breadcrumbLabels, localizedUrl } from '@/lib/ai-search';

/**
 * #7869 — Hub SEO « Alternatives & comparatifs » (/alternatives).
 * Metadata serveur dédiée + fil d'Ariane JSON-LD, pattern identique à
 * blog/layout.tsx (le rendu de la page reste client, children pass-through).
 */

const hubSeo: Record<AppLocale, { title: string; description: string }> = {
  fr: {
    title: 'Alternatives & comparatifs — Leopardo face aux solutions du marché',
    description:
      "Comparez Leopardo aux solutions établies (Odoo, Sage, PayFit, OrangeHRM, Connecteam, Talenteo) : open source, paie multi-pays, pointage terrain, mode hors ligne. Comparatifs honnêtes, essai 14 jours.",
  },
  en: {
    title: 'Alternatives & comparisons — Leopardo vs established solutions',
    description:
      'Compare Leopardo with established solutions (Odoo, Sage, PayFit, OrangeHRM, Connecteam, Talenteo): open source, multi-country payroll, field attendance, offline mode. Honest comparisons, 14-day trial.',
  },
  tr: {
    title: 'Alternatifler ve karşılaştırmalar — Leopardo ve yerleşik çözümler',
    description:
      'Leopardo ile yerleşik çözümleri karşılaştırın (Odoo, Sage, PayFit, OrangeHRM, Connecteam): açık kaynak, çok ülkeli bordro, saha yoklaması, çevrimdışı mod. 14 gün deneme.',
  },
  ar: {
    title: 'البدائل والمقارنات — ليوباردو مقابل الحلول الراسخة',
    description:
      'قارن ليوباردو بالحلول الراسخة (Odoo وSage وPayFit وOrangeHRM وConnecteam): مفتوح المصدر، رواتب متعددة البلدان، حضور ميداني، وضع دون اتصال. تجربة 14 يومًا.',
  },
};

async function resolveLocale(): Promise<AppLocale> {
  // #4004 : ?lang= normalisé en en-tête x-vitrine-lang par le middleware.
  const headerList = await headers();
  return (headerList.get('x-vitrine-lang') ?? 'fr') as AppLocale;
}

export async function generateMetadata(): Promise<Metadata> {
  const lang = await resolveLocale();
  const seo = hubSeo[lang] ?? hubSeo.fr;

  return generateSEOMetadata({
    title: seo.title,
    description: seo.description,
    ogType: 'website',
    canonical: `${SITE_URL}/alternatives`,
    locale: lang,
  });
}

export default async function AlternativesLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  const lang = await resolveLocale();
  const labels = breadcrumbLabels(lang);
  const seo = hubSeo[lang] ?? hubSeo.fr;

  return (
    <>
      <BreadcrumbJsonLd
        items={[
          { name: labels.home, url: localizedUrl('/', lang) },
          { name: seo.title, url: localizedUrl('/alternatives', lang) },
        ]}
      />
      {children}
    </>
  );
}
