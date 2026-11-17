/**
 * #9580 (BC-41 BRANDING) — coquille de la surface publique.
 *
 * ## Ce composant ne fait AUCUNE E/S
 *
 * Il reçoit un `ResolvedSurface` déjà résolu et rend. Pas de `fetch`, pas de
 * `useEffect`, pas de `localStorage` : c'est la règle de couche du module, et
 * un test de garde la vérifie (`__tests__/layer-guard.test.ts`).
 *
 * ## Pourquoi les variables CSS sont posées ici
 *
 * Une seule fois, sur le conteneur racine. Les composants enfants consomment
 * `var(--brand-primary)`. Si chaque composant recevait la palette en prop pour
 * la mettre en `style`, ajouter une teinte demain demanderait de tous les
 * toucher — et le mode sombre deviendrait impossible.
 */

import type { ReactNode } from 'react';

import type { ResolvedSurface } from '../application/resolve-surface';
import { themeVariablesToStyle } from '../domain/theme';

interface PublicSurfaceShellProps {
  surface: ResolvedSurface;
  children: ReactNode;
}

export function PublicSurfaceShell({ surface, children }: PublicSurfaceShellProps) {
  const { brand, tokens } = surface;

  return (
    <div
      dir={tokens.direction}
      data-density={tokens.density}
      data-vertical={brand.vertical}
      style={parseStyleString(themeVariablesToStyle(tokens))}
      className="min-h-screen bg-[var(--brand-surface)] text-slate-900 antialiased"
    >
      {children}
    </div>
  );
}

/**
 * Convertit la chaîne `--x: y; --z: w` en objet `style` React.
 *
 * React accepte les propriétés personnalisées dans `style` à l'exécution, mais
 * le type `CSSProperties` ne les connaît pas. On construit donc un objet
 * `Record<string, string>` et on le passe tel quel : pas de cast `as any`, pas
 * de `dangerouslySetInnerHTML`.
 */
function parseStyleString(style: string): Record<string, string> {
  const result: Record<string, string> = {};

  for (const declaration of style.split(';')) {
    const index = declaration.indexOf(':');

    if (index === -1) {
      continue;
    }

    const key = declaration.slice(0, index).trim();
    const value = declaration.slice(index + 1).trim();

    if (key !== '' && value !== '') {
      result[key] = value;
    }
  }

  return result;
}
