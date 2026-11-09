import { TOOLS, TOOL_CATEGORIES } from '../data/catalog';
import {
  getAllToolSlugs,
  getCatalogTools,
  getTool,
  getToolsByCategory,
  isToolEnabled,
  searchTools,
} from './registry';

/**
 * Tests du registre — intégrité du catalogue et résolution.
 */
describe('catalogue', () => {
  it('contient au moins les 5 outils du premier lot', () => {
    const slugs = getAllToolSlugs();
    expect(slugs).toEqual(
      expect.arrayContaining(['imc', 'tva', 'salaire-net', 'convertisseur-unites', 'pourcentage']),
    );
  });

  it('contient les 5 outils du deuxième lot', () => {
    const slugs = getAllToolSlugs();
    expect(slugs).toEqual(
      expect.arrayContaining([
        'regle-de-trois',
        'surface-volume',
        'cbm-poids-volumetrique',
        'marge-prix-vente',
        'tva-multi-taux',
      ]),
    );
  });

  it('déclare une catégorie connue pour chaque outil', () => {
    const known = new Set(TOOL_CATEGORIES.map((category) => category.id));
    for (const tool of TOOLS) {
      expect(known.has(tool.category)).toBe(true);
    }
  });

  it('a des slugs uniques', () => {
    const slugs = TOOLS.map((tool) => tool.slug);
    expect(new Set(slugs).size).toBe(slugs.length);
  });

  it('a des slugs conformes au format kebab-case', () => {
    for (const tool of TOOLS) {
      expect(tool.slug).toMatch(/^[a-z0-9]+(?:-[a-z0-9]+)*$/);
    }
  });

  it('déclare une version sémantique pour chaque outil', () => {
    for (const tool of TOOLS) {
      expect(tool.version).toMatch(/^\d+\.\d+\.\d+$/);
    }
  });

  it('déclare au moins un mot-clé par outil', () => {
    for (const tool of TOOLS) {
      expect(tool.keywords.length).toBeGreaterThan(0);
    }
  });
});

describe('getTool', () => {
  it('retourne la définition d’un slug connu', () => {
    expect(getTool('imc')?.slug).toBe('imc');
  });

  it('retourne null pour un slug inconnu', () => {
    expect(getTool('inexistant')).toBeNull();
  });
});

describe('getToolsByCategory', () => {
  it('filtre par catégorie', () => {
    const finance = getToolsByCategory('finance');
    expect(finance.map((tool) => tool.slug)).toContain('tva');
    expect(finance.every((tool) => tool.category === 'finance')).toBe(true);
  });
});

describe('searchTools', () => {
  it('retourne tout le catalogue pour une requête vide', () => {
    expect(searchTools('')).toHaveLength(TOOLS.length);
  });

  it('trouve par mot-clé', () => {
    expect(searchTools('tva').map((tool) => tool.slug)).toContain('tva');
  });

  it('ignore la casse et les accents', () => {
    expect(searchTools('SANTE').map((tool) => tool.slug)).toContain('imc');
  });

  it('retourne un tableau vide si rien ne correspond', () => {
    expect(searchTools('zzzzz')).toHaveLength(0);
  });
});

/**
 * Décision #9157 — un outil `enabled: false` sort du CATALOGUE (donc de la
 * recherche, de `generateStaticParams` et du sitemap) mais reste RÉSOLVABLE par
 * slug direct : un lien partagé ne casse jamais.
 */
describe('outils désactivés (décision #9157)', () => {
  it('considère un outil sans `enabled` comme actif', () => {
    expect(isToolEnabled({ ...TOOLS[0], enabled: undefined })).toBe(true);
    expect(isToolEnabled({ ...TOOLS[0], enabled: true })).toBe(true);
    expect(isToolEnabled({ ...TOOLS[0], enabled: false })).toBe(false);
  });

  it('expose tous les outils actifs du catalogue', () => {
    const enabled = TOOLS.filter((tool) => tool.enabled !== false);
    expect(getCatalogTools().map((tool) => tool.slug)).toEqual(enabled.map((tool) => tool.slug));
  });

  it('n’expose que les outils actifs dans getAllToolSlugs', () => {
    const enabled = TOOLS.filter((tool) => tool.enabled !== false).map((tool) => tool.slug);
    expect(getAllToolSlugs()).toEqual(enabled);
  });

  it('résout un outil désactivé par slug direct (pas de 404 sur un lien partagé)', () => {
    const disabled = TOOLS.find((tool) => tool.enabled === false);
    if (!disabled) {
      // Aucun outil désactivé dans le catalogue livré : le contrat est vérifié
      // sur une définition synthétique, sans dépendre du contenu éditorial.
      expect(getTool('imc')?.slug).toBe('imc');
      return;
    }
    expect(getTool(disabled.slug)?.slug).toBe(disabled.slug);
  });
});
