/**
 * Tests du client de pré-qualification des solutions (partie pure).
 *
 * Vérifie la construction des réponses par défaut et la résolution des
 * libellés — pas d'appel réseau (les helpers API sont testés côté backend).
 */
import {
  buildDefaultAnswers,
  solutionLabel,
  SOLUTION_LABELS,
  type SolutionSurveyQuestion,
} from '../solution-survey';

const SAMPLE_QUESTIONS: SolutionSurveyQuestion[] = [
  {
    key: 'service_type',
    type: 'select',
    label_key: 'solutions.restaurant.question.service_type',
    options: [{ value: 'mixte', label_key: 'solutions.restaurant.question.service_type.mixte' }],
    default: 'mixte',
  },
  {
    key: 'scheduling',
    type: 'bool',
    label_key: 'solutions.restaurant.question.scheduling',
    default: false,
  },
  {
    key: 'payroll',
    type: 'bool',
    label_key: 'solutions.restaurant.question.payroll',
  },
];

describe('solution-survey lib', () => {
  it('buildDefaultAnswers applique les défauts déclarés et ignore les questions sans défaut', () => {
    const defaults = buildDefaultAnswers(SAMPLE_QUESTIONS);

    expect(defaults).toEqual({
      service_type: 'mixte',
      scheduling: false,
    });
    expect(defaults.payroll).toBeUndefined();
  });

  it('solutionLabel résout les clés backend dans les 4 locales (fr/en/tr/ar)', () => {
    expect(solutionLabel('solutions.restaurant.question.service_type', 'fr')).toBe(
      'Comment proposez-vous vos plats ?',
    );
    expect(solutionLabel('solutions.restaurant.question.service_type', 'en')).toBe(
      'How do you serve your dishes?',
    );
    expect(solutionLabel('solutions.restaurant.question.service_type', 'tr')).toBe(
      'Yemeklerinizi nasıl sunuyorsunuz?',
    );
    expect(solutionLabel('solutions.restaurant.question.service_type', 'ar')).toBe(
      'كيف تقدمون أطباقكم؟',
    );
  });

  it('solutionLabel retombe sur la clé brute si inconnue', () => {
    expect(solutionLabel('cle.inexistante', 'fr')).toBe('cle.inexistante');
  });

  it('chaque clé du catalogue SOLUTION_LABELS est traduite dans les 4 locales (#6691)', () => {
    const locales = ['fr', 'en', 'tr', 'ar'] as const;
    const keys = Object.keys(SOLUTION_LABELS);

    expect(keys.length).toBeGreaterThanOrEqual(50);

    for (const key of keys) {
      for (const locale of locales) {
        const value = SOLUTION_LABELS[key][locale];
        expect(typeof value).toBe('string');
        expect(value.length).toBeGreaterThan(0);
      }
      // pas de repli silencieux tr/ar → en (sauf valeurs numériques type « 1–5 »)
      const en = SOLUTION_LABELS[key].en;
      if (/[\p{L}]/u.test(en)) {
        expect(SOLUTION_LABELS[key].tr).not.toBe(en);
        expect(SOLUTION_LABELS[key].ar).not.toBe(en);
      }
    }
  });
});
