import type { Metadata } from 'next';

export const metadata: Metadata = {
  title: 'Demander une Demo | Leopardo RH',
  description:
    'Planifiez une demo gratuite de Leopardo RH. Decouvrez la gestion RH automatisee : paie multi-pays, pointage, absences, formations et plus.',
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
