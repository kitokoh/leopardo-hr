/**
 * Identité de la vitrine Pharmacie.
 *
 * Palette propre (vert clinique) : registre de la santé, distinct du retail et
 * du séjour. `onPrimary` est calculé. Nom et accroche viennent du catalogue.
 */

import { getVerticalCopy } from '../../copy/surfaces-copy';
import { buildPalette, type SurfaceIdentity } from '../../kernel';

const copy = getVerticalCopy('pharmacy', 'fr');

export const PHARMACY_SURFACE: SurfaceIdentity = {
  vertical: 'pharmacy',
  displayName: copy.displayName,
  tagline: copy.tagline,
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
