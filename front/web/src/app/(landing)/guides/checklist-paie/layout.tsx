import { SITE_URL } from '@/lib/site-url';
import { Metadata } from 'next';
import { generateMetadata as generateSEOMetadata } from '@/modules/vitrine/lib/seo';

export const metadata: Metadata = generateSEOMetadata({
  title: 'Checklist Paie 2026 | Télécharger Gratuitement',
  description:
    'Checklist complète pour votre paie. Vérifications et conformité. Téléchargez gratuitement en PDF.',
  keywords: [
    'checklist paie',
    'paie',
    'conformité paie',
    'gestion paie',
  ],
  ogImage: `${SITE_URL}/og/guides-checklist-paie.png`,
  ogType: 'article',
  canonical: `${SITE_URL}/guides/checklist-paie`,
});

export default function GuidesChecklistPaieLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return children;
}
