import { SITE_URL } from '@/lib/site-url';
import { Metadata } from 'next';
import { generateMetadata as generateSEOMetadata } from '@/modules/vitrine/lib/seo';

export const metadata: Metadata = generateSEOMetadata({
  title: 'Guide Complet RH pour Startup | Télécharger',
  description:
    'Guide complet RH pour startup. Conseils, templates et bonnes pratiques. Téléchargez gratuitement en PDF.',
  keywords: [
    'guide RH startup',
    'RH pour startup',
    'gestion RH',
    'conseils RH',
  ],
  ogImage: `${SITE_URL}/og/guides-rh-startup.png`,
  ogType: 'article',
  canonical: `${SITE_URL}/guides/rh-startup`,
});

export default function GuidesRHStartupLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return children;
}
