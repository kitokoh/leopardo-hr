import { SITE_URL } from '@/lib/site-url';
import { Metadata } from 'next';
import { headers } from 'next/headers';
import { generateMetadata as generateSEOMetadata, getPageMetadata } from '@/modules/vitrine/lib/seo';

/**
 * Hub vitrine « Packs métiers offerts » (/packs) — métadonnées dédiées,
 * localisées via l'en-tête x-vitrine-lang (#4004), comme /restaurateur.
 */
export async function generateMetadata(): Promise<Metadata> {
  const headerList = await headers();
  const lang = headerList.get('x-vitrine-lang') ?? undefined;
  const seo = getPageMetadata('packs', lang);

  return generateSEOMetadata({
    title: seo.title,
    description: seo.description,
    keywords: seo.keywords,
    ogImage: seo.ogImage,
    ogType: 'website',
    canonical: `${SITE_URL}/packs`,
    locale: lang,
  });
}

export default function PacksHubLayout({ children }: { children: React.ReactNode }) {
  return children;
}
