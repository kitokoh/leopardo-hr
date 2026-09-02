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

  it('couvre au moins les 51 clés du survey restaurant', () => {
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
    for (const key of keys) {
      const en = SOLUTION_LABELS[key].en;
      if (/[\p{L}]/u.test(en)) {
        expect(SOLUTION_LABELS[key].tr).not.toBe(en);
        expect(SOLUTION_LABELS[key].ar).not.toBe(en);
      }
    }
  });

  it('les clés correspondent aux label_key/reason_key du backend', () => {
    // Convention (RestaurantSurvey.php) : solutions.restaurant.{question,package,reason}.*
    for (const key of keys) {
      expect(key.startsWith('solutions.restaurant.')).toBe(true);
    }
  });
});
