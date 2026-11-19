/**
 * Client du registre multi-pays — **infrastructure** (#9658).
 *
 * Ce fichier possède l'appel réseau que le domaine n'a pas le droit de faire.
 * Il a été extrait de `data/supported-countries.ts` (devenu
 * `domain/supported-countries.ts`) : la garde de couche web refuse `fetch(`
 * dans un `domain/`, et pour une bonne raison — un domaine qui appelle le
 * réseau n'est testable qu'avec un mock de `fetch`, donc il ne teste plus la
 * règle mais le mock.
 *
 * La **règle** (filtrer les pays indisponibles, ignorer un code non alpha-2,
 * retomber sur le registre statique) est restée dans le domaine, sous
 * `normalizeSupportedCountries`. Ce fichier ne fait que transporter.
 */

import {
  SUPPORTED_COUNTRIES_FALLBACK,
  normalizeSupportedCountries,
  type SupportedCountryOption,
} from '../domain/supported-countries';

/**
 * Récupère les pays supportés (disponibles) via le proxy same-origin.
 * En cas d'échec (hors ligne, cold-start Render), retombe sur le registre
 * statique — le funnel ne doit jamais être bloqué par le réseau.
 */
export async function fetchSupportedCountries(): Promise<SupportedCountryOption[]> {
  try {
    const res = await fetch('/api/v1/supported-countries', {
      headers: { Accept: 'application/json' },
    });
    if (!res.ok) return SUPPORTED_COUNTRIES_FALLBACK;
    return normalizeSupportedCountries(await res.json());
  } catch {
    return SUPPORTED_COUNTRIES_FALLBACK;
  }
}
