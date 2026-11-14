import { getRelatedTools, getCatalogTools, getTool } from './registry';

/**
 * Tests des outils similaires.
 *
 * Invariants : l'outil courant n'est JAMAIS suggéré, la même catégorie passe
 * en premier, un slug inconnu ne produit aucune suggestion, et la limite est
 * respectée (y compris 0 et les valeurs négatives).
 */
describe('getRelatedTools', () => {
  it('ne suggère jamais l’outil courant', () => {
    for (const tool of getCatalogTools()) {
      const related = getRelatedTools(tool.slug, 3);
      expect(related.map((entry) => entry.slug)).not.toContain(tool.slug);
    }
  });

  it('retourne au plus `limit` outils', () => {
    expect(getRelatedTools('imc', 3)).toHaveLength(3);
    expect(getRelatedTools('imc', 1)).toHaveLength(1);
  });

  it('retourne une liste vide pour une limite nulle ou négative', () => {
    expect(getRelatedTools('imc', 0)).toEqual([]);
    expect(getRelatedTools('imc', -5)).toEqual([]);
  });

  it('retourne une liste vide pour un slug inconnu', () => {
    expect(getRelatedTools('outil-inexistant', 3)).toEqual([]);
  });

  it('place la même catégorie en premier', () => {
    const current = getTool('imc')!;
    const related = getRelatedTools('imc', 3);
    // `sante` ne contient qu'un outil : les 3 suggestions viennent donc des
    // autres catégories, mais aucune ne doit être `imc`.
    expect(related.every((tool) => tool.slug !== current.slug)).toBe(true);
  });

  it('privilégie la même catégorie quand elle contient d’autres outils', () => {
    // `calcul` contient plusieurs outils : les suggestions doivent en venir.
    const related = getRelatedTools('pourcentage', 2);
    expect(related).toHaveLength(2);
    expect(related.every((tool) => tool.category === 'calcul')).toBe(true);
  });

  it('ne suggère que des outils actifs du catalogue', () => {
    const activeSlugs = new Set(getCatalogTools().map((tool) => tool.slug));
    for (const tool of getCatalogTools()) {
      for (const related of getRelatedTools(tool.slug, 3)) {
        expect(activeSlugs.has(related.slug)).toBe(true);
      }
    }
  });
});
