/**
 * Identité de la vitrine Pharmacie.
 *
 * Palette propre (vert clinique) : registre de la santé, distinct du retail et
 * du séjour. `onPrimary` est calculé.
 */

import { buildPalette, type SurfaceIdentity } from '../../kernel';

export const PHARMACY_SURFACE: SurfaceIdentity = {
  vertical: 'pharmacy',
  displayName: 'Leopardo Pharmacies',
  tagline: 'Trouvez une officine, ses horaires et sa garde, près de chez vous.',
  canonicalDomain: 'pharmacies.leopardo.app',
  basePath: '/pharmacies',
  discoveryType: 'pharmacy',
  palette: buildPalette({
    primary: '#047857',
    accent: '#22C55E',
    surface: '#F7FBF8',
    onSurface: '#0A1F16',
    muted: '#5B7A6B',
  }),
  locales: ['fr', 'en', 'tr', 'ar'],
};
