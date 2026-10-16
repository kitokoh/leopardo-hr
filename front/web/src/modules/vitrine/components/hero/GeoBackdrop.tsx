'use client';

/**
 * GeoBackdrop — cartes géographiques en arrière-plan de la constellation.
 *
 * Option A retenue (transition successive) : une carte s'efface pendant que la
 * suivante apparaît — fondu croisé lent, aucune carte ne coexiste avec une
 * autre, la constellation reste l'élément visuel dominant.
 *
 * Cycle : France → Algérie → Turquie → Tchad → continent africain. La France
 * n'est donc plus un décor figé (elle était seule, en dur dans `HeroSection`).
 *
 * Direction artistique : un plan DERRIÈRE la scène WebGL, opacité maîtrisée
 * (la silhouette se lit sans concurrencer les étoiles, les libellés ni le CTA).
 * Tracés exacts Natural Earth (voir `data/geo/map-paths.ts`) — pas de
 * silhouette aléatoire. Décoratif : `aria-hidden`, aucun texte (rien à
 * traduire), jamais cliquable.
 *
 * Accessibilité : sous `prefers-reduced-motion: reduce`, l'intervalle n'est
 * jamais posé — seule la première carte (la France) reste affichée, sans
 * fondu (la transition est neutralisée en CSS). Si `matchMedia` ou les
 * animations avancées ne sont pas disponibles, on affiche la France statique :
 * aucune information n'est perdue, le décor est purement visuel.
 */

import { useEffect, useState } from 'react';
import { MAP_OUTLINES, type MapKey } from '../../data/geo/map-paths';

/** Ordre du cycle. La France ouvre le cycle (continuité avec l'existant). */
const MAP_ORDER: readonly MapKey[] = ['france', 'algeria', 'turkey', 'chad', 'africa'];

/** Durée d'affichage d'une carte (le fondu CSS dure 1,4 s). */
const HOLD_MS = 9000;

/** Classes de cadrage par carte : l'Afrique (plus large que haute) respire. */
const MAP_SIZE: Record<MapKey, string> = {
  france: 'h-[86vmin] w-[86vmin]',
  algeria: 'h-[86vmin] w-[86vmin]',
  turkey: 'h-[86vmin] w-[86vmin]',
  chad: 'h-[86vmin] w-[86vmin]',
  africa: 'h-[78vmin] w-[78vmin]',
};

const REDUCED_MOTION_QUERY = '(prefers-reduced-motion: reduce)';

export function GeoBackdrop({ locale }: { locale?: string }) {
  void locale; // décoratif : aucun texte, donc aucune locale à lire
  const [index, setIndex] = useState(0);
  const [rotating, setRotating] = useState(false);

  useEffect(() => {
    // jsdom (tests) et très vieux navigateurs : pas de `matchMedia` → la
    // France statique, sans erreur.
    if (typeof window.matchMedia !== 'function') return undefined;
    if (window.matchMedia(REDUCED_MOTION_QUERY).matches) return undefined;

    setRotating(true);
    const timer = window.setInterval(() => {
      setIndex((current) => (current + 1) % MAP_ORDER.length);
    }, HOLD_MS);
    return () => window.clearInterval(timer);
  }, []);

  return (
    <div
      aria-hidden="true"
      className="pointer-events-none absolute inset-0 flex items-center justify-center overflow-hidden"
    >
      {MAP_ORDER.map((key, position) => {
        // Sans rotation (reduced-motion / matchMedia absent) : seule la
        // première carte est rendue — pas de couche cachée inutile.
        if (!rotating && position !== 0) return null;
        const active = position === index;
        return (
          <svg
            key={key}
            viewBox="0 0 100 100"
            preserveAspectRatio="xMidYMid meet"
            focusable="false"
            className={`geo-map-layer absolute shrink-0 ${MAP_SIZE[key]} ${
              active ? 'opacity-[0.22]' : 'opacity-0'
            }`}
          >
            <path
              d={MAP_OUTLINES[key].d}
              fill="#10b981"
              fillOpacity={0.16}
              stroke="#06b6d4"
              strokeWidth={0.32}
              strokeOpacity={0.75}
              strokeLinejoin="round"
            />
          </svg>
        );
      })}
    </div>
  );
}

export default GeoBackdrop;
