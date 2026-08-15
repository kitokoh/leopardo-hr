import { SITE_URL } from '@/lib/site-url';
import { headers } from 'next/headers';
import type { Metadata } from 'next';
import { generateMetadata as generateSEOMetadata, localizedPageMetadata, resolveSsrLang, pageMetadata } from '@/modules/vitrine/lib/seo';

export async function generateMetadata(): Promise<Metadata> {
  const headerList = await headers();
  const lang = headerList.get('x-lang') ?? resolveSsrLang(headerList.get('accept-language'));
  const meta = localizedPageMetadata('demo', lang);
  return generateSEOMetadata({    title: meta.title,
    description: meta.description,
  keywords: pageMetadata.demo.keywords,
  ogImage: pageMetadata.demo.ogImage,
  ogType: 'website',
  canonical: `${SITE_URL}/demo`,
});
}

export default function DemoLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return children;
}
