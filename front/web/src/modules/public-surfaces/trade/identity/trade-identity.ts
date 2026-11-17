/**
 * Identité de la vitrine B2B (Trade).
 *
 * Palette propre (bleu nuit / acier) : registre du commerce international,
 * distinct des verticales grand public de proximité. `onPrimary` est calculé.
 */

import { buildPalette, type SurfaceIdentity } from '../../kernel';

export const TRADE_SURFACE: SurfaceIdentity = {
  vertical: 'trade',
  displayName: 'Leopardo Entreprises',
  tagline: 'Fournisseurs et négociants vérifiés — import, export et marché domestique.',
  canonicalDomain: 'entreprises.leopardo.app',
  basePath: '/entreprises',
  discoveryType: 'trade_company',
  palette: buildPalette({
    primary: '#1E3A8A',
    accent: '#3B82F6',
    surface: '#F8FAFC',
    onSurface: '#0B1220',
    muted: '#64748B',
  }),
  locales: ['fr', 'en', 'tr', 'ar'],
};
