import { SITE_URL } from '@/lib/site-url';
import type { Metadata } from 'next';

export const metadata: Metadata = {
  alternates: { canonical: `${SITE_URL}/demo` },
  title: 'Demander une Demo | Leopardo RH',
  description:
    'Planifiez une demo gratuite de Leopardo RH. Découvrez la gestion RH automatisee : paie multi-pays, pointage, absences, formations et plus.',
  openGraph: {
    title: 'Demander une Demo | Leopardo RH',
    description:
      'Planifiez une demo gratuite de Leopardo RH. Gestion RH automatisee pour PME.',
    type: 'website',
    url: '/demo',
    siteName: 'Leopardo RH',
  },
  twitter: {
    card: 'summary_large_image',
    title: 'Demander une Demo | Leopardo RH',
    description:
      'Planifiez une demo gratuite de Leopardo RH. Gestion RH automatisee pour PME.',
  },
};

export default function DemoLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return children;
}
