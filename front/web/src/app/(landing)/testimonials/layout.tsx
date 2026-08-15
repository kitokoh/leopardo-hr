import { Metadata } from 'next';
import { generateMetadata as generateSEOMetadata, pageMetadata } from '@/modules/vitrine/lib/seo';
import { getSiteUrl } from '@/lib/site';

export const metadata: Metadata = generateSEOMetadata({
  title: pageMetadata.testimonials.title,
  description: pageMetadata.testimonials.description,
  keywords: pageMetadata.testimonials.keywords,
  ogImage: pageMetadata.testimonials.ogImage,
  ogType: 'website',
  canonical: `${getSiteUrl()}/testimonials`,
});

export default function TestimonialsLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return children;
}
