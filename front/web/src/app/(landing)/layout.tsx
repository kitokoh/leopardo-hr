import { headers } from 'next/headers';
import { cache } from 'react';
import { generateFAQSchema } from '@/modules/vitrine/lib/seo';
import { getFaqItems } from '@/modules/vitrine/data/faq';
import type { AppLocale } from '@/lib/i18n';

/**
 * #8076 — balisage schema.org FAQPage de la page d'accueil (SEO longue traîne).
 *
 * La FAQ de la landing (`data/faq.ts`, rendue côté client par `FAQSection`)
 * suit la locale vitrine ; le JSON-LD suit la locale SSR (en-tête
 * `x-vitrine-lang` posé par le middleware, puis Accept-Language) — même
 * pattern que le layout de `/faq` (#3921 / #4201) : le contenu structuré
 * reflète le contenu visible, jamais des Q/R FR figées pour un visiteur
 * en/tr/ar.
 */
const getSsrLocale = cache(async (): Promise<AppLocale> => {
  const headerList = await headers();
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

export default async function LandingLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  const locale = await getSsrLocale();
  const faqSchema = generateFAQSchema(
    getFaqItems(locale).map((item) => ({ question: item.question, answer: item.answer })),
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
