import type { Metadata } from 'next';
import PublicPortalLayout from '@/modules/public-portal/components/PublicPortalLayout';

/**
 * Layout du portail grand public à la racine (#9357).
 *
 * ## Rôle 1 : poser le chrome du portail
 *
 * `/` est la page d'accueil du grand public : elle doit porter l'en-tête
 * (navigation entre verticales, sélecteur de langue, bascule de thème, bouton
 * « Leopardo Business ») et le pied de page communs, exactement comme
 * `/decouvrir` et les annuaires.
 *
 * ## Rôle 2 : déclarer le manifeste de l'app grand public
 *
 * Le layout racine déclare `manifest: '/manifest'`, qui décrit le produit RH.
 * Installer la PWA depuis `/` produirait une icône ouvrant la page de vente
 * RH. On ne touche PAS au layout racine : seule cette route déclare le sien.
 *
 * ## Pourquoi un groupe de routes `(public)`
 *
 * Le groupe n'ajoute aucun segment d'URL : `(public)/page.tsx` sert bien `/`.
 * Il isole le portail du reste de l'arbre (dashboard, auth, business) sans
 * créer de préfixe, et permet d'y attacher ce layout sans affecter les autres
 * surfaces.
 */

export const metadata: Metadata = {
  manifest: '/decouvrir/manifest.webmanifest',
};

export default PublicPortalLayout;
