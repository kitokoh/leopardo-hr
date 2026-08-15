import { SITE_URL } from '@/lib/site-url';
import { Metadata } from 'next';
import { headers } from 'next/headers';
import { generateMetadata as generateSEOMetadata, localizedPageMetadata, resolveSsrLang, pageMetadata } from '@/modules/vitrine/lib/seo';

export async function generateMetadata(): Promise<Metadata> {
  const headerList = await headers();
  const lang = headerList.get('x-lang') ?? resolveSsrLang(headerList.get('accept-language'));
  const meta = localizedPageMetadata('careers', lang);
  return generateSEOMetadata({    title: meta.title,
    description: meta.description,
  keywords: pageMetadata.careers.keywords,
  ogImage: pageMetadata.careers.ogImage,
  ogType: 'website',
  canonical: `${SITE_URL}/careers`,
});
}

export default function CareersLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return children;
}
