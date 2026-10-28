'use client';

import { useCallback, useMemo, useState } from 'react';
import { AnimatePresence, motion } from 'framer-motion';
import { ArrowLeft, Check, Edit3, Loader2, Sparkles, X } from 'lucide-react';
import { apiFetch } from '@/lib/api-client';
import type { AppLocale } from '@/lib/i18n';
import { t as i18nT } from '@/lib/i18n/locale-catalog';

/**
 * #8231 (BOS-043) — ecran d'accueil Lea : description libre → resume
 * structure **editable** → confirmation.
 *
 * Trois etats, jamais fusionnes, parce qu'ils ont trois consequences
 * differentes :
 *
 *  1. `describe`   — rien n'est engage. L'appel `interpret` ne fait que
 *                    proposer ; le serveur n'active rien (contrat #8230 :
 *                    `interpret` rend un plan + un jeton, `confirm` execute).
 *  2. `clarify`    — le modele a besoin d'une precision (<= 2 tours). On
 *                    n'affiche PAS de resume tant que la precision manque :
 *                    montrer un plan partiel ferait confirmer un perimetre
 *                    faux.
 *  3. `review`     — le resume est editable AVANT confirmation. C'est le
 *                    point de l'issue : l'utilisateur doit pouvoir corriger ce
 *                    que le systeme a compris, sans recommencer sa saisie.
 *
 * **Bascule formulaire permanente** : `onUseForm` est disponible a chaque
 * etape (pas seulement en cas d'erreur). Un parcours IA qui n'offre pas de
 * sortie enfermerait l'utilisateur des que le modele se trompe — et
 * `outcome === 'fallback'` (LLM indisponible, prompt refuse) y bascule
 * automatiquement.
 *
 * Le flag `onboarding.lea.enabled` est verifie **cote serveur** (#8230) : en
 * 404, cet ecran n'est pas monte du tout (l'appelant consulte la
 * disponibilite), mais un 404 tardif retombe proprement sur le formulaire.
 */

type LeaOutcome = 'plan_ready' | 'clarification' | 'fallback';

type LeaInterpretResponse = {
  data?: {
    outcome?: LeaOutcome;
    answers?: Record<string, unknown>;
    plan?: { solutions?: string[]; tools?: string[] };
    plan_token?: string;
    questions?: string[];
    reason_code?: string;
  };
  message?: string;
};

type LeaStep = 'describe' | 'clarify' | 'review';

/**
 * Etat du parcours. `answers` est conserve tel que le serveur l'a rendu : il
 * est renvoye **inchange** a `confirm`, avec le `plan_token` qui le scelle.
 * Modifier `answers` a la main invaliderait le jeton (409) — la correction
 * du resume passe donc par une nouvelle interpretation (`clarify`), pas par
 * une mutation locale du plan.
 */
type LeaState = {
  step: LeaStep;
  description: string;
  answers: Record<string, unknown>;
  plan: { solutions: string[]; tools: string[] } | null;
  planToken: string | null;
  questions: string[];
};

const EMPTY_STATE: LeaState = {
  step: 'describe',
  description: '',
  answers: {},
  plan: null,
  planToken: null,
  questions: [],
};

/** Bornes du contrat #8230 (`min:12`, `max:2000`). */
const MIN_DESCRIPTION = 12;
const MAX_DESCRIPTION = 2000;
/** Le serveur borne la clarification a 2 tours. */
const MAX_CLARIFICATION_ROUNDS = 2;

