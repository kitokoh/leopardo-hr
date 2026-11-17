/**
 * Identité de la vitrine Hôtellerie (Stay).
 *
 * Palette propre (teal profond) : registre du séjour, distinct du retail et du
 * restaurant. `onPrimary` est calculé. Nom et accroche viennent du catalogue.
 */

import { getVerticalCopy } from '../../copy/surfaces-copy';
import { buildPalette, type SurfaceIdentity } from '../../kernel';

const copy = getVerticalCopy('hospitality', 'fr');

export const HOSPITALITY_SURFACE: SurfaceIdentity = {
  vertical: 'hospitality',
  displayName: copy.displayName,
  tagline: copy.tagline,
  canonicalDomain: 'sejours.leopardo.app',
  basePath: '/stay',
  discoveryType: 'hospitality_property',
  palette: buildPalette({
    primary: '#0F766E',
    accent: '#14B8A6',
    surface: '#F7FAF9',
    onSurface: '#0B1F1C',
    muted: '#5F7A76',
  }),
  locales: ['fr', 'en', 'tr', 'ar'],
};
