'use client';

/**
 * HeroScene3DPanel — la constellation « Business OS » en PLEINE SCÈNE du héros
 * (page d'accueil, layout `stage`).
 *
 * Mission Leopardo — la constellation présente les SOLUTIONS MÉTIER RÉELLES
 * (et non les liens du menu) : les 10 étoiles viennent du catalogue unique
 * `data/solution-catalog.ts` (qui réutilise `PACK_PAGES` pour les verticales
 * déjà documentées).
 *
 * Le clic sur une étoile n'ouvre plus une page : il affiche la fiche
 * interactive (`SolutionCard`, rendue dans un portail — voir son en-tête).
 *
 * Retour utilisateur : l'indice « Cliquez sur un module pour explorer » est
 * retiré (la constellation et ses fiches se découvrent au survol, le pointeur
 * change ; aucun texte ne vient concurrencer les étoiles).
 *
 * Le conteneur est une COLONNE PLEINE HAUTEUR en trois zones : la scène
 * (flex-1, elle domine) puis le CTA unique, ancre bas. La scène reçoit une
 * taille explicite (`flex-1` + `min-h-0`) : le canvas WebGL a besoin d'une
 * hauteur réelle, un `absolute inset-0` dans un parent non dimensionné
 * donnait un rendu étiré.
 */

import { useCallback, useEffect, useRef, useState } from 'react';
import { HeroScene3D } from './HeroScene3D';
import { SolutionCard } from './SolutionCard';
import { HeroSolutionsCTA } from './HeroSolutionsCTA';
import { useVitrineLocale } from '../../lib/vitrine-locale';
import { SOLUTIONS, solutionLabel, type SolutionId } from '../../data/solution-catalog';

/**
 * Identifiants des modules, dans l'ordre de création de la scène 3D :
 * anneau INTÉRIEUR (5 premières entrées du catalogue — solutions offertes en
 * pack) puis anneau EXTÉRIEUR (5 suivantes — autres environnements + socle).
 * Voir `HeroBusinessOS3D` (`MODULES_PER_RING = [5, 5]`).
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
      {/* Vague 6 — proportion revue : la scène occupait 100 % de la hauteur et
          poussait le CTA tout en bas (un « no man's land » entre la
          constellation et le bouton, relevé en audit). Elle prend 86 % et le
          CTA vient juste sous elle ; la marge basse garde de l'air sans vider
          l'écran. */}
      <div ref={sceneRef} className="relative min-h-0 flex-[0_0_86%]">
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
