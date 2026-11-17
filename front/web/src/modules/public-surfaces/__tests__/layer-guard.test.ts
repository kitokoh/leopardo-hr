import { readdirSync, readFileSync, statSync } from 'node:fs';
import { join, relative } from 'node:path';

/**
 * Garde de couche des vitrines publiques.
 *
 * La règle DDD du module est opposée par un TEST, pas par la revue : un
 * `domain/` qui importerait `next/*`, React ou un client HTTP ferait échouer la
 * CI. Sans cette garde, la couche domaine se dégraderait au premier correctif
 * pressé — c'est exactement ce que l'audit reprochait aux fronts existants.
 */

const MODULE_ROOT = join(__dirname, '..');

/** Motifs interdits dans un fichier de couche `domain/`. */
const FORBIDDEN_IN_DOMAIN: ReadonlyArray<{ pattern: RegExp; reason: string }> = [
  { pattern: /^next(\/|$)/, reason: 'framework Next.js' },
  { pattern: /^react(\/|$)/, reason: 'React' },
  { pattern: /^@\/lib\//, reason: 'client API / utilitaire global' },
  { pattern: /^@\/components\//, reason: 'composant global' },
  { pattern: /^@\/modules\//, reason: 'autre module applicatif' },
  { pattern: /(^|\/)infrastructure(\/|$)/, reason: 'couche infrastructure' },
  { pattern: /(^|\/)application(\/|$)/, reason: 'couche application' },
  { pattern: /(^|\/)components(\/|$)/, reason: 'couche composants' },
];

function walk(dir: string): string[] {
  const out: string[] = [];

  for (const entry of readdirSync(dir)) {
    const full = join(dir, entry);

    if (statSync(full).isDirectory()) {
      out.push(...walk(full));
    } else if (/\.(ts|tsx)$/.test(entry)) {
      out.push(full);
    }
  }

  return out;
}

function importsOf(source: string): string[] {
  const specifiers: string[] = [];
  const staticImport = /from\s+['"]([^'"]+)['"]/g;
  const dynamicImport = /import\(\s*['"]([^'"]+)['"]\s*\)/g;

  for (const match of source.matchAll(staticImport)) {
    specifiers.push(match[1]);
  }

  for (const match of source.matchAll(dynamicImport)) {
    specifiers.push(match[1]);
  }

  return specifiers;
}

function domainFiles(): string[] {
  return walk(MODULE_ROOT).filter((file) => {
    const rel = relative(MODULE_ROOT, file).split('\\').join('/');

    return rel.includes('/domain/') || rel.startsWith('domain/');
  });
}

describe('garde de couche — domain/ est pur', () => {
  it('trouve au moins un fichier de domaine', () => {
    expect(domainFiles().length).toBeGreaterThan(0);
  });

  it('aucun fichier de domaine n’importe une couche supérieure ou un framework', () => {
    const violations: string[] = [];

    for (const file of domainFiles()) {
      const rel = relative(MODULE_ROOT, file).split('\\').join('/');
      const source = readFileSync(file, 'utf8');

      for (const specifier of importsOf(source)) {
        for (const rule of FORBIDDEN_IN_DOMAIN) {
          if (rule.pattern.test(specifier)) {
            violations.push(`${rel} → ${specifier} (${rule.reason})`);
          }
        }
      }
    }

    expect(violations).toEqual([]);
  });
});
