'use client';

import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { AnimatePresence, motion } from 'framer-motion';
import { ArrowLeft, Check, Loader2, Sparkles } from 'lucide-react';
import { apiFetch } from '@/lib/api-client';
import type { AppLocale, StoredAuthUser } from '@/lib/i18n';
import { t as i18nT } from '@/lib/i18n/locale-catalog';

/**
 * #7493 — entretien de préparation conversationnel de première connexion.
 *
 * Une question à la fois (carte centrée, cartes-réponses, jamais de champ
 * libre), zappable (« Passer » par question, « Terminer plus tard » global)
 * et REPRENABLE : le brouillon vit côté serveur
 * (`companies.metadata.setup_interview`, PATCH incrémental) — jamais en
 * localStorage, pour reprendre sur un autre appareil (même décision que
 * l'écran de bienvenue #7604).
 *
 * À la clôture (`POST /setup-interview/complete`), le backend active les
 * modules selon les réponses (allowlist fail-closed, idempotent) et le
 * récapitulatif HUMAIN est affiché — aucun jargon « module/feature flag ».
 * L'appelant (layout) recharge ensuite `/auth/me` pour que la navigation
 * reflète les activations SANS rechargement (mécanisme #7245/#7322).
 *
 * L'ordre est ADAPTATIF : un(e) indépendant(e) (« je travaille seul(e) »)
 * ne voit ni la taille d'équipe ni la question des horaires planifiés.
 */

export type SetupInterviewCloseReason = 'completed' | 'dismissed' | 'closed';

type InterviewAnswers = Record<string, string | string[] | null>;

type InterviewState = {
  status?: 'not_started' | 'in_progress' | 'completed' | 'dismissed';
  answers?: InterviewAnswers;
  activated?: { solutions?: string[]; tools?: string[]; failed?: string[] };
};

type QuestionDef = {
  key: string;
  options: string[];
  multi?: boolean;
  /** Ordre adaptatif : une réponse peut court-circuiter une question. */
  visible?: (answers: InterviewAnswers) => boolean;
};

const isSolo = (answers: InterviewAnswers) => answers.company_type === 'solo';

// Miroir de l'allowlist serveur (`SetupInterviewPlanner::QUESTIONS`) —
// 6 questions maximum, aucune à plus de 8 cartes-réponses.
const QUESTIONS: QuestionDef[] = [
  { key: 'company_type', options: ['solo', 'team'] },
  {
    key: 'team_size',
    options: ['1-10', '11-50', '51-200', '201-500', '500+'],
    visible: (a) => !isSolo(a),
  },
  { key: 'sector', options: ['restaurant', 'fuel_station', 'education', 'commerce', 'services', 'travel', 'other'] },
  { key: 'premises', options: ['single', 'multiple', 'mobile', 'none'] },
  {
    key: 'priorities',
    options: ['attendance', 'payroll', 'accounting', 'crm', 'cameras', 'showcase'],
    multi: true,
  },
  {
    key: 'scheduled_hours',
    options: ['yes', 'no'],
    visible: (a) => !isSolo(a),
  },
];

/**
 * Faut-il proposer l'entretien de préparation ? Miroir de la garde serveur
 * (`SetupInterviewController` : `principal`/`rh`) — même logique fail-safe
 * que `shouldShowFirstLoginWelcome` : on ne montre pas un parcours dont les
 * écritures répondraient 403. Un entretien `completed`/`dismissed` ou un
 * onboarding déjà terminé ne redéclenche rien.
 */
export function shouldShowSetupInterview(user?: StoredAuthUser | null): boolean {
  if (!user || user.role !== 'manager') {
    return false;
  }

  const managerRole = (user.manager_role ?? '').toLowerCase();
  if (managerRole !== 'principal' && managerRole !== 'rh') {
    return false;
  }

  const metadata = user.company?.metadata as
    | { onboarding_completed?: boolean; setup_interview?: { status?: string } }
    | undefined;
  if (!user.company || metadata?.onboarding_completed === true) {
    return false;
  }

  const status = metadata?.setup_interview?.status;
  return status !== 'completed' && status !== 'dismissed';
}

