import { Metadata } from 'next';
import { canonicalUrl, generateMetadata as generateSEOMetadata, pageMetadata } from '@/modules/vitrine/lib/seo';

export const metadata: Metadata = generateSEOMetadata({
  title: pageMetadata.testimonials.title,
  description: pageMetadata.testimonials.description,
  keywords: pageMetadata.testimonials.keywords,
  ogImage: pageMetadata.testimonials.ogImage,
  ogType: 'website',
  canonical: canonicalUrl('/testimonials'),
});

export default function TestimonialsLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return children;
}
