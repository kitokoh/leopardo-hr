'use client';

/**
 * GeoBackdrop — cartes géographiques qui défilent sur un DEMI-CERCLE 3D
 * CONCAVE autour de la constellation (passe Fondateur, #8702).
 *
 * Le demi-cercle est « tourné vers le visiteur, par sa concavité » : la paroi
 * intérieure d'une cuvette. La géométrie réelle (translate3d + perspective,
 * voir le bloc `.geo-arc` de `globals.css`) donne :
 *
 *   gauche (PROCHE, présente) → sommet (LOINTAIN, plonge derrière le plan de
 *   la constellation) → droite (PROCHE) → sortie du demi-cercle, puis la
 *   suivante.
 *
 * La taille suit la profondeur RÉELLE (le navigateur la calcule, ce n'est pas
 * un scale() simulé) et chaque carte pivote légèrement (rotateY), plaquée sur
 * la paroi : la concavité se lit à l'œil. La carte au sommet est la plus
 * éloignée — elle ne concurrence plus jamais les étoiles (défaut relevé en
 * vague 5), et les deux bouts qui reviennent vers le visiteur encadrent la
 * constellation comme deux ailes. Le calque reste en `z-[1]` (la scène WebGL
 * est en `z-10`) : aucune carte ne passe devant les étoiles à l'écran, la
 * profondeur étant portée par la taille et l'opacité.
 *
 * Ordre de défilement : d'abord les CONTINENTS (Afrique, Europe, Asie), puis
 * les PAYS (France, Algérie, Turquie, Tchad, Maroc, Tunisie, Sénégal, Arabic
 * Saoudite). Le cycle se répète.
 *
 * Intérieur VIDE : chaque carte n'est qu'une silhouette — un fond teinté, un
 * contour fin, rien d'autre (ni texte, ni grille, ni détail intérieur). C'est
 * un décor de profondeur, pas une carte à lire.
 *
 * Tracés exacts Natural Earth 1:110m (domaine public) — voir
 * `tools/cartography/make_maps.py`. Aucune silhouette aléatoire.
 *
 * Performance / accessibilité : l'animation est 100 % CSS (`transform` +
 * `opacity` seulement, composées par le GPU), aucun `requestAnimationFrame`,
 * aucun état React qui change en boucle. Sous `prefers-reduced-motion:
 * reduce`, ou si les animations ne démarrent pas, une seule carte statique
 * s'affiche : le décor reste lisible, la constellation reste dominante.
 * `aria-hidden` : rien à lire, rien à cliquer.
 */

import { MAP_OUTLINES, type MapKey } from '../../data/geo/map-paths';

/**
 * Ordre de défilement : les continents d'abord, puis les pays.
 * Chaque entrée = une carte du demi-cercle.
 */
const ARC_ORDER: readonly MapKey[] = [
  // Continents
  'africa',
  'europe',
  'asia',
  // Pays
  'france',
  'algeria',
  'turkey',
  'chad',
  'morocco',
  'tunisia',
  'senegal',
  'saudi',
];

/**
 * Durée d'un tour complet du demi-cercle, et décalage entre deux cartes.
 * `--geo-count` porte le nombre de cartes ; le décalage négatif fait entrer
 * les cartes en continu, une par une, sans jamais toutes réapparaître d'un
 * coup. Le cycle complet est long (lent = élégant, jamais « clignotant »).
 */
const CYCLE_SECONDS = 54;

export function GeoBackdrop({ locale }: { locale?: string }) {
  void locale; // décoratif : aucun texte, donc aucune locale à lire

  return (
    <div
      aria-hidden="true"
      className="geo-arc pointer-events-none absolute inset-0 z-[1] overflow-hidden"
      style={{ ['--geo-count' as string]: String(ARC_ORDER.length) }}
    >
      {ARC_ORDER.map((key, index) => {
        const outline = MAP_OUTLINES[key];
        return (
          <div
            key={key}
            className="geo-arc-card"
            style={{ animationDelay: `${-(index * CYCLE_SECONDS) / ARC_ORDER.length}s` }}
          >
            <svg
              viewBox="0 0 100 100"
              preserveAspectRatio="xMidYMid meet"
              focusable="false"
              className="h-full w-full"
            >
              {/* Silhouette vide : un fond, un contour. Rien à l'intérieur. */}
              <path d={outline.d} fill="currentColor" fillOpacity={0.1} stroke="none" />
              <path
                d={outline.d}
                fill="none"
                stroke="currentColor"
                strokeWidth={0.45}
                strokeOpacity={0.55}
                strokeLinejoin="round"
              />
            </svg>
          </div>
        );
      })}
    </div>
  );
}

export default GeoBackdrop;
