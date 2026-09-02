/**
 * Tests du client de pré-qualification des solutions (partie pure).
 *
 * Vérifie la construction des réponses par défaut et la résolution des
 * libellés — pas d'appel réseau (les helpers API sont testés côté backend).
 */
import {
  buildDefaultAnswers,
  solutionLabel,
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

  it('solutionLabel résout les clés backend en fr et en', () => {
    expect(solutionLabel('solutions.restaurant.question.service_type', 'fr')).toBe(
      'Comment proposez-vous vos plats ?',
    );
    expect(solutionLabel('solutions.restaurant.question.service_type', 'en')).toBe(
      'How do you serve your dishes?',
    );
  });

  it('solutionLabel retombe sur la clé brute si inconnue', () => {
    expect(solutionLabel('cle.inexistante', 'fr')).toBe('cle.inexistante');
  });

  it('solutionLabel résout tr/ar dans le catalogue ×4 (#6691)', () => {
    expect(solutionLabel('solutions.restaurant.package.kiosk', 'ar')).toBe('كشك تسجيل الحضور');
    expect(solutionLabel('solutions.restaurant.package.edge', 'tr')).toBe('Yerel Edge düğümü (çevrimdışı)');
  });
});
