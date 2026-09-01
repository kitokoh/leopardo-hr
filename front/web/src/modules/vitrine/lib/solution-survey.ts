/**
 * Client du questionnaire de pré-qualification des solutions sectorielles.
 *
 * Alimente le « wizard restaurateur » : les QUESTIONS et le catalogue de
 * PACKAGES viennent du backend (`GET/POST /solutions/...`) — source de
 * vérité unique. Le front ne fait qu'afficher, pré-cocher et gérer le
 * téléchargement.
 *
 * Labels : les `label_key` / `reason_key` du backend sont résolus via
 * `SOLUTION_LABELS` (fr/en complets, tr/ar → en). La migration vers le
 * catalogue i18n central (`/i18n/catalog`) est le chemin production.
 */

import { apiFetch } from '@/lib/api-client';

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
// Labels localisés (résolution des clés backend).
// fr/en complets — tr/ar retombent sur en (TODO: traduire via i18n catalog).
// ──────────────────────────────────────────────────────────────────────────

export type VitrineLocale = 'fr' | 'en' | 'tr' | 'ar';

type Localized = { fr: string; en: string };

export const SOLUTION_LABELS: Record<string, Localized> = {
  // Questions
  'solutions.restaurant.question.service_type': { fr: 'Comment proposez-vous vos plats ?', en: 'How do you serve your dishes?' },
  'solutions.restaurant.question.service_type.sur_place': { fr: 'Sur place', en: 'Dine-in' },
  'solutions.restaurant.question.service_type.a_emporter': { fr: 'À emporter', en: 'Takeaway' },
  'solutions.restaurant.question.service_type.mixte': { fr: 'Mixte (sur place + à emporter)', en: 'Mixed (dine-in + takeaway)' },
  'solutions.restaurant.question.employee_count': { fr: 'Combien de salariés ?', en: 'How many employees?' },
  'solutions.restaurant.question.employee_count.1_5': { fr: '1 à 5', en: '1–5' },
  'solutions.restaurant.question.employee_count.6_20': { fr: '6 à 20', en: '6–20' },
  'solutions.restaurant.question.employee_count.21_50': { fr: '21 à 50', en: '21–50' },
  'solutions.restaurant.question.employee_count.50_plus': { fr: 'Plus de 50', en: '50+' },
  'solutions.restaurant.question.attendance_device': { fr: 'Comment vos équipes pointent-elles ?', en: 'How do your teams clock in?' },
  'solutions.restaurant.question.attendance_device.none': { fr: 'Pas de pointage aujourd\'hui', en: 'No attendance yet' },
  'solutions.restaurant.question.attendance_device.mobile': { fr: 'Depuis le mobile (géolocalisé)', en: 'From mobile (geolocated)' },
  'solutions.restaurant.question.attendance_device.kiosk': { fr: 'Borne / kiosque dans le restaurant', en: 'Kiosk on-site' },
  'solutions.restaurant.question.attendance_device.biometric': { fr: 'Badgeuse biométrique', en: 'Biometric terminal' },
  'solutions.restaurant.question.scheduling': { fr: 'Gérez-vous des plannings d\'équipe ?', en: 'Do you manage team schedules?' },
  'solutions.restaurant.question.payroll': { fr: 'Voulez-vous gérer la paie dans l\'app ?', en: 'Do you want payroll in the app?' },
  'solutions.restaurant.question.accounting': { fr: 'Besoin de suivi comptable ?', en: 'Need accounting tracking?' },
  'solutions.restaurant.question.delivery': { fr: 'Faites-vous de la livraison ?', en: 'Do you offer delivery?' },
  'solutions.restaurant.question.delivery.none': { fr: 'Non', en: 'No' },
  'solutions.restaurant.question.delivery.platforms': { fr: 'Oui, via les plateformes (Uber Eats…)', en: 'Yes, via platforms (Uber Eats…)' },
  'solutions.restaurant.question.delivery.own': { fr: 'Oui, avec ma propre flotte', en: 'Yes, with my own fleet' },
  'solutions.restaurant.question.reservations': { fr: 'Prenez-vous des réservations en ligne ?', en: 'Do you take online reservations?' },
  'solutions.restaurant.question.inventory': { fr: 'Voulez-vous suivre votre stock ?', en: 'Do you want inventory tracking?' },
  'solutions.restaurant.question.loyalty': { fr: 'Programme de fidélité ?', en: 'Loyalty program?' },
  'solutions.restaurant.question.pos': { fr: 'Caisse enregistreuse connectée ?', en: 'Connected POS?' },
  // Packages
  'solutions.restaurant.package.mobile_employee': { fr: 'App mobile employé', en: 'Employee mobile app' },
  'solutions.restaurant.package.mobile_manager': { fr: 'App mobile manager', en: 'Manager mobile app' },
  'solutions.restaurant.package.attendance_mobile': { fr: 'Pointage mobile géolocalisé', en: 'Geolocated mobile attendance' },
  'solutions.restaurant.package.kiosk': { fr: 'Kiosque de pointage (borne)', en: 'Attendance kiosk' },
  'solutions.restaurant.package.edge': { fr: 'Nœud Edge local (offline-first)', en: 'Local Edge node (offline-first)' },
  'solutions.restaurant.package.planning': { fr: 'Planning d\'équipe', en: 'Team scheduling' },
  'solutions.restaurant.package.payroll': { fr: 'Paie (multi-pays)', en: 'Payroll (multi-country)' },
  'solutions.restaurant.package.accounting': { fr: 'Comptabilité', en: 'Accounting' },
  'solutions.restaurant.package.delivery': { fr: 'Gestion de la livraison', en: 'Delivery management' },
  'solutions.restaurant.package.reservations': { fr: 'Réservations en ligne', en: 'Online reservations' },
  'solutions.restaurant.package.inventory': { fr: 'Gestion de stock', en: 'Inventory management' },
  'solutions.restaurant.package.loyalty': { fr: 'Fidélité & marketing', en: 'Loyalty & marketing' },
  'solutions.restaurant.package.pos': { fr: 'Caisse (POS) connectée', en: 'Connected POS' },
  // Raisons
  'solutions.restaurant.reason.base': { fr: 'Indispensable : vos salariés pointent, consultent leurs fiches et leurs paies.', en: 'Essential: your employees clock in and view their payslips.' },
  'solutions.restaurant.reason.manager': { fr: 'Votre équipe est assez grande pour piloter depuis le mobile.', en: 'Your team is large enough to manage from mobile.' },
  'solutions.restaurant.reason.attendance_mobile': { fr: 'Vous avez choisi le pointage mobile.', en: 'You chose mobile attendance.' },
  'solutions.restaurant.reason.kiosk': { fr: 'Vous avez choisi un pointage sur borne.', en: 'You chose kiosk attendance.' },
  'solutions.restaurant.reason.edge': { fr: 'Le kiosque fonctionne même sans connexion grâce au nœud local.', en: 'The kiosk works offline thanks to the local node.' },
  'solutions.restaurant.reason.scheduling': { fr: 'Vous gérez des plannings d\'équipe.', en: 'You manage team schedules.' },
  'solutions.restaurant.reason.payroll': { fr: 'Vous voulez internaliser la paie.', en: 'You want in-house payroll.' },
  'solutions.restaurant.reason.accounting': { fr: 'Vous avez demandé un suivi comptable.', en: 'You asked for accounting tracking.' },
  'solutions.restaurant.reason.delivery': { fr: 'Vous faites de la livraison.', en: 'You offer delivery.' },
  'solutions.restaurant.reason.reservations': { fr: 'Vous prenez des réservations.', en: 'You take reservations.' },
  'solutions.restaurant.reason.inventory': { fr: 'Vous voulez suivre votre stock.', en: 'You want inventory tracking.' },
  'solutions.restaurant.reason.loyalty': { fr: 'Vous voulez fidéliser vos clients.', en: 'You want to retain customers.' },
  'solutions.restaurant.reason.pos': { fr: 'Vous voulez une caisse connectée.', en: 'You want a connected POS.' },
};

export function solutionLabel(
  key: string,
  locale: VitrineLocale,
  fallback = key,
): string {
  const entry = SOLUTION_LABELS[key];
  if (!entry) {
    return fallback;
  }
  if (locale === 'fr') {
    return entry.fr;
  }
  return entry.en; // en = langue de repli (tr/ar → en pour l'instant)
}

export const localeFromAppLocale = (locale: string): VitrineLocale =>
  locale === 'fr' ? 'fr' : locale === 'tr' ? 'tr' : locale === 'ar' ? 'ar' : 'en';
