import type { Metadata } from 'next';
import PublicPortalLayout from '@/modules/public-portal/components/PublicPortalLayout';

/**
 * Layout de l'onglet « Activités » (`/activites`).
 *
 * ## Rôle : porter le chrome du portail public
 *
 * Comme `/decouvrir`, la page doit avoir l'en-tête (navigation, sélecteur de
 * langue) et le pied de page communs. Sans ce layout, elle s'afficherait avec
 * le chrome du produit RH — exactement ce que la spec interdit (« l'app ne doit
 * jamais ressembler à un logiciel RH avec des boutons ajoutés »).
 *
 * ## ⚠️ Limite connue : le périmètre du manifeste PWA
 *
 * Le manifeste de `/decouvrir` déclare `scope: '/decouvrir'`. Une PWA
 * installée depuis `/decouvrir` ne peut donc PAS naviguer vers `/activites` :
 * le navigateur sortirait du périmètre et ouvrirait un onglet externe.
 *
 * C'est une **décision en attente**, pas un oubli : élargir le `scope` à `/`
 * donnerait une fenêtre sans barre d'adresse sur le back-office, ce que le
 * manifeste borne volontairement. La correction propre est un `scope` couvrant
 * les quatre onglets (`/decouvrir`, `/activites`, `/profil`, `/conversations`)
 * sans couvrir `/business` ni l'admin — un travail de manifeste à part entière,
 * hors de ce lot.
 *
 * En navigateur, les deux pages fonctionnent normalement.
 */

export const metadata: Metadata = {
  manifest: '/decouvrir/manifest.webmanifest',
};

export default PublicPortalLayout;
