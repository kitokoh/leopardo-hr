import { SITE_URL } from '@/lib/site-url';
import { Metadata } from 'next';
import { headers } from 'next/headers';
import { localizedPageMetadata, resolveSsrLang, pageMetadata, generateMetadata as generateSEOMetadata } from '@/modules/vitrine/lib/seo';

export async function generateMetadata(): Promise<Metadata> {
  const headerList = await headers();
  const lang = headerList.get('x-lang') ?? resolveSsrLang(headerList.get('accept-language'));
  const meta = localizedPageMetadata('employes', lang);
  return generateSEOMetadata({
  ...pageMetadata.employes,
    title: meta.title,
    description: meta.description,
  canonical: `${SITE_URL}/employes`,
});
}

export default function EmployesLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return children;
}
