import type { Metadata } from 'next';

import PublicInboxSpace from './public-inbox-space';

/**
 * #8760 [BOS-CHAT] — Boîte de réception des demandes publiques.
 *
 * Les métadonnées vivent côté serveur (pas de littéral dans un composant
 * client) et la page est `noindex` : elle appartient à un espace professionnel
 * authentifié, aucune raison de l'exposer aux moteurs de recherche.
 */
export const metadata: Metadata = {
  title: 'Demandes publiques',
  robots: { index: false, follow: false },
};

export default function PublicInboxPage() {
  return <PublicInboxSpace />;
}
