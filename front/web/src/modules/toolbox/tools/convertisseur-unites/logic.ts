import { isFiniteNumber, safeDivide } from '../../lib/validation';

/**
 * Convertisseur d'unités.
 *
 * Principe : chaque unité porte un FACTEUR vers l'unité de base de sa
 * catégorie. Conversion : `valeur × facteur(source) ÷ facteur(cible)`.
 * Les facteurs sont EXACTS (définitions SI / internationales) :
 *   - longueur  : base mètre (1 in = 0,0254 m exactement)
 *   - masse     : base kilogramme (1 lb = 0,45359237 kg exactement)
 *   - volume    : base litre (1 gal US = 3,785411784 L exactement)
 *   - surface   : base mètre carré (1 acre = 4046,8564224 m² exactement)
 *   - vitesse   : base mètre par seconde (1 mph = 0,44704 m/s exactement)
 *   - température : formules AFFINES (pas un simple facteur) — traitées à part.
 * Référence : BIPM — brochure SI.
 */

export type UnitCategoryId =
  | 'longueur'
  | 'masse'
  | 'volume'
  | 'surface'
  | 'vitesse'
  | 'temperature';

export interface UnitDef {
  id: string;
  /** Valeur d'une unité exprimée dans l'unité de base de la catégorie. */
  factor: number;
}

export const UNIT_CATEGORIES: Record<UnitCategoryId, UnitDef[]> = {
  longueur: [
    { id: 'mm', factor: 0.001 },
    { id: 'cm', factor: 0.01 },
    { id: 'm', factor: 1 },
    { id: 'km', factor: 1000 },
    { id: 'in', factor: 0.0254 },
    { id: 'ft', factor: 0.3048 },
    { id: 'yd', factor: 0.9144 },
    { id: 'mi', factor: 1609.344 },
  ],
  masse: [
    { id: 'mg', factor: 0.000001 },
    { id: 'g', factor: 0.001 },
    { id: 'kg', factor: 1 },
    { id: 't', factor: 1000 },
    { id: 'oz', factor: 0.028349523125 },
    { id: 'lb', factor: 0.45359237 },
  ],
  volume: [
    { id: 'ml', factor: 0.001 },
    { id: 'cl', factor: 0.01 },
    { id: 'l', factor: 1 },
    { id: 'm3', factor: 1000 },
    { id: 'gal_us', factor: 3.785411784 },
    { id: 'gal_uk', factor: 4.54609 },
  ],
  surface: [
    { id: 'cm2', factor: 0.0001 },
    { id: 'm2', factor: 1 },
    { id: 'km2', factor: 1000000 },
    { id: 'ha', factor: 10000 },
    { id: 'ft2', factor: 0.09290304 },
    { id: 'acre', factor: 4046.8564224 },
  ],
  vitesse: [
    { id: 'mps', factor: 1 },
    { id: 'kmh', factor: 1 / 3.6 },
    { id: 'mph', factor: 0.44704 },
    { id: 'knot', factor: 1852 / 3600 },
  ],
  temperature: [
    { id: 'c', factor: 1 },
    { id: 'f', factor: 1 },
    { id: 'k', factor: 1 },
  ],
};

/**
 * Convertit une valeur entre deux unités d'une même catégorie.
 * Retourne `null` si la catégorie/les unités sont inconnues, si les unités
 * diffèrent de catégorie, ou si la valeur n'est pas finie.
 */
export function convertUnit(
  category: UnitCategoryId,
  fromId: string,
  toId: string,
  value: number,
): number | null {
  if (!isFiniteNumber(value)) return null;
  const units = UNIT_CATEGORIES[category];
  if (!units) return null;

  const from = units.find((unit) => unit.id === fromId);
  const to = units.find((unit) => unit.id === toId);
  if (!from || !to) return null;

  if (category === 'temperature') {
    return convertTemperature(fromId, toId, value);
  }

  const inBase = value * from.factor;
  return safeDivide(inBase, to.factor);
}

/**
 * Conversion de température via l'échelle Celsius (formules affines) :
 *   F = C × 9/5 + 32 ; K = C + 273,15.
 */
export function convertTemperature(fromId: string, toId: string, value: number): number | null {
  const toCelsius = (v: number): number | null => {
    if (fromId === 'c') return v;
    if (fromId === 'f') return ((v - 32) * 5) / 9;
    if (fromId === 'k') return v - 273.15;
    return null;
  };

  const celsius = toCelsius(value);
  if (celsius === null) return null;

  if (toId === 'c') return celsius;
  if (toId === 'f') return (celsius * 9) / 5 + 32;
  if (toId === 'k') return celsius + 273.15;
  return null;
}
