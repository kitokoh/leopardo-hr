/**
 * Gardes du visuel « Pile Leopardo » (hero de la vitrine).
 *
 * Ce que ces tests protègent : le visuel affirme une architecture
 * (socle → horizontale → verticales) et, pour chaque verticale, la liste des
 * briques horizontales qu'elle consomme. Si le catalogue change côté front
 * (`CLIENT_MODULES`) ou côté manifests serveur, le visuel ne doit PAS se
 * mettre à mentir en silence — il doit échouer ici.
 *
 * Référence de la provenance : en-tête de `data/solution-stack.ts`.
 */
import {
  EXTRA_MODULE_LABELS,
  HORIZONTAL_BLOCKS,
  VERTICALS,
  getSolutionStackCopy,
} from '../solution-stack';

const LOCALES = ['fr', 'en', 'tr', 'ar'] as const;

describe('solution-stack — cohérence de la composition', () => {
  it('la couche horizontale occupe une grille 4×4 sans doublon ni trou', () => {
    expect(HORIZONTAL_BLOCKS).toHaveLength(16);

    const keys = HORIZONTAL_BLOCKS.map((block) => block.key);
    expect(new Set(keys).size).toBe(16);

    const cells = HORIZONTAL_BLOCKS.map((block) => `${block.col}:${block.row}`);
    expect(new Set(cells).size).toBe(16);

    for (const block of HORIZONTAL_BLOCKS) {
      expect(block.col).toBeGreaterThanOrEqual(0);
      expect(block.col).toBeLessThanOrEqual(3);
      expect(block.row).toBeGreaterThanOrEqual(0);
      expect(block.row).toBeLessThanOrEqual(3);
    }
  });

  it('expose les 4 verticales du type BusinessVertical', () => {
    expect(VERTICALS.map((v) => v.key).sort()).toEqual(
      ['education', 'fuel', 'restaurant', 'travel'].sort(),
    );
  });

  it('chaque verticale ne consomme que des briques horizontales existantes', () => {
    const known = new Set(HORIZONTAL_BLOCKS.map((block) => block.key));

    for (const vertical of VERTICALS) {
      expect(vertical.consumes.length).toBeGreaterThan(0);
      for (const consumed of vertical.consumes) {
        expect(known.has(consumed)).toBe(true);
      }
      // Pas de doublon : un faisceau en double se verrait dans la scène.
      expect(new Set(vertical.consumes).size).toBe(vertical.consumes.length);
    }
  });

  it('aucune verticale ne revendique la totalité des briques horizontales', () => {
    // Une verticale qui « consomme tout » rendrait la démonstration absurde.
    for (const vertical of VERTICALS) {
      expect(vertical.consumes.length).toBeLessThan(HORIZONTAL_BLOCKS.length);
    }
  });

  it('la consommation reflète les manifests serveur (rh → employés/contrats/absences)', () => {
    // Les 4 manifests déclarent tous `rh`, `crm`, `accounting`, `marketing`.
    for (const vertical of VERTICALS) {
      expect(vertical.consumes).toEqual(
        expect.arrayContaining(['employees', 'contracts', 'absences', 'crm', 'accounting', 'marketing']),
      );
    }

    // `attendance` + `payroll` ne sont déclarés que par FuelStation et EduManager.
    expect(VERTICALS.find((v) => v.key === 'fuel')?.consumes).toEqual(
      expect.arrayContaining(['attendance', 'payroll']),
    );
    expect(VERTICALS.find((v) => v.key === 'education')?.consumes).toEqual(
      expect.arrayContaining(['attendance', 'payroll']),
    );
    expect(VERTICALS.find((v) => v.key === 'restaurant')?.consumes).not.toContain('payroll');
    expect(VERTICALS.find((v) => v.key === 'travel')?.consumes).not.toContain('payroll');

    // `fleet` n'a pas de brique côté client : il doit rester en texte, jamais en tuile.
    expect(VERTICALS.find((v) => v.key === 'fuel')?.extraModules).toContain('fleet');
  });
});

describe('solution-stack — copie ×4 locales', () => {
  it('traduit la totalité des libellés dans les 4 locales', () => {
    for (const locale of LOCALES) {
      const copy = getSolutionStackCopy(locale);

      expect(copy.title.length).toBeGreaterThan(0);
      expect(copy.subtitle.length).toBeGreaterThan(0);
      expect(copy.hint.length).toBeGreaterThan(0);
      expect(copy.canvasAlt.length).toBeGreaterThan(0);

      for (const block of HORIZONTAL_BLOCKS) {
        expect(copy.horizontals[block.key]?.length ?? 0).toBeGreaterThan(0);
      }
      for (const vertical of VERTICALS) {
        expect(copy.verticals[vertical.key]?.length ?? 0).toBeGreaterThan(0);
      }
    }
  });

  it('aucun repli silencieux tr/ar → en', () => {
    const en = getSolutionStackCopy('en');
    for (const locale of ['tr', 'ar'] as const) {
      const copy = getSolutionStackCopy(locale);
      expect(copy.title).not.toBe(en.title);
      expect(copy.hint).not.toBe(en.hint);
      for (const block of HORIZONTAL_BLOCKS) {
        expect(copy.horizontals[block.key]).not.toBe(en.horizontals[block.key]);
      }
    }
  });

  it('traduit les modules sans brique dans les 4 locales', () => {
    for (const locale of LOCALES) {
      const labels = EXTRA_MODULE_LABELS[locale];
      for (const moduleKey of ['documents', 'notifications', 'fleet']) {
        expect(labels[moduleKey]?.length ?? 0).toBeGreaterThan(0);
      }
    }
  });

  it('ne contient aucun mojibake (garde i18n du dépôt)', () => {
    for (const locale of LOCALES) {
      const serialized = JSON.stringify(getSolutionStackCopy(locale));
      expect(serialized).not.toMatch(/Ã|Â|Ù|Ø/);
    }
  });
});
