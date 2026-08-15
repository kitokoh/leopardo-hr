import { SITE_URL } from '@/lib/site-url';
import { Metadata } from 'next';

export const metadata: Metadata = {
  alternates: { canonical: `${SITE_URL}/guides` },
  title: 'Guides & Ressources | Téléchargements Gratuits',
  description:
    'Téléchargez nos guides gratuits: Guide RH Startup, Checklist Paie 2026, Modèle Planning Employés.',
  keywords: [
    'guides gratuits',
    'ressources RH',
    'templates',
    'téléchargements',
  ],
};

export default function GuidesLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return children;
}
