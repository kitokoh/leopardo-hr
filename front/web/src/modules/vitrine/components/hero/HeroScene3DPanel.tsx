'use client';

/**
 * HeroScene3DPanel — la constellation « Business OS » en PLEINE SCÈNE du héros
 * (page d'accueil, layout `stage`).
 *
 * Point 4 (proposition B, revue visuelle du propriétaire produit) — la
 * structure du produit devient la mise en page : les solutions métier sont des
 * CARTES EN ORBITE autour du noyau central (`HeroOrbitCards`), et non plus des
 * losanges anonymes projetés par le canvas.
 *
 * Répartition des rôles, volontairement nette :
 *   • la constellation WebGL reste le DÉCOR (`dimmed`, sans `onSelectModule`
 *     donc `pointer-events-none`) — elle porte l'ambiance, pas l'interaction ;
 *   • les cartes DOM portent l'INTERACTION : texte réel, traduit, focusable,
 *     activable au clavier, et un mouvement orbital en CSS pur.
 *
 * Ce partage supprime la cause racine des défauts a11y historiques : les
 * étiquettes n'ont plus à être reconstruites à la main depuis des positions
 * projetées (`getWorldPosition().project()`), elles sont de vrais boutons.
 *
 * Le clic sur une carte n'ouvre pas une page : il affiche la fiche
 * interactive (`SolutionCard`, rendue dans un portail — voir son en-tête).
 *
 * Le conteneur est une COLONNE PLEINE HAUTEUR : la scène (flex-1, elle domine)
 * puis le CTA unique, ancré bas. La scène reçoit une taille explicite
 * (`flex-1` + `min-h-0`) : le canvas WebGL a besoin d'une hauteur réelle, un
 * `absolute inset-0` dans un parent non dimensionné donnait un rendu étiré.
 */

import { useCallback, useEffect, useRef, useState } from 'react';
import { HeroScene3D } from './HeroScene3D';
import { HeroOrbitCards } from './HeroOrbitCards';
import { SolutionCard } from './SolutionCard';
import { HeroSolutionsCTA } from './HeroSolutionsCTA';
import { useVitrineLocale } from '../../lib/vitrine-locale';
import { SOLUTIONS, type SolutionId } from '../../data/solution-catalog';

/** Destination de repli d'un module (accessible depuis la fiche). */
export const MODULE_HREFS: readonly string[] = SOLUTIONS.map((solution) => solution.href);

export function HeroScene3DPanel() {
  const { locale } = useVitrineLocale();
  const [selected, setSelected] = useState<SolutionId | null>(null);
  const sceneRef = useRef<HTMLDivElement>(null);

  const onSelectModule = useCallback((id: SolutionId) => {
    setSelected(id);
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
      {/* `tabIndex={-1}` : cible de restitution du focus quand la fiche a été
          ouverte à la souris. Ouverte au clavier, la fiche rend le focus à la
          carte d'origine, qui est un vrai contrôle. */}
      <div ref={sceneRef} tabIndex={-1} className="relative min-h-0 flex-1 outline-none">
        {/* Décor : la constellation, estompée et non interactive. */}
        <div aria-hidden="true" className="absolute inset-0">
          <HeroScene3D dimmed />
        </div>
        {/* Interaction : les cartes en orbite. */}
        <div className="relative flex h-full items-center justify-center px-4">
          <HeroOrbitCards onSelect={onSelectModule} selectedId={selected} />
        </div>
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
