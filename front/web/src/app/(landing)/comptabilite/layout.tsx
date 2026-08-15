import { SITE_URL } from '@/lib/site-url';
import { headers } from 'next/headers';
import { generateMetadata as generateSEOMetadata, resolveSsrLang} from '@/modules/vitrine/lib/seo';
import { localizedPageMetadata, pageMetadata } from '@/modules/vitrine/lib/seo';
import type { Metadata } from 'next';

export async function generateMetadata(): Promise<Metadata> {
  const headerList = await headers();
  const lang = headerList.get('x-lang') ?? resolveSsrLang(headerList.get('accept-language'));
  const meta = localizedPageMetadata('comptabilite', lang);
  return generateSEOMetadata({
  ...pageMetadata.comptabilite,
    title: meta.title,
    description: meta.description,
  canonical: `${SITE_URL}/comptabilite`,
});
}

export default function ComptabiliteLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return children;
}
