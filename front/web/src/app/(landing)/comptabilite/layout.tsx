import { SITE_URL } from '@/lib/site-url';
import { generateMetadata as generateSEOMetadata, getPageMetadata } from '@/modules/vitrine/lib/seo';
import type { Metadata } from 'next';
import { headers } from 'next/headers';

export async function generateMetadata(): Promise<Metadata> {
  // #4004 : ?lang= normalisé par le middleware en en-tête x-vitrine-lang
  // (Next 15 ne passe pas searchParams aux generateMetadata des layouts).
  const headerList = await headers();
  const lang = headerList.get('x-vitrine-lang') ?? undefined;
  const seo = getPageMetadata('comptabilite', lang);
  return generateSEOMetadata({

    ...seo,
    canonical: `${SITE_URL}/comptabilite`,
    locale: lang,
  });
}

export default function ComptabiliteLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return children;
}
