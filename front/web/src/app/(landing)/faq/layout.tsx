import { SITE_URL } from '@/lib/site-url';
import { Metadata } from 'next';
import { headers } from 'next/headers';
import { cache } from 'react';
import { generateMetadata as generateSEOMetadata, getPageMetadata, generateFAQSchema } from '@/modules/vitrine/lib/seo';
import { getFaqPageContent } from '@/modules/vitrine/data/faq-page';
import type { AppLocale } from '@/lib/i18n';

export async function generateMetadata(): Promise<Metadata> {
  // #4004 : ?lang= normalisé par le middleware en en-tête x-vitrine-lang
  // (Next 15 ne passe pas searchParams aux generateMetadata des layouts).
  const headerList = await headers();
  const lang = headerList.get('x-vitrine-lang') ?? undefined;
  const seo = getPageMetadata('faq', lang);
  return generateSEOMetadata({


  title: seo.title,
  description: seo.description,
  keywords: seo.keywords,
  ogImage: seo.ogImage,
  ogType: 'website',
  canonical: `${SITE_URL}/faq`,
    locale: lang,
  });
}

// Issue #3921 : le schéma FAQPage doit suivre la locale SSR (Accept-Language),
// comme le lang/dir du document racine — plus de Q/R FR codées en dur pour
// les visiteurs en/tr/ar (le contenu visible est déjà localisé).
const getSsrLocale = cache(async (): Promise<AppLocale> => {
  const headerList = await headers();
  // #4201 : ?lang= (x-vitrine-lang) prime sur Accept-Language — le schéma
  // FAQPage doit suivre la langue RÉELLE rendue (parité avec generateMetadata).
  const lang = headerList.get('x-vitrine-lang');
  if (lang && (['fr', 'en', 'ar', 'tr'] as const).includes(lang as AppLocale)) {
    return lang as AppLocale;
  }
  const base = (headerList.get('accept-language') ?? '')
    .split(',')[0]
    .trim()
    .toLowerCase()
    .slice(0, 2);
  return (['fr', 'en', 'ar', 'tr'] as const).includes(base as AppLocale) ? (base as AppLocale) : 'fr';
});

export default async function FaqLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  const locale = await getSsrLocale();
  const content = getFaqPageContent(locale);
  const faqSchema = generateFAQSchema(
    content.items.map((item) => ({ question: item.question, answer: item.answer }))
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
