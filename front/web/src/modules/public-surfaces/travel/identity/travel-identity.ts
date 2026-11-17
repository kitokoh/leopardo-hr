/**
 * Identité de la vitrine Billets (Travel).
 *
 * Palette propre (violet/cyan) : registre du voyage, distinct du séjour et du
 * commerce. `onPrimary` est calculé. Nom et accroche viennent du catalogue.
 */

import { getVerticalCopy } from '../../copy/surfaces-copy';
import { buildPalette, type SurfaceIdentity } from '../../kernel';

const copy = getVerticalCopy('travel', 'fr');

export const TRAVEL_SURFACE: SurfaceIdentity = {
  vertical: 'travel',
  displayName: copy.displayName,
  tagline: copy.tagline,
  canonicalDomain: 'billets.leopardo.app',
  basePath: '/travel',
  discoveryType: 'travel',
  palette: buildPalette({
    primary: '#6D28D9',
    accent: '#06B6D4',
    surface: '#FAF8FF',
    onSurface: '#1B1035',
    muted: '#6B6480',
  }),
  locales: ['fr', 'en', 'tr', 'ar'],
};
