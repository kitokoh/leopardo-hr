import { Metadata } from 'next';
import { generateMetadata as generateSEOMetadata } from '@/modules/vitrine/lib/seo';
import { pageMetadata } from '@/modules/vitrine/lib/seo';

export const metadata: Metadata = generateSEOMetadata({
  title: pageMetadata.blog.title,
  description: pageMetadata.blog.description,
  keywords: pageMetadata.blog.keywords,
  ogImage: pageMetadata.blog.ogImage,
  ogType: 'website',
  canonical: 'https://leopardo.com/blog',
});

export default function BlogLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return children;
}
