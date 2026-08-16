import type { Metadata } from 'next';

// #4177 : page utilitaire hors SEO — sans metadata dédiée, /offline héritait
// du layout racine (canonical + hreflang pointant vers la homepage).
export const metadata: Metadata = {
  robots: { index: false, follow: false },
};

export default function OfflineLayout({
  children,
}: Readonly<{ children: React.ReactNode }>): React.ReactElement {
  return <>{children}</>;
}
