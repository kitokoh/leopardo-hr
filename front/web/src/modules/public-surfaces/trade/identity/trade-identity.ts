/**
 * Identité de la vitrine B2B (Trade).
 *
 * Palette propre (bleu nuit / acier) : registre du commerce international,
 * distinct des verticales grand public de proximité. `onPrimary` est calculé.
 * Nom et accroche viennent du catalogue de copie.
 */

import { getVerticalCopy } from '../../copy/surfaces-copy';
import { buildPalette, type SurfaceIdentity } from '../../kernel';

const copy = getVerticalCopy('trade', 'fr');

export const TRADE_SURFACE: SurfaceIdentity = {
  vertical: 'trade',
  displayName: copy.displayName,
  tagline: copy.tagline,
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
