import { SITE_URL } from '@/lib/site-url';
import { Metadata } from 'next';
import { generateMetadata as generateSEOMetadata, getPageMetadata } from '@/modules/vitrine/lib/seo';

export async function generateMetadata({ searchParams }: {
  searchParams: Promise<{ lang?: string }>;
}): Promise<Metadata> {
  const { lang } = await searchParams;
  const seo = getPageMetadata('guideChecklistPaie', lang);
  return generateSEOMetadata({

  title: seo.title,
  description: seo.description,
  keywords: seo.keywords,
  ogImage: seo.ogImage,
  ogType: 'article',
  canonical: `${SITE_URL}/guides/checklist-paie`,
    locale: lang,
  });
}

export default function GuidesChecklistPaieLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return children;
}
