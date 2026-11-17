/**
 * Identité de la vitrine Hôtellerie (Stay).
 *
 * Palette propre (teal profond) : registre du séjour, distinct du retail et du
 * restaurant. `onPrimary` est calculé.
 */

import { buildPalette, type SurfaceIdentity } from '../../kernel';

export const HOSPITALITY_SURFACE: SurfaceIdentity = {
  vertical: 'hospitality',
  displayName: 'Leopardo Séjours',
  tagline: 'Hôtels, résidences et maisons d’hôtes — disponibilités et réservation directe.',
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
