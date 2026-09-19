'use client';

import { useCallback, useEffect, useMemo, useState } from 'react';
import { CheckCircle2, ChevronDown, ChevronUp, ListChecks, X } from 'lucide-react';
import { apiFetch } from '@/lib/api-client';
import { getCopy, normalizeLocale, type AppLocale } from '@/lib/i18n';
import { t as i18nT } from '@/lib/i18n/locale-catalog';

/**
 * #7494 — carte « Prochaines étapes » du dashboard.
 *
 * Remplace la modale `OnboardingWizard` à ~10 étapes comme point d'entrée de
 * la mise en route : après l'entretien (#7493), les modules sont déjà
 * configurés et il ne reste que quelques actions réellement pertinentes,
 * seedées par profil côté serveur (`SeedDefaultSteps`).
 *
 * Format : carte DISCRÈTE (jamais de modale bloquante), repliable,
 * 3 étapes visibles max + « Tout voir », dismissible (réduite en pastille,
 * jamais perdue). Les endpoints existants sont conservés tels quels :
 * `GET /onboarding-setup/checklist` + `PATCH /onboarding-setup/{step}/
 * complete|skip` (validation serveur `StepCompletionGuard` — aucune
 * complétion fictive). Le filtre Quick Start (< 15 employés) reste actif :
 * les étapes optionnelles sont sautables en un clic.
 */

const VISIBLE_STEPS = 3;
const QUICK_START_THRESHOLD = 15;
// Repli local uniquement (préférence d'affichage, pas un état métier) —
// même pattern que la carte Leo IA du dashboard.
export const NEXT_STEPS_DISMISS_KEY = 'leopardo_next_steps_dismissed';

type ChecklistStep = {
  step_key: string;
  title: string;
  description?: string | null;
  status: 'pending' | 'completed' | 'skipped';
  order: number;
  required?: boolean;
};

type ChecklistData = {
  completed_steps?: number;
  total_steps?: number;
  go_live_ready?: boolean;
  employees_count?: number;
  steps?: ChecklistStep[];
};

