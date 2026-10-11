'use client';

/**
 * HeroScene3DPanel — la constellation « Business OS » en COLONNE de héro
 * (page d'accueil, layout split).
 *
 * Retour propriétaire 2026-10-11 (#8389) : la scène plein cadre derrière le
 * texte nuisait à la lisibilité ; elle vit désormais dans la colonne de
 * droite, à la place du screenshot produit (déplacé, pas supprimé — il reste
 * disponible pour d'autres sections). Et #8390 : les modules orbitaux sont
 * cliquables et mènent aux pages packs / entrées clés.
 *
 * Le conteneur a une hauteur propre : la scène (absolute inset-0) le remplit.
 */

import { HeroScene3D } from './HeroScene3D';

/**
 * Un href par module (ordre de création : anneau intérieur ×4 puis anneau
 * extérieur ×4). Anneau intérieur = les 4 packs métiers offerts (la promesse
 * d'entrée) ; anneau extérieur = les entrées clés de la plateforme.
 * Clavier/lecteur d'écran : ces mêmes liens sont dans la navbar, les CTA et
 * le hub /packs — le canvas n'est qu'un raccourci visuel de plus.
 */
const MODULE_LINKS: string[] = [
  // Anneau intérieur — packs métiers offerts
  '/restaurateur',
  '/packs/station-service',
  '/packs/ecole',
  '/packs/agence-de-voyage',
  // Anneau extérieur — entrées plateforme
  '/packs',
  '/pricing',
  '/demo',
  '/contact',
];

export function HeroScene3DPanel() {
  return (
    <div className="relative h-[380px] w-full sm:h-[460px] lg:h-[560px]">
      <HeroScene3D moduleLinks={MODULE_LINKS} />
    </div>
  );
}

export default HeroScene3DPanel;
