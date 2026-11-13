import type { Metadata } from 'next';
import PublicPortalLayout from '@/modules/public-portal/components/PublicPortalLayout';

/**
 * Layout de l'onglet « Profil » (`/profil`).
 *
 * Même rôle que celui de `/activites` : porter le chrome du portail public
 * (en-tête, sélecteur de langue, pied de page) plutôt que celui du produit RH.
 *
 * ⚠️ Même limite connue : le manifeste PWA de `/decouvrir` déclare
 * `scope: '/decouvrir'`, donc une app installée ne couvre pas `/profil`. Voir
 * le commentaire détaillé dans `src/app/activites/layout.tsx` — c'est une
 * décision en attente (élargir le `scope` sans exposer le back-office), pas un
 * oubli.
 */

export const metadata: Metadata = {
  manifest: '/decouvrir/manifest.webmanifest',
};

export default PublicPortalLayout;
