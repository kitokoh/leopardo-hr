import { Metadata } from 'next';

export const metadata: Metadata = {
  title: 'Modèle Planning Employés | Télécharger Excel',
  description:
    'Modèle de planning pour vos employés. Template Excel gratuit, flexible et facile à utiliser.',
  keywords: [
    'planning employés',
    'modèle planning',
    'template Excel',
    'gestion planning',
  ],
};

export default function GuidesPlanningEmployesLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return children;
}
