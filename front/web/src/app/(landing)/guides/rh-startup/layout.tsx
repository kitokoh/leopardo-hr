import { Metadata } from 'next';

export const metadata: Metadata = {
  title: 'Guide Complet RH pour Startup | Télécharger',
  description:
    'Guide complet RH pour startup. Conseils, templates et bonnes pratiques. Téléchargez gratuitement en PDF.',
  keywords: [
    'guide RH startup',
    'RH pour startup',
    'gestion RH',
    'conseils RH',
  ],
};

export default function GuidesRHStartupLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return children;
}
