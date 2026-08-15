import { SITE_URL } from '@/lib/site-url';
import { Metadata } from 'next';
import { headers } from 'next/headers';
import { generateMetadata as generateSEOMetadata, localizedPageMetadata, resolveSsrLang, pageMetadata } from '@/modules/vitrine/lib/seo';

export async function generateMetadata(): Promise<Metadata> {
  const headerList = await headers();
  const lang = headerList.get('x-lang') ?? resolveSsrLang(headerList.get('accept-language'));
  const meta = localizedPageMetadata('guides', lang);
  return generateSEOMetadata({    title: meta.title,
    description: meta.description,
  keywords: pageMetadata.guides.keywords,
  ogImage: pageMetadata.guides.ogImage,
  ogType: 'website',
  canonical: `${SITE_URL}/guides`,
});
}

export default function GuidesLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return children;
}
