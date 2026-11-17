/**
 * Cas d'usage : résoudre la vitrine d'une verticale.
 *
 * Volontairement mince : la décision « cette verticale a-t-elle une vitrine ? »
 * appartient au registre, pas à la page. Une page qui reçoit `null` doit
 * répondre 404 — jamais afficher une vitrine générique qui promettrait des
 * fonctionnalités inexistantes.
 */

import type { SurfaceIdentity } from '../domain/surface-identity';
import type { SurfaceRegistry } from '../domain/surface-registry';

/** Résultat de résolution : l'identité, ou `null` (fail-closed). */
export function resolveSurface(
  registry: SurfaceRegistry,
  vertical: string,
): SurfaceIdentity | null {
  return registry.get(vertical);
}

/**
 * Résout la vitrine ou lève une erreur explicite.
 *
 * Réservé aux contextes où l'absence est un défaut de configuration (script de
 * build, sitemap) et non une requête visiteur.
 */
export function requireSurface(registry: SurfaceRegistry, vertical: string): SurfaceIdentity {
  const identity = registry.get(vertical);

  if (identity === null) {
    throw new Error(`Aucune vitrine déclarée pour la verticale : ${vertical}`);
  }

  return identity;
}
