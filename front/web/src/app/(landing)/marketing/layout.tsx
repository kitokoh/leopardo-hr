import { SITE_URL } from '@/lib/site-url';
import { Metadata } from 'next';
import { getPageMetadata, generateMetadata as generateSEOMetadata } from '@/modules/vitrine/lib/seo';

export async function generateMetadata({ searchParams }: {
  searchParams: Promise<{ lang?: string }>;
}): Promise<Metadata> {
  const { lang } = await searchParams;
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
