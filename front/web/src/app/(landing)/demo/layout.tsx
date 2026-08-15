import { SITE_URL } from '@/lib/site-url';
import type { Metadata } from 'next';
import { generateMetadata as generateSEOMetadata } from '@/modules/vitrine/lib/seo';

export const metadata: Metadata = generateSEOMetadata({
  title: 'Demander une Demo | Leopardo RH',
  description:
    'Planifiez une demo gratuite de Leopardo RH. Découvrez la gestion RH automatisee : paie multi-pays, pointage, absences, formations et plus.',
  keywords: [
    'demo Leopardo RH',
    'démo logiciel RH',
    'planifier démo SaaS',
    'gestion RH automatisée',
  ],
  ogImage: `${SITE_URL}/og/demo.png`,
  ogType: 'website',
  canonical: `${SITE_URL}/demo`,
});

export default function DemoLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return children;
}
