/**
 * Registre des vitrines verticales déclarées.
 *
 * Ajouter une verticale = ajouter une ligne ici. Le registre valide chaque
 * identité au chargement du module : une palette illisible ou un domaine
 * malformé fait échouer les tests, pas la production.
 */

import { createSurfaceRegistry } from './kernel';
import { HOSPITALITY_SURFACE } from './hospitality';
import { PHARMACY_SURFACE } from './pharmacy';
import { RESTAURANT_SURFACE } from './restaurants';
import { RETAIL_SURFACE } from './retail';
import { TRADE_SURFACE } from './trade';
import { TRAVEL_SURFACE } from './travel';
import { MOBILITY_SURFACE } from './vtc';

export const surfaceRegistry = createSurfaceRegistry([
  RESTAURANT_SURFACE,
  RETAIL_SURFACE,
  HOSPITALITY_SURFACE,
  TRADE_SURFACE,
  TRAVEL_SURFACE,
  PHARMACY_SURFACE,
  MOBILITY_SURFACE,
]);
