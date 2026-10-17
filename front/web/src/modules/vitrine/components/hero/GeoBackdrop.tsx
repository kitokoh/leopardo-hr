'use client';

/**
 * GeoBackdrop — cartes géographiques qui défilent sur un DEMI-CERCLE derrière
 * la constellation.
 *
 * Retour utilisateur (vague 5) : les cartes passaient au milieu de la scène et
 * concurrençaient les étoiles. Elles décrivent désormais un demi-cercle qui
 * ENTURE la constellation :
 *
 *   gauche (petite, estompée) → centre (la plus grande, DERRIÈRE les étoiles)
 *   → droite (petite, estompée) → sortie du demi-cercle, puis la suivante.
 *
 * La taille suit la position : une carte grossit à mesure qu'elle s'approche du
 * centre (elle « passe derrière » la constellation, donc au plus près) et
 * rapetisse en sortant. Aucune carte n'est jamais devant : le calque est en
 * `z-[1]`, la scène WebGL en `z-10` — la constellation reste TOUJOURS au
 * premier plan.
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
