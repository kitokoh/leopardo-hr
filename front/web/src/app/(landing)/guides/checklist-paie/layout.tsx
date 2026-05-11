import { Metadata } from 'next';

export const metadata: Metadata = {
  title: 'Checklist Paie 2024 | Télécharger Gratuitement',
  description:
    'Checklist complète pour votre paie 2024. Vérifications et conformité. Téléchargez gratuitement en PDF.',
  keywords: [
    'checklist paie',
    'paie 2024',
    'conformité paie',
    'gestion paie',
  ],
};

export default function GuidesChecklistPaieLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return children;
}
