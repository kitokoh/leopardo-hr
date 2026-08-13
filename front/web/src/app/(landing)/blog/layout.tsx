import { Metadata } from 'next';
import { notFound } from 'next/navigation';
import { generateMetadata as generateSEOMetadata } from '@/modules/vitrine/lib/seo';
import { pageMetadata } from '@/modules/vitrine/lib/seo';
import { getEnvConfig } from '@/modules/vitrine/lib/env';

export const metadata: Metadata = generateSEOMetadata({
  title: pageMetadata.blog.title,
  description: pageMetadata.blog.description,
  keywords: pageMetadata.blog.keywords,
  ogImage: pageMetadata.blog.ogImage,
  ogType: 'website',
  canonical: 'https://gestionemployer-backend.vercel.app/blog',
});

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
