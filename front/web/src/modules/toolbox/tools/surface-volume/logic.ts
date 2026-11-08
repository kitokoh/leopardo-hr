import { isFiniteNumber } from '../../lib/validation';

/**
 * Surfaces et volumes des formes géométriques usuelles.
 *
 * Toutes les longueurs sont dans la MÊME unité (m, cm, m… au choix) ; les
 * surfaces sont retournées dans cette unité au carré et les volumes dans cette
 * unité au cube. Aucune conversion n'est faite ici (utiliser le convertisseur
 * d'unités pour changer d'échelle).
 *
 * Formules (références : formules géométriques élémentaires, π = Math.PI) :
 *   - carré        : aire = a²
 *   - rectangle    : aire = L × l
 *   - triangle     : aire = b × h ÷ 2
 *   - cercle       : aire = π × r²
 *   - cube         : volume = a³ ; aire totale = 6 × a²
 *   - pavé droit   : volume = L × l × h ; aire totale = 2 × (L×l + L×h + l×h)
 *   - cylindre     : volume = π × r² × h ; aire latérale = 2 × π × r × h
 *   - sphère       : volume = 4/3 × π × r³ ; aire = 4 × π × r²
 *
 * Hypothèses : dimensions strictement positives (une dimension nulle ou
 * négative n'a pas de sens géométrique → `null`). Résultats THÉORIQUES :
 * ils ne constituent pas une validation de sécurité ni un métré officiel.
 */

export type ShapeId =
  | 'carre'
  | 'rectangle'
  | 'triangle'
  | 'cercle'
  | 'cube'
  | 'pave-droit'
  | 'cylindre'
  | 'sphere';

export interface ShapeResult {
  /** Aire (unité²) — `null` si la forme n'a pas d'aire calculée ici. */
  area: number | null;
  /** Volume (unité³) — `null` pour les formes planes. */
  volume: number | null;
}

/** Vrai si toutes les dimensions sont des nombres finis strictement positifs. */
function allPositive(...values: number[]): boolean {
  return values.every((value) => isFiniteNumber(value) && value > 0);
}

/**
 * Calcule l'aire et/ou le volume d'une forme.
 * Retourne `null` si une dimension est manquante, non finie ou ≤ 0.
 */
export function computeShape(
  shape: ShapeId,
  dimensions: Record<string, number>,
): ShapeResult | null {
  const { a, length, width, height, base, radius } = dimensions;

  switch (shape) {
    case 'carre': {
      if (!allPositive(a)) return null;
      return { area: a * a, volume: null };
    }
    case 'rectangle': {
      if (!allPositive(length, width)) return null;
      return { area: length * width, volume: null };
    }
    case 'triangle': {
      if (!allPositive(base, height)) return null;
      return { area: (base * height) / 2, volume: null };
    }
    case 'cercle': {
      if (!allPositive(radius)) return null;
      return { area: Math.PI * radius * radius, volume: null };
    }
    case 'cube': {
      if (!allPositive(a)) return null;
      return { area: 6 * a * a, volume: a * a * a };
    }
    case 'pave-droit': {
      if (!allPositive(length, width, height)) return null;
      return {
        area: 2 * (length * width + length * height + width * height),
        volume: length * width * height,
      };
    }
    case 'cylindre': {
      if (!allPositive(radius, height)) return null;
      return {
        area: 2 * Math.PI * radius * height,
        volume: Math.PI * radius * radius * height,
      };
    }
    case 'sphere': {
      if (!allPositive(radius)) return null;
      return {
        area: 4 * Math.PI * radius * radius,
        volume: (4 / 3) * Math.PI * radius * radius * radius,
      };
    }
    default:
      return null;
  }
}
