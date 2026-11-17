/**
 * Identité de la vitrine Restaurant.
 *
 * C'est **l'identité de la surface publique**, pas une marque de tenant : elle
 * est fixe, connue à la compilation, et propre à cette verticale. Aucune autre
 * vitrine ne partage ces valeurs.
 *
 * La palette est chaude (braise/ambre) parce que c'est le registre visuel du
 * restaurant ; `onPrimary` est **calculé** par `buildPalette`, donc le CTA reste
 * lisible même si la teinte change.
 */

import { buildPalette, type SurfaceIdentity } from '../../kernel';

export const RESTAURANT_SURFACE: SurfaceIdentity = {
  vertical: 'restaurant',
  displayName: 'Leopardo Restaurants',
  tagline: 'Trouvez votre table, découvrez la carte, réservez en deux gestes.',
  canonicalDomain: 'restaurants.leopardo.app',
  basePath: '/restaurants',
  discoveryType: 'restaurant',
  palette: buildPalette({
    primary: '#C2410C',
    accent: '#F59E0B',
    surface: '#FFFBF5',
    onSurface: '#1C1917',
    muted: '#78716C',
  }),
  locales: ['fr', 'en', 'tr', 'ar'],
};
