/**
 * Garde de complétude ×4 du catalogue de libellés du survey solutions.
 *
 * Pattern `pricing.test.ts` (#4951) : toute clé du catalogue doit être
 * traduite dans les 4 locales (fr/en/tr/ar), sans repli silencieux
 * tr/ar → en (sauf valeurs numériques type « 1–5 »). Issue #6691.
 */
import { SOLUTION_LABELS } from '../solution-survey-labels';

describe('SOLUTION_LABELS complétude ×4 (#6691)', () => {
  const locales = ['fr', 'en', 'tr', 'ar'] as const;
  const keys = Object.keys(SOLUTION_LABELS);

  it('couvre au moins les 51 clés du survey restaurant + les clés Trade (BC-36)', () => {
    expect(keys.length).toBeGreaterThanOrEqual(51);
  });

  it('chaque clé est traduite dans les 4 locales', () => {
    for (const key of keys) {
      for (const locale of locales) {
        const value = SOLUTION_LABELS[key][locale];
        expect(typeof value).toBe('string');
        expect(value.length).toBeGreaterThan(0);
      }
    }
  });

  it('pas de repli silencieux tr/ar → en (hors valeurs numériques)', () => {
    // Exceptions assumées : toponymes identiques en anglais et en turc.
    // Une traduction qui coïncide n'est PAS un repli — on l'explicite ici
    // plutôt que d'affaiblir la garde pour tout le catalogue.
    const trEqualsEnAllowed = new Set<string>([
      'solutions.trade.question.hq_country.sn', // Senegal / Senegal
    ]);

    for (const key of keys) {
      const en = SOLUTION_LABELS[key].en;
      if (/[\p{L}]/u.test(en)) {
        if (!trEqualsEnAllowed.has(key)) {
          expect(SOLUTION_LABELS[key].tr).not.toBe(en);
        }
        expect(SOLUTION_LABELS[key].ar).not.toBe(en);
      }
    }
  });

  it('les clés correspondent aux label_key/reason_key du backend', () => {
    // Convention (RestaurantSurvey.php / TradeSurvey.php) :
    // solutions.{restaurant|trade}.{question,package,reason}.*
    for (const key of keys) {
      expect(
        key.startsWith('solutions.restaurant.') || key.startsWith('solutions.trade.'),
      ).toBe(true);
    }
  });

  it('le survey Trade (BC-36) est traduit ×4 et couvre les deux périmètres', () => {
    const tradeKeys = keys.filter((k) => k.startsWith('solutions.trade.'));

    // Périmètre (International / Domestique) — exigence d'acceptation n° 1.
    expect(tradeKeys).toContain('solutions.trade.question.scope.international');
    expect(tradeKeys).toContain('solutions.trade.question.scope.domestic');
    // Rôle international — importateur / exportateur / les deux (exigence n° 2).
    expect(tradeKeys).toContain('solutions.trade.question.international_role.both');
    // Mode domestique — grossiste / détail (exigence n° 3).
    expect(tradeKeys).toContain('solutions.trade.question.domestic_role.wholesaler');
    expect(tradeKeys).toContain('solutions.trade.question.domestic_role.retailer');

    for (const key of tradeKeys) {
      for (const locale of locales) {
        expect(typeof SOLUTION_LABELS[key][locale]).toBe('string');
        expect(SOLUTION_LABELS[key][locale].length).toBeGreaterThan(0);
      }
    }
  });
});
