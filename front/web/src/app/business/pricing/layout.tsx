import { headers } from 'next/headers';
import type { Metadata } from 'next';
import { SITE_URL } from '@/lib/site-url';
import {
  generateMetadata as generateSEOMetadata,
  getPageMetadata,
  generateFAQSchema,
} from '@/modules/vitrine/lib/seo';
import { t } from '@/lib/i18n/locale-catalog';
import { getPricingFaq } from '@/modules/vitrine/data/pricing-faq';
import type { AppLocale } from '@/lib/i18n';

// #3487 — la meta description pricing est localisée par requête (Accept-Language
// SSR, même logique que le root layout #2719) au lieu de t('fr', …) codé en dur.
function resolveSsrLang(acceptLanguage: string | null): AppLocale {
  const base = (acceptLanguage ?? '')
    .split(',')[0]
    .trim()
    .toLowerCase()
    .slice(0, 2);

  return (['fr', 'en', 'ar', 'tr'] as const).includes(base as AppLocale)
    ? (base as AppLocale)
    : 'fr';
}

export async function generateMetadata(): Promise<Metadata> {
  const headerList = await headers();
  // #4004 : ?lang= (liens internes vitrine, normalisé par le middleware en
  // x-vitrine-lang) prime sur Accept-Language SSR.
  const lang = headerList.get('x-vitrine-lang') ?? undefined;
  const locale = (lang as AppLocale | undefined) ?? resolveSsrLang(headerList.get('accept-language'));
  const seo = getPageMetadata('pricing', locale);

  return generateSEOMetadata({
    ...seo,
    canonical: `${SITE_URL}/pricing`,
    robots: 'index, follow',
    // #3807 : og:locale aligné sur la locale réelle (pas de fr_FR global).
    locale,
  });
}

export default async function PricingLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  // Issue #3921 : FAQ Schema généré depuis le contenu localisé de la page
  // (mêmes questions/answers que l'UI visible, dans la langue de la page).
  const headerList = await headers();
  // #4201 : le schéma JSON-LD suit la même source de locale que les
  // métadonnées (?lang= via x-vitrine-lang, sinon Accept-Language) — un
  // visiteur ?lang=en ne doit pas recevoir un schéma FR.
  const locale = (headerList.get('x-vitrine-lang') as AppLocale | null)
    ?? resolveSsrLang(headerList.get('accept-language'));
  const faqSchema = generateFAQSchema(
    getPricingFaq(locale).map((item) => ({ question: item.question, answer: item.answer }))
  );

  return (
    <>
      <script
        type="application/ld+json"
        dangerouslySetInnerHTML={{ __html: JSON.stringify(faqSchema) }}
      />
      {children}
    </>
  );
}
