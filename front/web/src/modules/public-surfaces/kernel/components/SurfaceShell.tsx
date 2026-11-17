import type { CSSProperties, ReactNode } from 'react';

import type { SurfaceIdentity } from '../domain/surface-identity';

/**
 * Enveloppe de rendu d'une vitrine verticale.
 *
 * Elle ne fait qu'une chose : exposer la palette de l'identité en variables CSS
 * et poser le fond/texte. Aucun fetch, aucun état, aucune règle métier — le
 * rendu reste pur et testable sans réseau.
 */
export function SurfaceShell({
  identity,
  children,
}: {
  identity: SurfaceIdentity;
  children: ReactNode;
}) {
  const { palette } = identity;

  const style = {
    '--surface-primary': palette.primary,
    '--surface-on-primary': palette.onPrimary,
    '--surface-accent': palette.accent,
    '--surface-bg': palette.surface,
    '--surface-fg': palette.onSurface,
    '--surface-muted': palette.muted,
    backgroundColor: palette.surface,
    color: palette.onSurface,
  } as CSSProperties;

  return (
    <div data-surface={identity.vertical} style={style}>
      {children}
    </div>
  );
}
