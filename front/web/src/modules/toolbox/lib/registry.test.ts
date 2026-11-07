import { TOOLS } from '../data/catalog';
import { getAllToolSlugs, getTool, getToolsByCategory, searchTools } from './registry';

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
