import { SITE_URL } from '@/lib/site-url';
import { Metadata } from 'next';
import { headers } from 'next/headers';
import { getPageMetadata, generateMetadata as generateSEOMetadata } from '@/modules/vitrine/lib/seo';

export async function generateMetadata(): Promise<Metadata> {
  // #4004 : ?lang= normalisé par le middleware en en-tête x-vitrine-lang
  // (Next 15 ne passe pas searchParams aux generateMetadata des layouts).
  const headerList = await headers();
  const lang = headerList.get('x-vitrine-lang') ?? undefined;
  const seo = getPageMetadata('marketing', lang);
  return generateSEOMetadata({

    ...seo,
    canonical: `${SITE_URL}/marketing`,
    locale: lang,
  });
}

export default function MarketingLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return children;
}
