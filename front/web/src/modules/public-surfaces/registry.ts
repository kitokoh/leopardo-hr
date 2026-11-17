/**
 * Registre des vitrines verticales déclarées.
 *
 * Ajouter une verticale = ajouter une ligne ici. Le registre valide chaque
 * identité au chargement du module : une palette illisible ou un domaine
 * malformé fait échouer les tests, pas la production.
 */

import { createSurfaceRegistry } from './kernel';
import { RESTAURANT_SURFACE } from './restaurants';

export const surfaceRegistry = createSurfaceRegistry([RESTAURANT_SURFACE]);
