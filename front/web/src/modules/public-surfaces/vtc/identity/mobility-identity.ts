/**
 * Identité de la vitrine Mobilité (VTC / taxi).
 *
 * Palette propre (graphite chaud / jaune signal) : registre du trajet urbain,
 * distinct du voyage longue distance (violet Billets), du séjour (teal) et du
 * commerce (indigo). `onPrimary` est calculé.
 *
 * ## Pourquoi un domaine séparé de `travel`
 *
 * `billets.leopardo.app` vend un trajet **daté et réservé à l'avance**. La
 * mobilité vend une **course immédiate** dans une ville. Deux temporalités,
 * deux publics, deux parcours : les réunir aurait forcé la vitrine Billets à
 * porter un mode de recherche qui n'est pas le sien.
 */

import { getVerticalCopy } from '../../copy/surfaces-copy';
import { buildPalette, type SurfaceIdentity } from '../../kernel';

const copy = getVerticalCopy('vtc', 'fr');

export const MOBILITY_SURFACE: SurfaceIdentity = {
  vertical: 'vtc',
  displayName: copy.displayName,
  tagline: copy.tagline,
  canonicalDomain: 'mobilite.leopardo.app',
  basePath: '/operateurs',
  discoveryType: 'vtc_operator',
  palette: buildPalette({
    primary: '#1F2937',
    accent: '#F59E0B',
    surface: '#FBFAF8',
    onSurface: '#111827',
    muted: '#6B7280',
  }),
  locales: ['fr', 'en', 'tr', 'ar'],
};
