import { SITE_URL } from '@/lib/site-url';
import { Metadata } from 'next';
import { headers } from 'next/headers';
import { notFound } from 'next/navigation';
import { generateMetadata as generateSEOMetadata, getPageMetadata } from '@/modules/vitrine/lib/seo';
import { getEnvConfig } from '@/modules/vitrine/lib/env';

export async function generateMetadata(): Promise<Metadata> {
  // #4004 : ?lang= normalisé par le middleware en en-tête x-vitrine-lang
  // (Next 15 ne passe pas searchParams aux generateMetadata des layouts).
  const headerList = await headers();
  const lang = headerList.get('x-vitrine-lang') ?? undefined;
  const seo = getPageMetadata('blog', lang);
  return generateSEOMetadata({


  title: seo.title,
  description: seo.description,
  keywords: seo.keywords,
  ogImage: seo.ogImage,
  ogType: 'website',
  canonical: `${SITE_URL}/blog`,
    locale: lang,
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
