import type { Metadata } from 'next';
import ViewerClient from './ViewerClient';

/**
 * #7425 (tranche 2) — page **publique** de visionnage d'un lien de partage.
 *
 * Elle est servie sans session (aucun en-tête d'authentification n'est requis
 * pour obtenir le HTML ; l'autorisation se joue à l'appel de `GET /view/cam`
 * avec l'en-tête `X-Token`) et **ne doit jamais être indexée** : un lien de
 * partage est privé, et une page indexée exposerait l'existence du flux — le
 * `robots` de page est donc posé ici, dans le composant serveur, avant tout
 * rendu.
 *
 * Le jeton n'est pas lu côté serveur (il vit dans le fragment, que le serveur
 * ne reçoit pas) : c'est `ViewerClient` qui le lit dans `location.hash` et le
 * transporte en en-tête.
 */
export const metadata: Metadata = {
  title: 'Flux caméra',
  robots: {
    index: false,
    follow: false,
    nocache: true,
    googleBot: { index: false, follow: false },
  },
};

export const dynamic = 'force-dynamic';

export default function PublicCameraViewerPage() {
  return <ViewerClient />;
}
