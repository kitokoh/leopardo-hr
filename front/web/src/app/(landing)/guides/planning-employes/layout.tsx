import { SITE_URL } from '@/lib/site-url';
import { Metadata } from 'next';
import { headers } from 'next/headers';
import { generateMetadata as generateSEOMetadata, localizedPageMetadata, resolveSsrLang, pageMetadata } from '@/modules/vitrine/lib/seo';

export async function generateMetadata(): Promise<Metadata> {
  const headerList = await headers();
  const lang = headerList.get('x-lang') ?? resolveSsrLang(headerList.get('accept-language'));
  const meta = localizedPageMetadata('guidePlanningEmployes', lang);
  return generateSEOMetadata({    title: meta.title,
    description: meta.description,
  keywords: pageMetadata.guidePlanningEmployes.keywords,
  ogImage: pageMetadata.guidePlanningEmployes.ogImage,
  ogType: 'article',
  canonical: `${SITE_URL}/guides/planning-employes`,
});
}

export default function GuidesPlanningEmployesLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return children;
}
