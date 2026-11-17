/**
 * Identité de la vitrine Restaurant.
 *
 * C'est **l'identité de la surface publique**, pas une marque de tenant : elle
 * est fixe, connue à la compilation, et propre à cette verticale. Aucune autre
 * vitrine ne partage ces valeurs.
 *
 * Le nom et l'accroche viennent du catalogue de copie (`copy/surfaces-copy`) :
 * une seule source de vérité éditoriale, servie en 4 langues. La palette est
 * chaude (braise/ambre) parce que c'est le registre visuel du restaurant ;
 * `onPrimary` est **calculé** par `buildPalette`, donc le CTA reste lisible même
 * si la teinte change.
 */

import { getVerticalCopy } from '../../copy/surfaces-copy';
import { buildPalette, type SurfaceIdentity } from '../../kernel';

const copy = getVerticalCopy('restaurant', 'fr');

export const RESTAURANT_SURFACE: SurfaceIdentity = {
  vertical: 'restaurant',
  displayName: copy.displayName,
  tagline: copy.tagline,
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
