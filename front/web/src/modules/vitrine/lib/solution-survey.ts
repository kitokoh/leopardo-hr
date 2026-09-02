/**
 * Client du questionnaire de pré-qualification des solutions sectorielles.
 *
 * Alimente le « wizard restaurateur » : les QUESTIONS et le catalogue de
 * PACKAGES viennent du backend (`GET/POST /solutions/...`) — source de
 * vérité unique. Le front ne fait qu'afficher, pré-cocher et gérer le
 * téléchargement.
 *
 * Labels : les `label_key` / `reason_key` du backend sont résolus via
 * `SOLUTION_LABELS` (catalogue ×4 fr/en/tr/ar dans
 * `data/solution-survey-labels.ts`). La migration vers le catalogue i18n
 * central (`/i18n/catalog`) est le chemin production.
 */

import { apiFetch } from '@/lib/api-client';
import { SOLUTION_LABELS } from '../data/solution-survey-labels';

export type SurveyAnswerValue = string | boolean;

export type SolutionSurveyQuestion = {
  key: string;
  type: 'select' | 'bool' | 'multi';
  label_key: string;
  options?: Array<{ value: string; label_key: string }>;
  default?: string | boolean;
};

export type SolutionPackage = {
  key: string;
  type: 'mobile' | 'module' | 'device' | 'edge';
  label_key: string;
  app?: string;
  download?: 'apk' | 'edge_install' | 'guide' | null;
  required?: boolean;
};

export type SuggestedPackage = SolutionPackage & {
  reason_key: string;
  priority: number;
};

export type SurveyResult = {
  code: string;
  packages: SuggestedPackage[];
  total: number;
};

export type SolutionSummary = {
  code: string;
  name: string;
  description: string;
  maturity: string;
};

export type SurveyData = {
  code: string;
  questions: SolutionSurveyQuestion[];
  packages: SolutionPackage[];
};

export async function fetchSolutions(): Promise<SolutionSummary[]> {
  const res = await apiFetch('/solutions');
  const payload = (await res.json()) as { data?: SolutionSummary[] };
  return Array.isArray(payload.data) ? payload.data : [];
}

export async function fetchSurvey(code: string): Promise<SurveyData | null> {
  const res = await apiFetch(`/solutions/${encodeURIComponent(code)}/survey`);
  if (!res.ok) {
    return null;
  }
  const payload = (await res.json()) as { data?: SurveyData };
  return payload.data ?? null;
}

export async function suggestPack(
  code: string,
  answers: Record<string, SurveyAnswerValue>,
): Promise<SurveyResult | null> {
  const res = await apiFetch(`/solutions/${encodeURIComponent(code)}/survey`, {
    method: 'POST',
    body: JSON.stringify({ answers }),
  });
  if (!res.ok) {
    return null;
  }
  const payload = (await res.json()) as { data?: SurveyResult };
  return payload.data ?? null;
}

/** Construit les réponses par défaut depuis les `default` des questions. */
export function buildDefaultAnswers(
  questions: SolutionSurveyQuestion[],
): Record<string, SurveyAnswerValue> {
  return Object.fromEntries(
    questions
      .filter((q) => q.default !== undefined)
      .map((q) => [q.key, q.default as SurveyAnswerValue]),
  );
}

// ──────────────────────────────────────────────────────────────────────────
// Labels localisés — le catalogue ×4 (fr/en/tr/ar) vit dans
// `data/solution-survey-labels.ts` (chemin exempté par PA2-I18N-014 :
// ce fichier EST le mécanisme i18n du wizard, pas des chaînes hors
// catalogue). Ici : types + résolution.
// ──────────────────────────────────────────────────────────────────────────

export type VitrineLocale = 'fr' | 'en' | 'tr' | 'ar';


export { SOLUTION_LABELS };
export type { SurveyLocalized } from '../data/solution-survey-labels';

export function solutionLabel(
  key: string,
  locale: VitrineLocale,
  fallback = key,
): string {
  const entry = SOLUTION_LABELS[key];
  if (!entry) {
    return fallback;
  }
  return entry[locale] ?? entry.en; // en = langue de repli ultime
}

export const localeFromAppLocale = (locale: string): VitrineLocale =>
  locale === 'fr' ? 'fr' : locale === 'tr' ? 'tr' : locale === 'ar' ? 'ar' : 'en';

