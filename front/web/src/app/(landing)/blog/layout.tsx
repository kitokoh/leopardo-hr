import { SITE_URL } from '@/lib/site-url';
import { Metadata } from 'next';
import { headers } from 'next/headers';
import { notFound } from 'next/navigation';
import { generateMetadata as generateSEOMetadata, resolveSsrLang} from '@/modules/vitrine/lib/seo';
import { localizedPageMetadata, pageMetadata } from '@/modules/vitrine/lib/seo';
import { getEnvConfig } from '@/modules/vitrine/lib/env';

export async function generateMetadata(): Promise<Metadata> {
  const headerList = await headers();
  const lang = headerList.get('x-lang') ?? resolveSsrLang(headerList.get('accept-language'));
  const meta = localizedPageMetadata('blog', lang);
  return generateSEOMetadata({    title: meta.title,
    description: meta.description,
  keywords: pageMetadata.blog.keywords,
  ogImage: pageMetadata.blog.ogImage,
  ogType: 'website',
  canonical: `${SITE_URL}/blog`,
});
}

export default function BlogLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  // The blog route is only served when NEXT_PUBLIC_ENABLE_BLOG is enabled.
  // Previously this flag was defined but never read anywhere, so the route
  // was always built and served regardless of its value (issue #1305).
  if (!getEnvConfig().enableBlog) {
    notFound();
  }

  return children;
}
