import { SITE_URL } from '@/lib/site-url';
import { Metadata } from 'next';
import { headers } from 'next/headers';
import { generateMetadata as generateSEOMetadata, getPageMetadata } from '@/modules/vitrine/lib/seo';

export async function generateMetadata(): Promise<Metadata> {
  // #4004 : ?lang= normalisé par le middleware en en-tête x-vitrine-lang
  // (Next 15 ne passe pas searchParams aux generateMetadata des layouts).
  const headerList = await headers();
  const lang = headerList.get('x-vitrine-lang') ?? undefined;
  const seo = getPageMetadata('videos', lang);
  return generateSEOMetadata({


  title: seo.title,
  description: seo.description,
  keywords: seo.keywords,
  ogImage: seo.ogImage,
  ogType: 'website',
  canonical: `${SITE_URL}/videos`,
    locale: lang,
  });
}

export default function VideosLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return children;
}
