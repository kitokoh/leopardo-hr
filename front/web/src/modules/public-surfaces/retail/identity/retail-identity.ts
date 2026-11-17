/**
 * Identité de la vitrine Retail (Marché).
 *
 * Palette propre à la verticale (indigo/ardoise) : registre du commerce de
 * détail, distinct du chaud restaurant. `onPrimary` est calculé.
 */

import { buildPalette, type SurfaceIdentity } from '../../kernel';

export const RETAIL_SURFACE: SurfaceIdentity = {
  vertical: 'retail',
  displayName: 'Leopardo Marché',
  tagline: 'Les boutiques près de chez vous, leur catalogue et leurs nouveautés.',
  canonicalDomain: 'marche.leopardo.app',
  basePath: '/boutiques',
  discoveryType: 'retail_shop',
  palette: buildPalette({
    primary: '#4338CA',
    accent: '#0EA5E9',
    surface: '#F8FAFC',
    onSurface: '#0F172A',
    muted: '#64748B',
  }),
  locales: ['fr', 'en', 'tr', 'ar'],
};
