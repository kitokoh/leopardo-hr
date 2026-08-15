import { SITE_URL } from '@/lib/site-url';
import { Metadata } from 'next';
import { generateMetadata as generateSEOMetadata, getPageMetadata } from '@/modules/vitrine/lib/seo';

// #3536 : /integrations était la seule route landing sans metadata dédiées
// (page client, aucun layout) — title/description/canonical/OG propres.
export async function generateMetadata({ searchParams }: {
  searchParams: Promise<{ lang?: string }>;
}): Promise<Metadata> {
  const { lang } = await searchParams;
  const seo = getPageMetadata('integrations', lang);
  return generateSEOMetadata({

  title: seo.title,
  description: seo.description,
  keywords: seo.keywords,
  ogImage: seo.ogImage,
  ogType: 'website',
  canonical: `${SITE_URL}/integrations`,
  robots: 'index, follow',
    locale: lang,
  });
}

export default function IntegrationsLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return children;
}
