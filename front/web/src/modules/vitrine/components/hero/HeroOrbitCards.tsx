'use client';

/**
 * HeroOrbitCards — les solutions métier en ORBITE autour du noyau (point 4,
 * proposition B de la revue visuelle du propriétaire produit).
 *
 * Lecture produit : le positionnement « Business OS » devient visible SANS
 * lire un mot — des modules qui gravitent autour d'un socle commun. C'est la
 * structure du produit qui devient la mise en page.
 *
 * Pourquoi des cartes DOM et non les losanges du canvas WebGL :
 *   • le texte est RÉEL (lisible, sélectionnable, traduit) au lieu d'être
 *     projeté à l'écran par `getWorldPosition().project()` à chaque image ;
 *   • chaque carte est un vrai `<button>` : clavier, lecteur d'écran et ordre
 *     de tabulation naturels — la projection canvas exigeait de reconstruire
 *     tout cela à la main (cf. l'historique a11y de `HeroBusinessOS3D`) ;
 *   • le mouvement orbital est du CSS PUR (`transform: rotate`), pas un
 *     recalcul JavaScript par image : le compositeur du navigateur s'en
 *     charge, le thread principal reste libre (exigence explicite du point 3).
 *
 * Géométrie — un anneau rotatif par orbite, position STATIQUE par carte :
 *   `.hero-orbit-ring`  est un carré plein (`inset: 0`) qui tourne ;
 *   `.hero-orbit-card`  est posée à `left`/`top` en pourcentage du carré, donc
 *   sa trajectoire est un cercle exact, et elle CONTRE-tourne avec la même
 *   durée, le même sens et le même délai pour rester droite.
 *
 * Pourquoi pas un « bras » par carte avec `translateY(-r)` : un pourcentage
 * dans `translateY` se résout contre la hauteur de l'ÉLÉMENT, pas du
 * conteneur — le rayon valait alors la hauteur d'une carte (≈ 18 px) au lieu
 * de 48 % de la scène, et les cartes s'agglutinaient au centre. Mesuré en
 * navigateur : les 5 cartes de l'anneau extérieur se retrouvaient empilées au
 * même angle, jusqu'à 33 px de recouvrement. `left`/`top` en pourcentage, eux,
 * se résolvent bien contre le conteneur.
 *
 * Variance par étoile (exigence du point 3) : chaque carte porte son propre
 * rayon, sa période, son sens et sa phase — aucune ne tourne au même rythme ni
 * à la même distance, donc pas d'effet « rouage ».
 *
 * Accessibilité : `prefers-reduced-motion` supprime les animations ; les
 * cartes restent alors réparties sur leur orbite et demeurent focusables et
 * activables au clavier.
 */

import { useCallback } from 'react';
import { useVitrineLocale } from '../../lib/vitrine-locale';
import { SOLUTIONS, solutionLabel, type SolutionId } from '../../data/solution-catalog';

export interface HeroOrbitCardsProps {
  /** Solution choisie (clic ou Entrée/Espace) — le parent ouvre la fiche. */
  onSelect: (id: SolutionId) => void;
  /** Solution actuellement ouverte : la carte correspondante est marquée. */
  selectedId?: SolutionId | null;
}

/**
 * Géométrie des deux anneaux. `radius` est un pourcentage du demi-côté de la
 * scène ; `duration` la période de révolution (s). Les deux anneaux tournent à
 * des vitesses différentes et en sens opposés.
 */
const RINGS = [
  { radius: 24, duration: 78, reverse: false },
  { radius: 50, duration: 124, reverse: true },
] as const;

/** Nombre de cartes par anneau (5 + 5 = les 10 solutions du catalogue). */
const PER_RING = 5;

