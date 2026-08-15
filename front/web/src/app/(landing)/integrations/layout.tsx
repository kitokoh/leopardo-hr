import { SITE_URL } from '@/lib/site-url';
import { Metadata } from 'next';
import { generateMetadata as generateSEOMetadata, pageMetadata } from '@/modules/vitrine/lib/seo';

// #3536 : /integrations était la seule route landing sans metadata dédiées
// (page client, aucun layout) — title/description/canonical/OG propres.
export const metadata: Metadata = generateSEOMetadata({
  title: pageMetadata.integrations.title,
  description: pageMetadata.integrations.description,
  keywords: pageMetadata.integrations.keywords,
  ogImage: pageMetadata.integrations.ogImage,
  ogType: 'website',
  canonical: `${SITE_URL}/integrations`,
  robots: 'index, follow',
});

export default function IntegrationsLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return children;
}