export function LeaOnboarding({
  locale,
  onConfirmed,
  onUseForm,
  onDismiss,
}: {
  locale: AppLocale;
  /** Le plan a ete execute : l'appelant recharge `/auth/me` (mecanisme #7245). */
  onConfirmed: (result: { solutions: string[]; tools: string[] }) => void;
  /** Bascule vers l'entretien deterministe — disponible a TOUTE etape. */
  onUseForm: () => void;
  onDismiss: () => void;
}) {
  const t = useCallback(
    (key: string, fallback = '') => i18nT(locale, `lea.${key}`, fallback),
    [locale],
  );

  const [state, setState] = useState<LeaState>(EMPTY_STATE);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [clarificationRound, setClarificationRound] = useState(0);
  const [clarifyDraft, setClarifyDraft] = useState<Record<string, string>>({});
  const [editing, setEditing] = useState(false);

  const descriptionTooShort = state.description.trim().length < MIN_DESCRIPTION;

  const submitDescription = useCallback(async () => {
    if (descriptionTooShort || busy) return;
    setBusy(true);
    setError(null);
    try {
      const response = await apiFetch('/onboarding/lea/interpret', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ description: state.description.trim() }),
      });

      // 404 = flag serveur OFF : on ne simule pas un parcours qui n'existe
      // pas, on rend la main au formulaire qui, lui, est toujours disponible.
      if (response.status === 404) {
        onUseForm();
        return;
      }

      const payload = (await response.json()) as LeaInterpretResponse;
      const data = payload.data;

      if (data?.outcome === 'fallback' || !data?.outcome) {
        // Lea n'a pas compris / n'est pas disponible. Le formulaire reprend
        // la main : c'est le comportement defini par BOS-040, pas une erreur.
        onUseForm();
        return;
      }

      if (data.outcome === 'clarification') {
        setState((prev) => ({
          ...prev,
          step: 'clarify',
          questions: data.questions ?? [],
        }));
        setClarifyDraft({});
        return;
      }

      setState((prev) => ({
        ...prev,
        step: 'review',
        answers: data.answers ?? {},
        plan: {
          solutions: data.plan?.solutions ?? [],
          tools: data.plan?.tools ?? [],
        },
        planToken: data.plan_token ?? null,
      }));
    } catch {
      setError(t('error.network', 'Connexion impossible.'));
    } finally {
      setBusy(false);
    }
  }, [busy, descriptionTooShort, onUseForm, state.description, t]);

  const submitClarification = useCallback(async () => {
    if (busy) return;
    // Le serveur exige `answers` (objet) ET `extracted` pour lever une
    // clarification sans re-interpreter le texte : sans `extracted`, il
    // relancerait l'extraction sur la description et « redevinerait » ce que
    // l'utilisateur vient de trancher.
    const answers = Object.fromEntries(
      Object.entries(clarifyDraft).filter(([, value]) => value.trim() !== ''),
    );
    if (Object.keys(answers).length === 0) return;

    setBusy(true);
    setError(null);
    try {
      const response = await apiFetch('/onboarding/lea/interpret', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          description: state.description.trim(),
          answers,
          extracted: state.answers,
        }),
      });

      if (response.status === 404) {
        onUseForm();
        return;
      }

      const payload = (await response.json()) as LeaInterpretResponse;
      const data = payload.data;

      if (data?.outcome === 'fallback' || !data?.outcome) {
        onUseForm();
        return;
      }

      if (data.outcome === 'clarification') {
        const nextRound = clarificationRound + 1;
        // Au-dela de 2 tours, on ne boucle pas indefiniment : le formulaire
        // reprend la main. Un parcours qui repose la meme question est vecu
        // comme une panne, et le contrat borne la clarification a 2 tours.
        if (nextRound >= MAX_CLARIFICATION_ROUNDS) {
          onUseForm();
          return;
        }
        setClarificationRound(nextRound);
        setState((prev) => ({ ...prev, questions: data.questions ?? [] }));
        setClarifyDraft({});
        return;
      }

      setState((prev) => ({
        ...prev,
        step: 'review',
        answers: { ...prev.answers, ...(data.answers ?? {}) },
        plan: {
          solutions: data.plan?.solutions ?? [],
          tools: data.plan?.tools ?? [],
        },
        planToken: data.plan_token ?? null,
      }));
    } catch {
      setError(t('error.network', 'Connexion impossible.'));
    } finally {
      setBusy(false);
    }
  }, [busy, clarifyDraft, clarificationRound, onUseForm, state.answers, state.description, t]);

  const confirmPlan = useCallback(async () => {
    if (busy || !state.planToken) return;
    setBusy(true);
    setError(null);
    try {
      const response = await apiFetch('/onboarding/lea/confirm', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          answers: state.answers,
          plan_token: state.planToken,
        }),
      });

      // 409 = le plan affiche n'est plus celui des reponses (jeton perime).
      // On redemande une confirmation sur le plan a jour plutot que de
      // laisser croire l'activation faite (contrat #8230).
      if (response.status === 409) {
        setState((prev) => ({ ...prev, step: 'describe', planToken: null }));
        setError(t('error.stale', 'Le resume a change. Verifiez-le a nouveau.'));
        return;
      }

      const payload = (await response.json()) as LeaInterpretResponse;
      if (!response.ok) {
        setError(t('error.confirm', "L'activation a echoue."));
        return;
      }

      onConfirmed({
        solutions: payload.data?.plan?.solutions ?? state.plan?.solutions ?? [],
        tools: payload.data?.plan?.tools ?? state.plan?.tools ?? [],
      });
    } catch {
      setError(t('error.network', 'Connexion impossible.'));
    } finally {
      setBusy(false);
    }
  }, [busy, onConfirmed, state.answers, state.plan, state.planToken, t]);

  const summaryRows = useMemo(() => {
    const plan = state.plan;
    if (!plan) return [];
    return [
      {
        key: 'solutions',
        label: t('review.solutions', 'Solutions'),
        values: plan.solutions,
      },
      {
        key: 'tools',
        label: t('review.tools', 'Outils'),
        values: plan.tools,
      },
    ].filter((row) => row.values.length > 0);
  }, [state.plan, t]);

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
      <motion.div
        initial={{ opacity: 0, y: 12 }}
        animate={{ opacity: 1, y: 0 }}
        exit={{ opacity: 0, y: 12 }}
        className="w-full max-w-xl rounded-2xl bg-white p-6 shadow-xl"
        role="dialog"
        aria-modal="true"
        aria-labelledby="lea-title"
      >
        <div className="mb-4 flex items-start justify-between gap-3">
          <div className="flex items-center gap-2">
            <Sparkles className="h-5 w-5 text-indigo-500" aria-hidden />
            <h2 id="lea-title" className="text-lg font-semibold text-slate-900">
              {t('title', 'Decrivez votre activite')}
            </h2>
          </div>
          <button
            type="button"
            onClick={onDismiss}
            className="rounded p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600"
            aria-label={t('later', 'Plus tard')}
          >
            <X className="h-4 w-4" />
          </button>
        </div>

        <AnimatePresence mode="wait">
          {state.step === 'describe' && (
            <motion.div key="describe" initial={{ opacity: 0 }} animate={{ opacity: 1 }}>
              <p className="mb-3 text-sm text-slate-600">
                {t('subtitle')}
              </p>
              <label htmlFor="lea-description" className="sr-only">
                {t('title')}
              </label>
              <textarea
                id="lea-description"
                value={state.description}
                onChange={(event) =>
                  setState((prev) => ({ ...prev, description: event.target.value }))
                }
                rows={5}
                maxLength={MAX_DESCRIPTION}
                placeholder={t('placeholder')}
                className="w-full resize-none rounded-xl border border-slate-300 p-3 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
              />
              <div className="mt-1 flex items-center justify-between text-xs text-slate-400">
                <span>{t('hint.min')}</span>
                <span>
                  {state.description.length}/{MAX_DESCRIPTION}
                </span>
              </div>

              <div className="mt-4 flex items-center justify-between gap-3">
                <button
                  type="button"
                  onClick={onUseForm}
                  className="text-sm text-slate-500 underline hover:text-slate-700"
                >
                  {t('useForm', 'Utiliser le formulaire')}
                </button>
                <button
                  type="button"
                  onClick={submitDescription}
                  disabled={descriptionTooShort || busy}
                  className="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-4 py-2 text-sm font-medium text-white disabled:opacity-40"
                >
                  {busy ? (
                    <Loader2 className="h-4 w-4 animate-spin" aria-hidden />
                  ) : (
                    <Sparkles className="h-4 w-4" aria-hidden />
                  )}
                  {t('analyse', 'Analyser')}
                </button>
              </div>
            </motion.div>
          )}

          {state.step === 'clarify' && (
            <motion.div key="clarify" initial={{ opacity: 0 }} animate={{ opacity: 1 }}>
              <p className="mb-3 text-sm text-slate-600">
                {t('clarify.title', "Deux precisions et je prepare le resume.")}
              </p>
              <div className="space-y-3">
                {state.questions.map((question, questionIndex) => (
                  <div key={`${question}-${questionIndex}`}>
                    <label
                      htmlFor={`lea-clarify-${questionIndex}`}
                      className="mb-1 block text-sm font-medium text-slate-700"
                    >
                      {question}
                    </label>
                    <input
                      id={`lea-clarify-${questionIndex}`}
                      value={clarifyDraft[question] ?? ''}
                      onChange={(event) =>
                        setClarifyDraft((prev) => ({ ...prev, [question]: event.target.value }))
                      }
                      className="w-full rounded-xl border border-slate-300 p-2 text-sm focus:border-indigo-500 focus:outline-none"
                    />
                  </div>
                ))}
              </div>

              <div className="mt-4 flex items-center justify-between gap-3">
                <div className="flex items-center gap-3">
                  <button
                    type="button"
                    onClick={() => setState((prev) => ({ ...prev, step: 'describe' }))}
                    className="inline-flex items-center gap-1 text-sm text-slate-500 hover:text-slate-700"
                  >
                    <ArrowLeft className="h-4 w-4" aria-hidden />
                    {t('back', 'Retour')}
                  </button>
                  <button
                    type="button"
                    onClick={onUseForm}
                    className="text-sm text-slate-500 underline hover:text-slate-700"
                  >
                    {t('useForm', 'Utiliser le formulaire')}
                  </button>
                </div>
                <button
                  type="button"
                  onClick={submitClarification}
                  disabled={busy || Object.keys(clarifyDraft).length === 0}
                  className="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-4 py-2 text-sm font-medium text-white disabled:opacity-40"
                >
                  {busy && <Loader2 className="h-4 w-4 animate-spin" aria-hidden />}
                  {t('clarify.submit', 'Continuer')}
                </button>
              </div>
            </motion.div>
          )}

          {state.step === 'review' && (
            <motion.div key="review" initial={{ opacity: 0 }} animate={{ opacity: 1 }}>
              <p className="mb-3 text-sm text-slate-600">
                {t('review.title')}
              </p>

              {summaryRows.length === 0 ? (
                <p className="rounded-xl bg-amber-50 p-3 text-sm text-amber-800">
                  {t('review.empty')}
                </p>
              ) : (
                <ul className="space-y-2">
                  {summaryRows.map((row) => (
                    <li key={row.key} className="rounded-xl border border-slate-200 p-3">
                      <div className="mb-1 text-xs uppercase tracking-wide text-slate-400">
                        {row.label}
                      </div>
                      <div className="flex flex-wrap gap-2">
                        {row.values.map((value) => (
                          <span
                            key={value}
                            className="rounded-full bg-indigo-50 px-2 py-1 text-xs text-indigo-700"
                          >
                            {value}
                          </span>
                        ))}
                      </div>
                    </li>
                  ))}
                </ul>
              )}

              {editing ? (
                <p className="mt-3 text-xs text-slate-500">
                  {t('review.editHint')}
                </p>
              ) : null}

              <div className="mt-4 flex flex-wrap items-center justify-between gap-3">
                <div className="flex flex-wrap items-center gap-3">
                  <button
                    type="button"
                    onClick={() => {
                      setEditing((prev) => !prev);
                      setState((prev) => ({ ...prev, step: 'describe' }));
                    }}
                    className="inline-flex items-center gap-1 text-sm text-slate-500 hover:text-slate-700"
                  >
                    <Edit3 className="h-4 w-4" aria-hidden />
                    {t('review.edit', 'Corriger')}
                  </button>
                  <button
                    type="button"
                    onClick={onUseForm}
                    className="text-sm text-slate-500 underline hover:text-slate-700"
                  >
                    {t('useForm', 'Utiliser le formulaire')}
                  </button>
                </div>
                <button
                  type="button"
                  onClick={confirmPlan}
                  disabled={busy || !state.planToken || summaryRows.length === 0}
                  className="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-4 py-2 text-sm font-medium text-white disabled:opacity-40"
                >
                  {busy ? (
                    <Loader2 className="h-4 w-4 animate-spin" aria-hidden />
                  ) : (
                    <Check className="h-4 w-4" aria-hidden />
                  )}
                  {t('review.confirm', 'Confirmer et activer')}
                </button>
              </div>
            </motion.div>
          )}
        </AnimatePresence>

        {error ? (
          <p role="alert" className="mt-3 rounded-lg bg-red-50 p-2 text-sm text-red-700">
            {error}
          </p>
        ) : null}
      </motion.div>
    </div>
  );
}

export default LeaOnboarding;
