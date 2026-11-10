import { SITE_URL } from '@/lib/site-url';
import { Metadata } from 'next';
import { headers } from 'next/headers';
import { generateMetadata as generateSEOMetadata, getPageMetadata } from '@/modules/vitrine/lib/seo';

// #4004 : listing localisé (FR par défaut, ?lang= pour EN/TR/AR).
async function listingMetadata(lang?: string): Promise<Metadata> {
  const seo = getPageMetadata('caseStudies', lang);
  return generateSEOMetadata({
    ...seo,
    ogType: 'website',
    canonical: `${SITE_URL}/case-studies`,
    locale: lang,
  });
}

// #7192 (audit SEO 2026-09-13) : la branche `params.slug` de ce layout était
// MORTE — un layout ne reçoit les params que de son propre segment, et
// `case-studies` n'a pas de segment dynamique (`[slug]` est un enfant). Elle
// n'a jamais pu produire autre chose que les métadonnées du listing, et
// dupliquait la logique désormais portée par `case-studies/[slug]/layout.tsx`.
// Ne reste ici que le listing ; le détail est dans le layout enfant.
// #4004 : ?lang= normalisé par le middleware en en-tête x-vitrine-lang.
export async function generateMetadata(): Promise<Metadata> {
  const headerList = await headers();
  const lang = headerList.get('x-vitrine-lang') ?? undefined;
  return listingMetadata(lang);
}

export default function CaseStudiesLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return children;
}
