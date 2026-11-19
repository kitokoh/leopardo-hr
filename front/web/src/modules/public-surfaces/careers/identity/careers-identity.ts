/**
 * Identité de la vitrine Emploi (Careers).
 *
 * Palette propre (vert profond / ambre) : registre du recrutement, distinct du
 * commerce et du voyage. `onPrimary` est calculé. Nom et accroche viennent du
 * catalogue de copie.
 */

import { getVerticalCopy } from '../../copy/surfaces-copy';
import { buildPalette, type SurfaceIdentity } from '../../kernel';

const copy = getVerticalCopy('careers', 'fr');

export const CAREERS_SURFACE: SurfaceIdentity = {
  vertical: 'careers',
  displayName: copy.displayName,
  tagline: copy.tagline,
  canonicalDomain: 'emploi.leopardo.app',
  basePath: '/emploi',
  discoveryType: 'careers',
  palette: buildPalette({
    primary: '#166534',
    accent: '#F59E0B',
    surface: '#F7FDF9',
    onSurface: '#052E1B',
    muted: '#5B6B62',
  }),
  locales: ['fr', 'en', 'tr', 'ar'],
};
