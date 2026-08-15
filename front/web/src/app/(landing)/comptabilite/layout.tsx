import { SITE_URL } from '@/lib/site-url';
import { generateMetadata as generateSEOMetadata, getPageMetadata } from '@/modules/vitrine/lib/seo';
import type { Metadata } from 'next';

export async function generateMetadata({ searchParams }: {
  searchParams: Promise<{ lang?: string }>;
}): Promise<Metadata> {
  const { lang } = await searchParams;
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
