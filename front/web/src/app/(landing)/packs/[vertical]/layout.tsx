import { SITE_URL } from '@/lib/site-url';
import { Metadata } from 'next';
import { headers } from 'next/headers';
import { generateMetadata as generateSEOMetadata, getPageMetadata } from '@/modules/vitrine/lib/seo';
import { PACK_VERTICALS } from '@/modules/vitrine/data/pack-pages';

/**
 * Pages vitrine « Pack offert » par métier (/packs/[vertical]) — métadonnées
 * dédiées par verticale, localisées via l'en-tête x-vitrine-lang (#4004),
 * comme /restaurateur.
 */

const SEO_KEY: Record<string, string> = {
  'station-service': 'packs-station-service',
  ecole: 'packs-ecole',
  'agence-de-voyage': 'packs-agence-voyage',
};

export function generateStaticParams() {
  return PACK_VERTICALS.map((vertical) => ({ vertical }));
}

export async function generateMetadata({
  params,
}: {
  params: Promise<{ vertical: string }>;
}): Promise<Metadata> {
  const { vertical } = await params;
  const headerList = await headers();
  const lang = headerList.get('x-vitrine-lang') ?? undefined;
  const seoKey = SEO_KEY[vertical] ?? 'landing';
  const seo = getPageMetadata(seoKey, lang);

  return generateSEOMetadata({
    title: seo.title,
    description: seo.description,
    keywords: seo.keywords,
    ogImage: seo.ogImage,
    ogType: 'website',
    canonical: `${SITE_URL}/packs/${vertical}`,
    locale: lang,
  });
}

export default function PacksLayout({ children }: { children: React.ReactNode }) {
  return children;
}
