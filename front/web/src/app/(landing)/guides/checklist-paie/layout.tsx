import { SITE_URL } from '@/lib/site-url';
import { Metadata } from 'next';
import { headers } from 'next/headers';
import { generateMetadata as generateSEOMetadata, getPageMetadata } from '@/modules/vitrine/lib/seo';
import type { AppLocale } from '@/lib/i18n';
import { BreadcrumbJsonLd } from '@/components/JsonLd';
import { breadcrumbLabels, localizedUrl } from '@/lib/ai-search';

async function resolveLocale(): Promise<AppLocale> {
  // #4004 : ?lang= normalisé par le middleware en en-tête x-vitrine-lang
  // (Next 15 ne passe pas searchParams aux generateMetadata des layouts).
  const headerList = await headers();
  return (headerList.get('x-vitrine-lang') ?? 'fr') as AppLocale;
}

export async function generateMetadata(): Promise<Metadata> {
  const lang = await resolveLocale();
  const seo = getPageMetadata('guideChecklistPaie', lang);
  return generateSEOMetadata({
    title: seo.title,
    description: seo.description,
    keywords: seo.keywords,
    ogImage: seo.ogImage,
    ogType: 'article',
    canonical: `${SITE_URL}/guides/checklist-paie`,
    locale: lang,
  });
}

export default async function GuidesChecklistPaieLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  const lang = await resolveLocale();
  const labels = breadcrumbLabels(lang);
  // #AI-SEO : fil d'Ariane (Accueil › guide). Pas de niveau intermédiaire
  // `/guides` : cet index n'existe pas (404) — un breadcrumb vers une URL
  // morte serait invalide.
  const guide = getPageMetadata('guideChecklistPaie', lang);

  return (
    <>
      <BreadcrumbJsonLd
        items={[
          { name: labels.home, url: localizedUrl('/', lang) },
          { name: guide.title, url: localizedUrl('/guides/checklist-paie', lang) },
        ]}
      />
      {children}
    </>
  );
}