export function HeroOrbitCards({ onSelect, selectedId = null }: HeroOrbitCardsProps) {
  const { locale } = useVitrineLocale();

  const handleSelect = useCallback(
    (id: SolutionId) => () => onSelect(id),
    [onSelect],
  );

  return (
    <div
      data-testid="hero-orbit-cards"
      className="hero-orbit relative mx-auto aspect-square"
      // Hauteur DÉFINIE, et non `100%` : le parent est un `flex-1 min-h-0` dont
      // la hauteur se résout à 0 (la section n'a qu'un `min-height`), donc
      // `height: 100%` effondrait le conteneur. `min(56vh, 40rem, 96vw)` borne
      // la scène par la hauteur de fenêtre, une taille maximale confortable ET
      // la largeur disponible ; `aspect-square` en déduit la largeur, donc le
      // cercle reste entier et centré.
      style={{ height: 'min(56vh, 40rem, 96vw)' }}
    >
      {/* Anneaux de repère : purement décoratifs, ils matérialisent les
          trajectoires sans jamais capter un clic. */}
      {RINGS.map((ring) => (
        <div
          key={`ring-${ring.radius}`}
          aria-hidden="true"
          className="hero-orbit-ring-guide pointer-events-none absolute left-1/2 top-1/2 rounded-full border border-emerald-400/15"
          style={{
            width: `${ring.radius * 2}%`,
            height: `${ring.radius * 2}%`,
            transform: 'translate(-50%, -50%)',
          }}
        />
      ))}

      {/* Noyau : le socle commun autour duquel tout gravite. */}
      <div
        aria-hidden="true"
        className="hero-orbit-core pointer-events-none absolute left-1/2 top-1/2 h-[13%] w-[13%] -translate-x-1/2 -translate-y-1/2 rounded-full"
      />

      {RINGS.map((ring, ringIndex) => (
        <div
          key={`orbit-${ring.radius}`}
          className="hero-orbit-ring absolute inset-0"
          style={
            {
              '--orbit-dur': `${ring.duration}s`,
              '--orbit-dir': ring.reverse ? 'reverse' : 'normal',
            } as React.CSSProperties
          }
        >
          {SOLUTIONS.slice(ringIndex * PER_RING, (ringIndex + 1) * PER_RING).map(
            (solution, positionInRing) => {
              // Décalage angulaire : les deux anneaux sont désynchronisés (36°
              // d'écart) pour que les cartes ne s'alignent jamais en rayons.
              const angle = (positionInRing / PER_RING) * 360 + (ringIndex === 0 ? 0 : 36);
              // Variance de rayon par étoile (±2 %) : deux cartes d'un même
              // anneau ne décrivent pas exactement le même cercle.
              const radius = ring.radius + (positionInRing % 3) - 1;
              const rad = (angle * Math.PI) / 180;
              const left = 50 + radius * Math.cos(rad);
              const top = 50 + radius * Math.sin(rad);
              const label = solutionLabel(solution.id, locale);
              const isSelected = selectedId === solution.id;

              return (
                <button
                  key={solution.id}
                  type="button"
                  data-testid={`hero-orbit-card-${solution.id}`}
                  aria-label={label}
                  aria-haspopup="dialog"
                  aria-expanded={isSelected}
                  onClick={handleSelect(solution.id)}
                  className="hero-orbit-card max-w-[6rem] rounded-xl border border-white/10 bg-slate-950/70 text-center text-[10px] font-semibold leading-tight text-slate-100 shadow-lg shadow-slate-950/50 backdrop-blur-md transition-colors hover:border-emerald-400/50 hover:bg-slate-900/85 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-400 sm:text-[11px]"
                  style={
                    {
                      // Position STATIQUE sur le cercle (pourcentage du carré
                      // plein) : la trajectoire est exacte, seule la rotation
                      // de l'anneau la fait tourner.
                      left: `${left}%`,
                      top: `${top}%`,
                      // Phase : chaque carte démarre à un point différent de sa
                      // révolution, donc aucune ne suit la même.
                      '--orbit-delay': `${-(ring.duration * positionInRing) / PER_RING}s`,
                    } as React.CSSProperties
                  }
                >
                  {/* La contre-rotation vit ICI, sur un élément interne : la
                      carte garde son recentrage statique, donc son centre reste
                      exactement sur le cercle (cf. note dans globals.css). */}
                  <span className="hero-orbit-card-inner block px-2 py-1.5">
                    <span className="hero-orbit-card-dot mr-1.5 inline-block h-1.5 w-1.5 rounded-full bg-emerald-400 align-middle" />
                    {label}
                  </span>
                </button>
              );
            },
          )}
        </div>
      ))}
    </div>
  );
}

export default HeroOrbitCards;
