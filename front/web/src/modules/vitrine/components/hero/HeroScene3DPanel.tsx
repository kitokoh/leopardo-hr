'use client';

/**
 * HeroScene3DPanel — la constellation « Business OS » en PLEINE SCÈNE du héros
 * (page d'accueil, layout `stage`).
 *
 * Passe Fondateur (#8701) — la constellation redevient la PIÈCE MAÎTRESSE 3D
 * du héros. La refonte précédente (#8698) l'avait rétrogradée en décor
 * estompé derrière des cartes CSS planes (`HeroOrbitCards`) : la scène
 * ressemblait à du 2D. Ces cartes plates sont retirées ; la constellation
 * WebGL est de nouveau pleine, vivante et interactive — comme avant, en
 * conservant les acquis a11y (étiquettes-boutons, restitution du focus).
 *
 * Mission Leopardo — la constellation présente les SOLUTIONS MÉTIER RÉELLES
 * (et non les liens du menu) : les étoiles viennent du catalogue unique
 * `data/solution-catalog.ts` (qui réutilise `PACK_PAGES` pour les verticales
 * déjà documentées).
 *
 * Le clic sur une étoile n'ouvre pas une page : il affiche la fiche
 * interactive (`SolutionCard`, rendue dans un portail — voir son en-tête).
 *
 * Le conteneur est une COLONNE PLEINE HAUTEUR : la scène (elle domine) puis
 * le CTA unique, ancré bas. La scène reçoit une taille explicite : le canvas
 * WebGL a besoin d'une hauteur réelle, un `absolute inset-0` dans un parent
 * non dimensionné donnait un rendu étiré.
 */

import { useCallback, useEffect, useRef, useState } from 'react';
import { HeroScene3D } from './HeroScene3D';
import { SolutionCard } from './SolutionCard';
import { HeroSolutionsCTA } from './HeroSolutionsCTA';
import { useVitrineLocale } from '../../lib/vitrine-locale';
import { SOLUTIONS, solutionLabel, type SolutionId } from '../../data/solution-catalog';

/**
 * Identifiants des modules, dans l'ordre de création de la scène 3D :
 * anneau INTÉRIEUR (premières entrées du catalogue) puis anneau EXTÉRIEUR
 * (entrées suivantes). Voir `HeroBusinessOS3D` (`MODULES_PER_RING`).
 */
const MODULE_IDS: readonly SolutionId[] = SOLUTIONS.map((solution) => solution.id);

/** Destination de repli d'un module (accessible depuis la fiche). */
export const MODULE_HREFS: readonly string[] = SOLUTIONS.map((solution) => solution.href);

export function HeroScene3DPanel() {
  const { locale } = useVitrineLocale();
  const [selected, setSelected] = useState<SolutionId | null>(null);
  const sceneRef = useRef<HTMLDivElement>(null);
  const moduleLabels = MODULE_IDS.map((id) => solutionLabel(id, locale));

  const onSelectModule = useCallback((index: number) => {
    const id = MODULE_IDS[index];
    if (id) setSelected(id);
  }, []);

  const close = useCallback(() => setSelected(null), []);

  // Échap referme la fiche même si le focus a quitté le document ; la fiche
  // gère déjà Échap et le piège de focus, cette écoute est un filet.
  useEffect(() => {
    if (!selected) return undefined;
    const onKey = (event: KeyboardEvent) => {
      if (event.key === 'Escape') setSelected(null);
    };
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, [selected]);

  return (
    <div className="relative flex h-full w-full flex-col">
      {/* La scène domine (86 %) et le CTA vient juste sous elle : à 100 % la
          constellation poussait le bouton tout en bas (un « no man's land »
          entre les deux, relevé en audit). */}
      {/* #8701 — `tabIndex={-1}` : cible de restitution du focus quand la
          fiche a été ouverte à la souris (l'étoile est un pixel de canvas,
          sans nœud focusable). Sans cela, le focus retombait sur <body> —
          défaut mesuré en navigateur. Ouverte au clavier, la fiche rend le
          focus à l'étiquette-bouton d'origine, qui est un vrai contrôle. */}
      <div ref={sceneRef} tabIndex={-1} className="relative min-h-0 flex-[0_0_86%] outline-none">
        <HeroScene3D onSelectModule={onSelectModule} moduleLabels={moduleLabels} />
      </div>
      <div className="relative z-20 flex shrink-0 justify-center px-4 pb-10 sm:pb-12">
        <HeroSolutionsCTA />
      </div>
      {selected && (
        <SolutionCard
          solutionId={selected}
          locale={locale}
          onClose={close}
          returnFocusRef={sceneRef}
        />
      )}
    </div>
  );
}

export default HeroScene3DPanel;
