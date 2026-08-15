import { SITE_URL } from '@/lib/site-url';
import { Metadata } from 'next';
import { generateMetadata as generateSEOMetadata } from '@/modules/vitrine/lib/seo';

export const metadata: Metadata = generateSEOMetadata({
  title: 'Modèle Planning Employés | Télécharger Excel',
  description:
    'Modèle de planning pour vos employés. Template Excel gratuit, flexible et facile à utiliser.',
  keywords: [
    'planning employés',
    'modèle planning',
    'template Excel',
    'gestion planning',
  ],
  ogImage: `${SITE_URL}/og/guides-planning-employes.png`,
  ogType: 'article',
  canonical: `${SITE_URL}/guides/planning-employes`,
});

export default function GuidesPlanningEmployesLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return children;
}
