/**
 * Registre des vitrines verticales.
 *
 * Le registre est la **seule** porte d'entrée vers une identité de vitrine. Il
 * est construit explicitement (pas de découverte dynamique) : ajouter une
 * verticale est un acte volontaire, visible en revue, et une verticale absente
 * échoue proprement au lieu de retomber sur une identité générique.
 *
 * Invariants :
 * - une verticale n'est déclarée qu'une fois (doublon → erreur au démarrage) ;
 * - chaque identité est validée par `assertSurfaceIdentity` ;
 * - `get()` est **fail-closed** : verticale inconnue → `null`, jamais un défaut.
 */

import {
  assertSurfaceIdentity,
  type SurfaceIdentity,
  type SurfaceVertical,
} from './surface-identity';

export interface SurfaceRegistry {
  /** Identité de la verticale, ou `null` si elle n'est pas déclarée. */
  get(vertical: string): SurfaceIdentity | null;
  /** Toutes les identités déclarées, dans l'ordre de déclaration. */
  all(): readonly SurfaceIdentity[];
  /** Vrai si la verticale est déclarée. */
  has(vertical: string): boolean;
  /** Verticales déclarées. */
  verticals(): readonly SurfaceVertical[];
}

/**
 * Construit un registre à partir d'identités.
 *
 * @throws si deux identités partagent la même verticale, ou si l'une est invalide.
 */
export function createSurfaceRegistry(
  identities: readonly SurfaceIdentity[],
): SurfaceRegistry {
  const byVertical = new Map<SurfaceVertical, SurfaceIdentity>();

  for (const identity of identities) {
    assertSurfaceIdentity(identity);

    if (byVertical.has(identity.vertical)) {
      throw new Error(`Vitrine déclarée deux fois pour la verticale : ${identity.vertical}`);
    }

    byVertical.set(identity.vertical, identity);
  }

  return {
    get(vertical: string): SurfaceIdentity | null {
      return byVertical.get(vertical as SurfaceVertical) ?? null;
    },
    all(): readonly SurfaceIdentity[] {
      return [...byVertical.values()];
    },
    has(vertical: string): boolean {
      return byVertical.has(vertical as SurfaceVertical);
    },
    verticals(): readonly SurfaceVertical[] {
      return [...byVertical.keys()];
    },
  };
}
