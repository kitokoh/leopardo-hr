import { SITE_URL } from '@/lib/site-url';
import { Metadata } from 'next';
import { generateMetadata as generateSEOMetadata, pageMetadata } from '@/modules/vitrine/lib/seo';

export const metadata: Metadata = generateSEOMetadata({
  title: pageMetadata.guideRhStartup.title,
  description: pageMetadata.guideRhStartup.description,
  keywords: pageMetadata.guideRhStartup.keywords,
  ogImage: pageMetadata.guideRhStartup.ogImage,
  ogType: 'article',
  canonical: `${SITE_URL}/guides/rh-startup`,
});

export default function GuidesRHStartupLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return children;
}
