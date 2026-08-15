import { SITE_URL } from '@/lib/site-url';
import { Metadata } from 'next';
import { headers } from 'next/headers';
import { cache } from 'react';
import { generateMetadata as generateSEOMetadata, pageMetadata, generateFAQSchema } from '@/modules/vitrine/lib/seo';
import { getFaqPageContent } from '@/modules/vitrine/data/faq-page';
import type { AppLocale } from '@/lib/i18n';

export const metadata: Metadata = generateSEOMetadata({
  title: pageMetadata.faq.title,
  description: pageMetadata.faq.description,
  keywords: pageMetadata.faq.keywords,
  ogImage: pageMetadata.faq.ogImage,
  ogType: 'website',
  canonical: `${SITE_URL}/faq`,
});

// Issue #3921 : le schéma FAQPage doit suivre la locale SSR (Accept-Language),
// comme le lang/dir du document racine — plus de Q/R FR codées en dur pour
// les visiteurs en/tr/ar (le contenu visible est déjà localisé).
const getSsrLocale = cache(async (): Promise<AppLocale> => {
  const headerList = await headers();
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
