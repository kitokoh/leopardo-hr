import { SITE_URL } from '@/lib/site-url';
import { Metadata } from 'next';
import { headers } from 'next/headers';
import { generateMetadata as generateSEOMetadata, resolveSsrLang} from '@/modules/vitrine/lib/seo';
import { localizedPageMetadata, pageMetadata } from '@/modules/vitrine/lib/seo';

export async function generateMetadata(): Promise<Metadata> {
  const headerList = await headers();
  const lang = headerList.get('x-lang') ?? resolveSsrLang(headerList.get('accept-language'));
  const meta = localizedPageMetadata('about', lang);
  return generateSEOMetadata({    title: meta.title,
    description: meta.description,
  keywords: pageMetadata.about.keywords,
  ogImage: pageMetadata.about.ogImage,
  ogType: 'website',
  canonical: `${SITE_URL}/about`,
});
}

export default function AboutLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return children;
}
