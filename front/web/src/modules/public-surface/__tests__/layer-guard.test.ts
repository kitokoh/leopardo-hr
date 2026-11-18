/**
 * #9580 (BC-41 BRANDING) — garde de couche du module `public-surface`.
 *
 * ## Ce que ce test protège
 *
 * L'audit DDD du 2026-11-17 a relevé 148 fichiers de route qui faisaient leur
 * propre I/O : la logique métier y était inséparable de la couche réseau, donc
 * non testable sans HTTP et dupliquée entre les pages.
 *
 * Ce test rend l'architecture OPPOSABLE sur le nouveau module :
 *
 *   domain/          ne doit RIEN importer d'au-dessus (couche la plus pure)
 *   components/      ne doit JAMAIS importer infrastructure/ (ni fetch)
 *   application/     peut importer domain + infrastructure (c'est sa couture)
 *   infrastructure/  peut importer domain, jamais components/
 *
 * Sans ce test, la règle serait une intention de revue de code — et une
 * intention ne survit pas à trois mois de pression produit.
 */

import { readFileSync, readdirSync, statSync } from 'node:fs';
import { join, relative } from 'node:path';

const MODULE_ROOT = join(__dirname, '..');

function collectSourceFiles(dir: string): string[] {
  const entries = readdirSync(dir);

  return entries.flatMap((entry) => {
    const full = join(dir, entry);

    if (statSync(full).isDirectory()) {
      // Les tests ne sont pas soumis à la règle : un test de composant peut
      // vouloir mocker la couche réseau.
      return entry === '__tests__' ? [] : collectSourceFiles(full);
    }

    return /\.(ts|tsx)$/.test(entry) ? [full] : [];
  });
}

function filesIn(layer: string): { path: string; content: string }[] {
  return collectSourceFiles(join(MODULE_ROOT, layer)).map((path) => ({
    path: relative(MODULE_ROOT, path),
    content: readFileSync(path, 'utf8'),
  }));
}

function importLines(content: string): string[] {
  return content
    .split('\n')
    .filter((line) => /^\s*(import|export)\s.+from\s+['"]/.test(line));
}

describe('public-surface — garde de couche', () => {
  it('domain/ n’importe ni application/, ni infrastructure/, ni components/', () => {
    const offenders: string[] = [];

    for (const file of filesIn('domain')) {
      for (const line of importLines(file.content)) {
        if (/from\s+['"]\.\.\/(application|infrastructure|components)/.test(line)) {
          offenders.push(`${file.path}: ${line.trim()}`);
        }
      }
    }

    expect(offenders).toEqual([]);
  });

  it('domain/ ne fait aucun appel réseau ni accès au navigateur', () => {
    const offenders: string[] = [];

    for (const file of filesIn('domain')) {
      const code = file.content;

      for (const forbidden of ['fetch(', 'localStorage', 'sessionStorage', 'next/headers', 'document.cookie']) {
        // On ignore les occurrences dans les commentaires : la documentation
        // du module cite explicitement ce qu'elle n'utilise pas.
        const codeLines = code
          .split('\n')
          .filter((line) => !/^\s*(\*|\/\/)/.test(line))
          .join('\n');

        if (codeLines.includes(forbidden)) {
          offenders.push(`${file.path}: ${forbidden}`);
        }
      }
    }

    expect(offenders).toEqual([]);
  });

  /**
   * La règle centrale : un composant reçoit des données déjà résolues. C'est
   * ce qui empêche un composant de décider d'un thème ou de refaire un appel.
   */
  it('components/ n’importe jamais infrastructure/', () => {
    const offenders: string[] = [];

    for (const file of filesIn('components')) {
      for (const line of importLines(file.content)) {
        if (/from\s+['"].*infrastructure/.test(line)) {
          offenders.push(`${file.path}: ${line.trim()}`);
        }
      }
    }

    expect(offenders).toEqual([]);
  });

  it('components/ ne fait aucun fetch', () => {
    const offenders: string[] = [];

    for (const file of filesIn('components')) {
      const codeLines = file.content
        .split('\n')
        .filter((line) => !/^\s*(\*|\/\/)/.test(line))
        .join('\n');

      if (codeLines.includes('fetch(')) {
        offenders.push(`${file.path}: fetch(`);
      }
    }

    expect(offenders).toEqual([]);
  });

  it('infrastructure/ n’importe jamais components/', () => {
    const offenders: string[] = [];

    for (const file of filesIn('infrastructure')) {
      for (const line of importLines(file.content)) {
        if (/from\s+['"].*components/.test(line)) {
          offenders.push(`${file.path}: ${line.trim()}`);
        }
      }
    }

    expect(offenders).toEqual([]);
  });

  /**
   * L'index est la seule surface consommable depuis l'extérieur : il ne doit
   * pas exposer `infrastructure/`, sinon un consommateur pourrait contourner
   * la validation du cas d'usage.
   */
  it('l’index du module n’exporte pas infrastructure/', () => {
    const index = readFileSync(join(MODULE_ROOT, 'index.ts'), 'utf8');
    const exported = importLines(index).filter((line) => line.includes('infrastructure'));

    // Seule exception : `loadSurfaceBySlug`/`loadSurfaceByHost` sont réexportés
    // depuis application/, qui elle-même branche l'infrastructure. L'index ne
    // doit contenir aucune ligne importante `infrastructure` directement.
    expect(exported.filter((line) => /from\s+['"]\.\/infrastructure/.test(line))).toEqual([]);
  });
});
