import { SITE_URL } from '@/lib/site-url';
import { Metadata } from 'next';
import { headers } from 'next/headers';
import { generateMetadata as generateSEOMetadata, localizedPageMetadata, resolveSsrLang, pageMetadata } from '@/modules/vitrine/lib/seo';

// #3536 : /integrations était la seule route landing sans metadata dédiées
// (page client, aucun layout) — title/description/canonical/OG propres.
export async function generateMetadata(): Promise<Metadata> {
  const headerList = await headers();
  const lang = headerList.get('x-lang') ?? resolveSsrLang(headerList.get('accept-language'));
  const meta = localizedPageMetadata('integrations', lang);
  return generateSEOMetadata({    title: meta.title,
    description: meta.description,
  keywords: pageMetadata.integrations.keywords,
  ogImage: pageMetadata.integrations.ogImage,
  ogType: 'website',
  canonical: `${SITE_URL}/integrations`,
  robots: 'index, follow',
});
}

export default function IntegrationsLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return children;
}
