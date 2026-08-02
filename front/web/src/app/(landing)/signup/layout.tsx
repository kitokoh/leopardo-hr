import { Metadata } from 'next';
import { generateMetadata as generateSEOMetadata, pageMetadata } from '@/modules/vitrine/lib/seo';

export const metadata: Metadata = generateSEOMetadata({
  title: pageMetadata.signup.title,
  description: pageMetadata.signup.description,
  keywords: pageMetadata.signup.keywords,
  ogImage: pageMetadata.signup.ogImage,
  ogType: 'website',
  canonical: 'https://leopardo.com/signup',
  robots: pageMetadata.signup.robots,
});

export default function SignupLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return children;
}