export function NextStepsCard({ locale }: { locale: AppLocale }) {
  const appLocale = normalizeLocale(locale ?? 'fr');
  const copySteps = getCopy(appLocale).onboarding.steps;
  const t = useCallback(
    (key: string, fallback = '') => i18nT(appLocale, `nextSteps.${key}`, fallback),
    [appLocale],
  );

  const [steps, setSteps] = useState<ChecklistStep[] | null>(null);
  const [goLiveReady, setGoLiveReady] = useState(false);
  const [employeesCount, setEmployeesCount] = useState<number | null>(null);
  const [collapsed, setCollapsed] = useState(false);
  const [dismissed, setDismissed] = useState(false);
  // Préférence lue au montage (jamais en SSR — `window` absent au prerender).
  useEffect(() => {
    try {
      if (window.localStorage.getItem(NEXT_STEPS_DISMISS_KEY) === 'true') {
        setDismissed(true);
      }
    } catch {
      // localStorage indisponible (navigation privée) — la carte reste visible.
    }
  }, []);
  const [showAll, setShowAll] = useState(false);
  const [actionKey, setActionKey] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    let cancelled = false;
    (async () => {
      try {
        const response = await apiFetch('/onboarding-setup/checklist');
        if (!response.ok) return; // employé / autre rôle : la carte reste muette
        const payload = (await response.json()) as { data?: ChecklistData };
        if (cancelled) return;
        const list = Array.isArray(payload.data?.steps) ? payload.data.steps : [];
        setSteps([...list].sort((a, b) => a.order - b.order));
        setGoLiveReady(payload.data?.go_live_ready === true);
        if (typeof payload.data?.employees_count === 'number') {
          setEmployeesCount(payload.data.employees_count);
        }
      } catch {
        // Carte non essentielle : une erreur de chargement ne casse pas le dashboard.
      }
    })();
    return () => {
      cancelled = true;
    };
  }, []);

  const pending = useMemo(() => (steps ?? []).filter((s) => s.status === 'pending'), [steps]);
  const doneCount = useMemo(
    () => (steps ?? []).filter((s) => s.status !== 'pending').length,
    [steps],
  );
  const quickStart = employeesCount !== null && employeesCount < QUICK_START_THRESHOLD;

  const mutate = async (step: ChecklistStep, action: 'complete' | 'skip') => {
    if (action === 'skip' && step.required) return;
    setActionKey(step.step_key);
    setError(null);
    try {
      const response = await apiFetch(`/onboarding-setup/${step.step_key}/${action}`, {
        method: 'PATCH',
      });
      if (!response.ok) {
        throw new Error(`step ${action} failed (${response.status})`);
      }
      setSteps((prev) =>
        prev?.map((s) =>
          s.step_key === step.step_key
            ? { ...s, status: action === 'complete' ? 'completed' : 'skipped' }
            : s,
        ) ?? null,
      );
    } catch {
      setError(t('error', 'Impossible de charger vos prochaines étapes.'));
    } finally {
      setActionKey(null);
    }
  };

  const persistDismiss = (value: boolean) => {
    setDismissed(value);
    try {
      window.localStorage.setItem(NEXT_STEPS_DISMISS_KEY, value ? 'true' : 'false');
    } catch {
      // Préférence d'affichage best-effort.
    }
  };

  // Rien à faire : checklist absente, terminée ou tenant prêt.
  if (!steps || pending.length === 0 || goLiveReady) {
    return null;
  }

  // Dismiss = pastille discrète, jamais une perte : un clic restaure la carte.
  if (dismissed) {
    return (
      <button
        type="button"
        onClick={() => persistDismiss(false)}
        data-testid="next-steps-restore"
        className="mb-4 inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-3.5 py-1.5 text-[11px] font-black text-slate-600 shadow-sm transition hover:border-emerald-300 hover:text-emerald-700"
      >
        <ListChecks className="h-3.5 w-3.5" aria-hidden="true" />
        {t('restore', 'Prochaines étapes ({n})').replace('{n}', String(pending.length))}
      </button>
    );
  }

  const visible = showAll ? pending : pending.slice(0, VISIBLE_STEPS);

  const stepMeta = (step: ChecklistStep): { title: string; desc: string } => {
    const fromCopy = copySteps[step.step_key];
    if (fromCopy) return fromCopy;
    const title = i18nT(appLocale, `nextSteps.steps.${step.step_key}.title`, '');
    if (title) {
      return { title, desc: i18nT(appLocale, `nextSteps.steps.${step.step_key}.desc`, '') };
    }
    return { title: step.title, desc: step.description ?? '' };
  };

  return (
    <section
      data-testid="next-steps-card"
      aria-label={t('title', 'Prochaines étapes')}
      className="mb-6 overflow-hidden rounded-3xl border border-emerald-100 bg-gradient-to-br from-white via-white to-emerald-50/40 shadow-sm dark:border-emerald-900/40 dark:from-slate-900 dark:via-slate-900 dark:to-emerald-950/20"
    >
      <header className="flex items-center justify-between gap-3 px-5 py-4">
        <button
          type="button"
          onClick={() => setCollapsed((value) => !value)}
          aria-expanded={!collapsed}
          data-testid="next-steps-toggle"
          className="flex flex-1 items-center gap-3 text-left"
        >
          <span className="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-emerald-50 to-cyan-50 dark:from-emerald-900/40 dark:to-cyan-900/20">
            <ListChecks className="h-5 w-5 text-emerald-600 dark:text-emerald-400" aria-hidden="true" />
          </span>
          <span>
            <span className="block text-sm font-black text-slate-900 dark:text-white">
              {t('title', 'Prochaines étapes')}
              {quickStart ? (
                <span className="ml-2 rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-black uppercase tracking-widest text-emerald-700">
                  Quick Start
                </span>
              ) : null}
            </span>
            <span className="block text-xs font-semibold text-slate-500 dark:text-slate-400">
              {t('progress', '{done} sur {total}')
                .replace('{done}', String(doneCount))
                .replace('{total}', String(steps.length))}
              {' — '}
              {t('subtitle', 'Quelques actions pour finir votre mise en route.')}
            </span>
          </span>
          {collapsed ? (
            <ChevronDown className="ml-auto h-4 w-4 text-slate-400" aria-hidden="true" />
          ) : (
            <ChevronUp className="ml-auto h-4 w-4 text-slate-400" aria-hidden="true" />
          )}
        </button>
        <button
          type="button"
          onClick={() => persistDismiss(true)}
          aria-label={t('dismiss', 'Masquer cette carte')}
          title={t('dismiss', 'Masquer cette carte')}
          data-testid="next-steps-dismiss"
          className="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-50 hover:text-slate-700"
        >
          <X className="h-4 w-4" aria-hidden="true" />
        </button>
      </header>
      {!collapsed ? (
        <div className="border-t border-slate-100 px-5 py-4 dark:border-slate-800">
          <ul className="space-y-2">
            {visible.map((step) => {
              const meta = stepMeta(step);
              const busy = actionKey === step.step_key;
              return (
                <li
                  key={step.step_key}
                  data-testid={`next-step-${step.step_key}`}
                  className="flex flex-wrap items-center justify-between gap-2 rounded-2xl border border-slate-100 bg-slate-50/60 px-4 py-3 transition hover:border-emerald-200 hover:bg-emerald-50/30 dark:border-slate-800 dark:bg-slate-800/40 dark:hover:border-emerald-900"
                >
                  <div className="min-w-0 flex-1">
                    <p className="truncate text-sm font-bold text-slate-800 dark:text-slate-200">{meta.title}</p>
                    {meta.desc ? (
                      <p className="truncate text-xs text-slate-500 dark:text-slate-400">{meta.desc}</p>
                    ) : null}
                  </div>
                  <div className="flex items-center gap-1.5">
                    <button
                      type="button"
                      onClick={() => void mutate(step, 'complete')}
                      disabled={busy}
                      data-testid={`next-step-complete-${step.step_key}`}
                      className="inline-flex items-center gap-1.5 rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-[11px] font-black text-emerald-700 transition hover:bg-emerald-100 disabled:opacity-50"
                    >
                      <CheckCircle2 className="h-3.5 w-3.5" aria-hidden="true" />
                      {t('complete', 'Marquer comme fait')}
                    </button>
                    {step.required !== true ? (
                      <button
                        type="button"
                        onClick={() => void mutate(step, 'skip')}
                        disabled={busy}
                        data-testid={`next-step-skip-${step.step_key}`}
                        className="rounded-xl px-3 py-1.5 text-[11px] font-black text-slate-400 transition hover:text-slate-700 disabled:opacity-50"
                      >
                        {t('skip', 'Passer')}
                      </button>
                    ) : null}
                  </div>
                </li>
              );
            })}
          </ul>
          {pending.length > VISIBLE_STEPS ? (
            <button
              type="button"
              onClick={() => setShowAll((value) => !value)}
              data-testid="next-steps-view-all"
              className="mt-3 text-xs font-black text-emerald-700 transition hover:text-emerald-900"
            >
              {showAll
                ? t('viewLess', 'Réduire')
                : t('viewAll', 'Tout voir ({n})').replace('{n}', String(pending.length))}
            </button>
          ) : null}
          {error ? (
            <p role="alert" className="mt-3 text-xs font-semibold text-red-600">
              {error}
            </p>
          ) : null}
        </div>
      ) : null}
    </section>
  );
}
