import fs from 'fs';
import path from 'path';
import { TOOLS, TOOL_CATEGORIES } from '../data/catalog';
import { TOOLBOX_LOCALES, getToolboxCopy, type ToolboxCopy } from '../data/copy';

/**
 * Garde-fou de cohérence catalogue ↔ copie ↔ i18n.
 *
 * Ce test verrouille l'invariant qui a causé un bug réel : une `labelKey` de
 * référence préfixée par erreur (`toolbox.ref.whoBmi` au lieu de `whoBmi`) ne
 * se résolvait dans AUCUNE locale et la section « Référence » affichait la clé
 * brute. Le test échoue si une seule référence, catégorie, alerte ou clé de
 * copie n'est pas résolue dans les 4 langues (fr/en/tr/ar).
 */

const LOCALES = TOOLBOX_LOCALES;

/** Aplatit un objet en chemins de clés (`a.b.c`). */
function flattenKeys(value: unknown, prefix = ''): string[] {
  if (value === null || typeof value !== 'object') return [prefix];
  return Object.entries(value as Record<string, unknown>).flatMap(([key, child]) =>
    flattenKeys(child, prefix ? `${prefix}.${key}` : key),
  );
}

/** Compte les références non résolues dans une copie donnée. */
function countUnresolvedReferences(copy: ToolboxCopy): number {
  let unresolved = 0;
  for (const tool of TOOLS) {
    for (const reference of tool.references) {
      if (!copy.refs[reference.labelKey]) unresolved += 1;
    }
  }
  return unresolved;
}

describe('cohérence catalogue ↔ copie', () => {
  it('déclare les 4 locales obligatoires (fr, en, tr, ar)', () => {
    expect([...LOCALES]).toEqual(['fr', 'en', 'tr', 'ar']);
  });

  it('résout chaque référence du catalogue dans les 4 langues', () => {
    for (const locale of LOCALES) {
      const copy = getToolboxCopy(locale);
      for (const tool of TOOLS) {
        for (const reference of tool.references) {
          expect(copy.refs[reference.labelKey]).toBeTruthy();
        }
      }
    }
  });

  it('ne laisse AUCUNE référence non résolue (garde-fou du bug labelKey)', () => {
    for (const locale of LOCALES) {
      expect(countUnresolvedReferences(getToolboxCopy(locale))).toBe(0);
    }
  });

  it('résout chaque catégorie du catalogue dans les 4 langues', () => {
    for (const locale of LOCALES) {
      const copy = getToolboxCopy(locale);
      for (const category of TOOL_CATEGORIES) {
        expect(copy.categories[category.id]).toBeTruthy();
      }
    }
  });

  it('résout chaque alerte du catalogue dans les 4 langues', () => {
    for (const locale of LOCALES) {
      const copy = getToolboxCopy(locale);
      for (const tool of TOOLS) {
        for (const warning of tool.warnings) {
          expect(copy.warnings[warning]).toBeTruthy();
        }
      }
    }
  });

  it('fournit une copie complète (nom, description, résultats) pour chaque outil', () => {
    for (const locale of LOCALES) {
      const copy = getToolboxCopy(locale);
      for (const tool of TOOLS) {
        const toolCopy = copy.tools[tool.slug];
        expect(toolCopy).toBeDefined();
        expect(toolCopy.name.trim().length).toBeGreaterThan(0);
        expect(toolCopy.description.trim().length).toBeGreaterThan(0);
        expect(Object.keys(toolCopy.results).length).toBeGreaterThan(0);
        for (const label of Object.values(toolCopy.results)) {
          expect(label.trim().length).toBeGreaterThan(0);
        }
      }
    }
  });

  it('n’expose aucun outil de copie sans entrée de catalogue', () => {
    const slugs = new Set(TOOLS.map((tool) => tool.slug));
    for (const locale of LOCALES) {
      for (const slug of Object.keys(getToolboxCopy(locale).tools)) {
        expect(slugs.has(slug)).toBe(true);
      }
    }
  });

  it('a une structure de clés identique dans les 4 langues', () => {
    const reference = flattenKeys(getToolboxCopy('fr')).sort();
    for (const locale of LOCALES) {
      expect(flattenKeys(getToolboxCopy(locale)).sort()).toEqual(reference);
    }
  });
});

describe('garde-fou — détection d’un catalogue buggé', () => {
  it('signale les références non résolues quand une labelKey est préfixée à tort', () => {
    const buggy: ToolboxCopy = {
      ...getToolboxCopy('fr'),
      refs: { ...getToolboxCopy('fr').refs },
    };
    // Simule le bug historique : on préfixe les clés de référence.
    buggy.refs = Object.fromEntries(
      Object.entries(buggy.refs).map(([key, value]) => [`toolbox.ref.${key}`, value]),
    );
    // 4 références déclarées × 4 locales = 16 non résolues.
    const total = LOCALES.reduce(
      (sum, locale) => sum + countUnresolvedReferences({ ...getToolboxCopy(locale), refs: buggy.refs }),
      0,
    );
    expect(total).toBe(16);
  });
});

describe('garde-fou — aucune clé i18n morte', () => {
  const moduleRoot = path.resolve(__dirname, '..');
  const appRoot = path.resolve(__dirname, '../../../app/toolbox');

  function collectSourceFiles(dir: string): string[] {
    if (!fs.existsSync(dir)) return [];
    return fs.readdirSync(dir, { withFileTypes: true }).flatMap((entry) => {
      const full = path.join(dir, entry.name);
      if (entry.isDirectory()) return collectSourceFiles(full);
      if (/\.(ts|tsx)$/.test(entry.name) && !/\.test\.(ts|tsx)$/.test(entry.name)) return [full];
      return [];
    });
  }

  it('utilise chaque clé de `common` au moins une fois dans le module', () => {
    const sources = [...collectSourceFiles(moduleRoot), ...collectSourceFiles(appRoot)]
      .map((file) => fs.readFileSync(file, 'utf8'))
      .join('\n');

    const commonKeys = Object.keys(getToolboxCopy('fr').common);
    const dead = commonKeys.filter((key) => !new RegExp(`common\\.${key}\\b`).test(sources));
    expect(dead).toEqual([]);
  });
});