export function SetupInterview({
  locale,
  onClose,
}: {
  locale: AppLocale;
  onClose: (reason: SetupInterviewCloseReason) => void;
}) {
  const t = useCallback(
    (key: string, fallback = '') => i18nT(locale, `setupInterview.${key}`, fallback),
    [locale],
  );

  const [answers, setAnswers] = useState<InterviewAnswers>({});
  const [index, setIndex] = useState(0);
  const [loading, setLoading] = useState(true);
  const [completing, setCompleting] = useState(false);
  const [recap, setRecap] = useState<{ solutions: string[]; tools: string[] } | null>(null);
  const [error, setError] = useState<string | null>(null);
  const closedRef = useRef(false);

  const visibleQuestions = useMemo(
    () => QUESTIONS.filter((q) => (q.visible ? q.visible(answers) : true)),
    [answers],
  );
  const question = recap ? null : visibleQuestions[index] ?? null;

  // Reprise : le brouillon serveur est la source de vérité (autre appareil).
  useEffect(() => {
    let cancelled = false;
    (async () => {
      try {
        const response = await apiFetch('/setup-interview');
        const payload = (await response.json()) as { data?: InterviewState };
        if (cancelled) return;
        const state = payload.data;
        if (state?.status === 'completed' || state?.status === 'dismissed') {
          closedRef.current = true;
          onClose('closed');
          return;
        }
        const saved = state?.answers ?? {};
        setAnswers(saved);
        // Reprendre à la première question visible non répondue.
        const visible = QUESTIONS.filter((q) => (q.visible ? q.visible(saved) : true));
        const firstUnanswered = visible.findIndex((q) => !(q.key in saved));
        setIndex(firstUnanswered === -1 ? Math.max(visible.length - 1, 0) : firstUnanswered);
      } catch {
        // Non bloquant : l'entretien démarre à la première question.
      } finally {
        if (!cancelled) setLoading(false);
      }
    })();
    return () => {
      cancelled = true;
    };
  }, [onClose]);

  const persistDraft = useCallback(async (partial: InterviewAnswers) => {
    try {
      await apiFetch('/setup-interview/answers', {
        method: 'PATCH',
        body: JSON.stringify({ answers: partial }),
      });
    } catch {
      // Brouillon best-effort : l'échec réseau n'interrompt jamais le parcours.
    }
  }, []);

  const complete = useCallback(async () => {
    setCompleting(true);
    setError(null);
    try {
      const response = await apiFetch('/setup-interview/complete', { method: 'POST' });
      if (!response.ok) {
        throw new Error(`complete failed (${response.status})`);
      }
      const payload = (await response.json()) as {
        data?: { activated?: { solutions?: string[]; tools?: string[] } };
      };
      setRecap({
        solutions: payload.data?.activated?.solutions ?? [],
        tools: payload.data?.activated?.tools ?? [],
      });
    } catch {
      setError(t('error', 'Impossible d’enregistrer pour le moment. Réessayez dans un instant.'));
    } finally {
      setCompleting(false);
    }
  }, [t]);

  const advance = useCallback(
    (nextAnswers: InterviewAnswers) => {
      const visible = QUESTIONS.filter((q) => (q.visible ? q.visible(nextAnswers) : true));
      const currentKey = question?.key;
      const currentPos = visible.findIndex((q) => q.key === currentKey);
      const next = currentPos === -1 ? index : currentPos + 1;
      if (next >= visible.length) {
        void complete();
      } else {
        setIndex(next);
      }
    },
    [complete, index, question],
  );

  const answer = (value: string | string[] | null) => {
    if (!question) return;
    const nextAnswers = { ...answers, [question.key]: value };
    setAnswers(nextAnswers);
    void persistDraft({ [question.key]: value });
    advance(nextAnswers);
  };

  const dismiss = useCallback(async () => {
    if (closedRef.current) return;
    closedRef.current = true;
    try {
      await apiFetch('/setup-interview/dismiss', { method: 'POST' });
    } catch {
      // Direction d'échec sûre : on ferme quand même, l'entretien reviendra.
    }
    onClose('dismissed');
  }, [onClose]);

  // Échap = « Terminer plus tard » : le parcours n'est jamais bloquant.
  useEffect(() => {
    const onKeyDown = (event: KeyboardEvent) => {
      if (event.key === 'Escape' && !recap) {
        void dismiss();
      }
    };
    window.addEventListener('keydown', onKeyDown);
    return () => window.removeEventListener('keydown', onKeyDown);
  }, [dismiss, recap]);

  const moduleLabel = (key: string) => i18nT(locale, `setupInterview.modules.${key}`, key);

  const progressLabel = t('progress', 'Question {current} sur {total}')
    .replace('{current}', String(Math.min(index + 1, visibleQuestions.length)))
    .replace('{total}', String(visibleQuestions.length));

  const selectedMulti = (key: string): string[] => {
    const value = answers[key];
    return Array.isArray(value) ? value : [];
  };

  const toggleMulti = (option: string) => {
    if (!question) return;
    const current = selectedMulti(question.key);
    const next = current.includes(option)
      ? current.filter((item) => item !== option)
      : [...current, option];
    setAnswers((prev) => ({ ...prev, [question.key]: next }));
  };

  const confirmMulti = () => {
    if (!question) return;
    answer(selectedMulti(question.key));
  };

  return (
    <div
      role="dialog"
      aria-modal="true"
      aria-label={t('title', 'Préparons votre espace')}
      data-testid="setup-interview"
      className="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-slate-50/95 p-4 backdrop-blur-sm"
    >
      <div className="w-full max-w-2xl">
        {loading ? (
          <div className="flex items-center justify-center py-24" data-testid="interview-loading">
            <Loader2 className="h-8 w-8 animate-spin text-emerald-500" aria-hidden="true" />
          </div>
        ) : recap ? (
          <motion.section
            initial={{ opacity: 0, y: 12 }}
            animate={{ opacity: 1, y: 0 }}
            className="rounded-3xl border border-emerald-100 bg-white p-8 shadow-xl"
            data-testid="interview-recap"
          >
            <div className="mb-4 inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-emerald-50">
              <Sparkles className="h-6 w-6 text-emerald-600" aria-hidden="true" />
            </div>
            <h2 className="text-2xl font-black text-slate-900">{t('recapTitle', 'Votre espace est prêt')}</h2>
            {recap.solutions.length + recap.tools.length > 0 ? (
              <>
                <p className="mt-2 text-sm text-slate-600">{t('recapBody', 'Voici ce que nous avons activé pour vous :')}</p>
                <ul className="mt-4 space-y-2">
                  {[...recap.solutions, ...recap.tools].map((key) => (
                    <li key={key} className="flex items-center gap-2 text-sm font-semibold text-slate-800">
                      <Check className="h-4 w-4 shrink-0 text-emerald-500" aria-hidden="true" />
                      {moduleLabel(key)}
                    </li>
                  ))}
                </ul>
              </>
            ) : (
              <p className="mt-2 text-sm text-slate-600">
                {t('recapEmpty', 'Votre espace de base est prêt. Vous pourrez activer d’autres outils à tout moment.')}
              </p>
            )}
            <button
              type="button"
              onClick={() => {
                closedRef.current = true;
                onClose('completed');
              }}
              data-testid="interview-recap-cta"
              className="mt-8 w-full rounded-2xl bg-emerald-600 px-6 py-3 text-sm font-black text-white transition hover:bg-emerald-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/40"
            >
              {t('recapCta', 'Découvrir mon espace')}
            </button>
          </motion.section>
        ) : question ? (
          <section className="rounded-3xl border border-slate-200 bg-white p-8 shadow-xl">
            <header className="mb-6">
              <p className="text-[11px] font-black uppercase tracking-widest text-emerald-600">
                {progressLabel}
              </p>
              <div className="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-slate-100" aria-hidden="true">
                <div
                  className="h-full rounded-full bg-emerald-500 transition-all"
                  style={{ width: `${Math.round((index / Math.max(visibleQuestions.length, 1)) * 100)}%` }}
                />
              </div>
            </header>
            <AnimatePresence mode="wait">
              <motion.div
                key={question.key}
                initial={{ opacity: 0, x: 16 }}
                animate={{ opacity: 1, x: 0 }}
                exit={{ opacity: 0, x: -16 }}
                transition={{ duration: 0.18 }}
              >
                <h2 className="text-xl font-black text-slate-900" data-testid="interview-question">
                  {i18nT(locale, `setupInterview.questions.${question.key}.label`, question.key)}
                </h2>
                {question.multi ? (
                  <p className="mt-1 text-xs font-semibold text-slate-500">
                    {i18nT(locale, `setupInterview.questions.${question.key}.hint`, '')}
                  </p>
                ) : null}
                <div className="mt-5 grid gap-2 sm:grid-cols-2">
                  {question.options.map((option) => {
                    const selected = question.multi
                      ? selectedMulti(question.key).includes(option)
                      : answers[question.key] === option;
                    return (
                      <button
                        key={option}
                        type="button"
                        aria-pressed={selected}
                        data-testid={`interview-option-${option}`}
                        onClick={() => (question.multi ? toggleMulti(option) : answer(option))}
                        className={[
                          'flex items-center justify-between gap-3 rounded-2xl border px-4 py-3 text-left text-sm font-bold transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/40',
                          selected
                            ? 'border-emerald-500 bg-emerald-50 text-emerald-800'
                            : 'border-slate-200 bg-white text-slate-700 hover:border-emerald-300 hover:bg-emerald-50/40',
                        ].join(' ')}
                      >
                        <span>
                          {i18nT(locale, `setupInterview.questions.${question.key}.options.${option}`, option)}
                        </span>
                        {selected ? <Check className="h-4 w-4 shrink-0 text-emerald-600" aria-hidden="true" /> : null}
                      </button>
                    );
                  })}
                </div>
                {question.multi ? (
                  <button
                    type="button"
                    onClick={confirmMulti}
                    data-testid="interview-multi-continue"
                    className="mt-5 w-full rounded-2xl bg-emerald-600 px-6 py-3 text-sm font-black text-white transition hover:bg-emerald-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/40"
                  >
                    {t('finish', 'Terminer')}
                  </button>
                ) : null}
              </motion.div>
            </AnimatePresence>
            {error ? (
              <p role="alert" className="mt-4 text-sm font-semibold text-red-600">
                {error}
              </p>
            ) : null}
            <footer className="mt-6 flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-4">
              <button
                type="button"
                onClick={() => setIndex((current) => Math.max(current - 1, 0))}
                disabled={index === 0 || completing}
                data-testid="interview-back"
                className="inline-flex items-center gap-1.5 rounded-xl px-3 py-2 text-xs font-black text-slate-500 transition hover:text-slate-800 disabled:opacity-40"
              >
                <ArrowLeft className="h-3.5 w-3.5" aria-hidden="true" />
                {t('back', 'Retour')}
              </button>
              <div className="flex items-center gap-2">
                <button
                  type="button"
                  onClick={() => answer(null)}
                  disabled={completing}
                  data-testid="interview-skip"
                  className="rounded-xl border border-slate-200 px-4 py-2 text-xs font-black text-slate-600 transition hover:border-slate-300 hover:bg-slate-50"
                >
                  {t('skip', 'Passer')}
                </button>
                <button
                  type="button"
                  onClick={() => void dismiss()}
                  disabled={completing}
                  data-testid="interview-later"
                  className="rounded-xl px-4 py-2 text-xs font-black text-slate-400 transition hover:text-slate-700"
                >
                  {t('later', 'Terminer plus tard')}
                </button>
              </div>
            </footer>
          </section>
        ) : (
          <div className="flex flex-col items-center justify-center gap-3 py-24 text-sm font-bold text-slate-600">
            {error ? (
              <>
                <p role="alert" className="text-sm font-semibold text-red-600">{error}</p>
                <button
                  type="button"
                  onClick={() => void complete()}
                  data-testid="interview-retry"
                  className="rounded-xl border border-slate-200 px-4 py-2 text-xs font-black text-slate-600 transition hover:border-slate-300 hover:bg-slate-50"
                >
                  {t('finish', 'Terminer')}
                </button>
                <button
                  type="button"
                  onClick={() => void dismiss()}
                  className="rounded-xl px-4 py-2 text-xs font-black text-slate-400 transition hover:text-slate-700"
                >
                  {t('later', 'Terminer plus tard')}
                </button>
              </>
            ) : (
              <>
                <Loader2 className="h-5 w-5 animate-spin text-emerald-500" aria-hidden="true" />
                {t('completing', 'Préparation de votre espace…')}
              </>
            )}
          </div>
        )}
      </div>
    </div>
  );
}
