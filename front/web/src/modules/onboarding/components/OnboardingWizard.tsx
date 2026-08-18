'use client';

import { useCallback, useEffect, useMemo, useState } from 'react';
import { AnimatePresence, motion } from 'framer-motion';
import {
  Building2,
  CalendarClock,
  CheckCircle2,
  ChevronRight,
  Fingerprint,
  Loader2,
  MapPin,
  ScanLine,
  SkipForward,
  Users,
  Wallet,
  X,
} from 'lucide-react';
import { apiFetch } from '@/lib/api-client';
import { useVitrineLocale } from '@/modules/vitrine/lib/vitrine-locale';
import { getCopy, normalizeLocale, storeAuthSession, type StoredAuthUser } from '@/lib/i18n';

/**
 * Wizard d'onboarding piloté par la VRAIE checklist backend
 * (`GET /onboarding-setup/checklist`). La checklist expose la shape canonique
 *   data: { completed_steps, total_steps, progress_percent, go_live_ready,
 *          steps: [{ step_key, title, description, status, order, required }] }
 *
 * Historique des correctifs :
 * - #3325 : on seedait via le checklist avant de marquer une étape complétée
 *   (table `onboarding_steps` vide au provisioning → 404 sinon).
 * - Audit web client 2026-08-17 : le wizard lisait `payload.data` comme un
 *   tableau alors que l'API renvoie un objet → `payload.data.filter` plantait
 *   (TypeError) et l'onboarding ne pouvait jamais être complété. Le wizard est
 *   désormais data-driven : il affiche les étapes réelles (titre localisé,
 *   fallback titre backend), complète/saute chaque étape via les endpoints
 *   PATCH dédiés (jamais de complétion fictive en un clic), rend le badge
 *   « Étape X sur Y » localisé ×4 et ajoute les aria-labels manquants.
 */

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
  progress_percent?: number;
  go_live_ready?: boolean;
  steps?: ChecklistStep[];
};

const STEP_ICONS: Record<string, React.ComponentType<{ className?: string }>> = {
  add_employees: Users,
  configure_payroll: Wallet,
  setup_schedules: CalendarClock,
  setup_geofence: MapPin,
  setup_kiosk: ScanLine,
  first_checkin: Fingerprint,
};

export function OnboardingWizard({
  user,
  onComplete,
}: {
  user: StoredAuthUser;
  onComplete: () => void;
}) {
  const { locale } = useVitrineLocale();
  const appLocale = normalizeLocale(locale ?? 'fr');
  const labels = getCopy(appLocale);
  const onboarding = labels.onboarding;

  const [isOpen, setIsOpen] = useState(true);
  const [steps, setSteps] = useState<ChecklistStep[] | null>(null);
  const [loading, setLoading] = useState(false);
  const [actionKey, setActionKey] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);

  const loadChecklist = useCallback(async () => {
    setError(null);
    setLoading(true);
    try {
      const response = await apiFetch('/onboarding-setup/checklist');
      const payload = (await response.json()) as { data?: ChecklistData };
      const list = Array.isArray(payload.data?.steps) ? payload.data.steps : [];
      setSteps(list.sort((a, b) => a.order - b.order));
    } catch (e) {
      console.error(e);
      setError(onboarding.errorGeneric);
    } finally {
      setLoading(false);
    }
  }, [onboarding.errorGeneric]);

  useEffect(() => {
    void loadChecklist();
  }, [loadChecklist]);

  const pendingSteps = useMemo(
    () => (steps ?? []).filter((s) => s.status === 'pending'),
    [steps],
  );
  const currentStep = pendingSteps[0] ?? null;
  const done = steps !== null && pendingSteps.length === 0;
  const progress = useMemo(() => {
    if (!steps || steps.length === 0) return 0;
    const completed = steps.filter((s) => s.status !== 'pending').length;
    return Math.round((completed / steps.length) * 100);
  }, [steps]);

  const stepMeta = (step: ChecklistStep) => {
    const localized = onboarding.steps[step.step_key];
    if (localized) return localized;
    return { title: step.title, desc: step.description ?? '' };
  };

  const applyStepResult = (stepKey: string, status: 'pending' | 'completed' | 'skipped') => {
    setSteps((prev) =>
      prev?.map((s) => (s.step_key === stepKey ? { ...s, status } : s)) ?? null,
    );
  };

  const mutateStep = async (step: ChecklistStep, status: 'completed' | 'skipped') => {
    if (status === 'skipped' && step.required) {
      return;
    }
    setActionKey(step.step_key);
    setError(null);
    try {
      // Routes backend : PATCH /onboarding-setup/{stepKey}/complete | /skip.
      const action = status === 'completed' ? 'complete' : 'skip';
      await apiFetch(`/onboarding-setup/${step.step_key}/${action}`, {
        method: 'PATCH',
      });
      applyStepResult(step.step_key, status);
    } catch (e) {
      console.error(e);
      setError(e instanceof Error ? e.message : onboarding.errorGeneric);
    } finally {
      setActionKey(null);
    }
  };

  const completeLocalOnboarding = () => {
    // Complétion locale uniquement : le backend connaît déjà l'état réel des
    // étapes (completed/skipped). On synchronise le profil local pour ne plus
    // ré-afficher le wizard au prochain chargement.
    const updatedUser = {
      ...user,
      company: {
        ...user.company,
        metadata: {
          ...(user.company?.metadata ?? {}),
          onboarding_completed: true,
        },
      },
    };
    storeAuthSession(null, updatedUser);
    setIsOpen(false);
    onComplete();
  };

  const handleDismiss = () => {
    setIsOpen(false);
    onComplete();
  };

  const handlePrimary = async () => {
    if (done) {
      completeLocalOnboarding();
      return;
    }
    if (!currentStep) {
      return;
    }
    await mutateStep(currentStep, 'completed');
  };

  const badgeText = onboarding.stepBadge
    .replace('{current}', String(done ? (steps?.length ?? 0) : (steps?.length ?? 0) - pendingSteps.length + 1))
    .replace('{total}', String(steps?.length ?? 0));

  if (!isOpen) return null;

  return (
    <AnimatePresence>
      <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4 backdrop-blur-sm">
        <motion.div
          initial={{ opacity: 0, scale: 0.95 }}
          animate={{ opacity: 1, scale: 1 }}
          exit={{ opacity: 0, scale: 0.95 }}
          className="relative w-full max-w-lg overflow-hidden rounded-3xl bg-white shadow-2xl"
          role="dialog"
          aria-modal="true"
          aria-label={onboarding.close}
        >
          <button
            onClick={handleDismiss}
            aria-label={onboarding.close}
            className="absolute right-4 top-4 z-10 rounded-full p-2 text-white/80 transition hover:bg-white/20 hover:text-white"
          >
            <X className="h-5 w-5" />
          </button>

          <div className="bg-gradient-to-br from-emerald-500 to-teal-600 p-8 text-white">
            <div className="mb-4 flex items-center justify-between">
              <span className="rounded-full bg-white/20 px-3 py-1 text-xs font-bold uppercase tracking-wider text-white">
                {badgeText}
              </span>
              <span className="text-xs font-semibold text-emerald-50">{progress}%</span>
            </div>
            <h2 className="text-2xl font-black">
              {done
                ? onboarding.allStepsDone
                : currentStep
                  ? stepMeta(currentStep).title
                  : ''}
            </h2>
            <p className="mt-2 text-emerald-50">
              {done || !currentStep ? '' : stepMeta(currentStep).desc}
            </p>
            <div className="mt-4 h-1.5 w-full overflow-hidden rounded-full bg-white/20">
              <div
                className="h-full rounded-full bg-white transition-all duration-500"
                style={{ width: `${progress}%` }}
              />
            </div>
          </div>

          <div className="p-8">
            <div className="space-y-6">
              {(steps ?? []).map((s) => {
                const meta = stepMeta(s);
                const isCompleted = s.status === 'completed';
                const isSkipped = s.status === 'skipped';
                const isActive = currentStep?.step_key === s.step_key;
                const Icon = STEP_ICONS[s.step_key] ?? Building2;
                return (
                  <div
                    key={s.step_key}
                    className={`flex items-center gap-4 transition-opacity ${
                      isActive ? 'opacity-100' : 'opacity-60'
                    }`}
                  >
                    <div
                      className={`flex h-10 w-10 shrink-0 items-center justify-center rounded-full ${
                        isCompleted
                          ? 'bg-emerald-100 text-emerald-600'
                          : isSkipped
                            ? 'bg-slate-100 text-slate-400'
                            : isActive
                              ? 'bg-teal-600 text-white shadow-lg'
                              : 'bg-slate-100 text-slate-400'
                      }`}
                    >
                      {isCompleted ? (
                        <CheckCircle2 className="h-5 w-5" aria-hidden="true" />
                      ) : isSkipped ? (
                        <SkipForward className="h-5 w-5" aria-hidden="true" />
                      ) : (
                        <Icon className="h-5 w-5" aria-hidden="true" />
                      )}
                    </div>
                    <div className="min-w-0 flex-1">
                      <p
                        className={`font-bold ${
                          isActive || isCompleted ? 'text-slate-900' : 'text-slate-500'
                        }`}
                      >
                        {meta.title}
                      </p>
                      {(isActive || isCompleted) && (
                        <p className="truncate text-xs text-slate-500">{meta.desc}</p>
                      )}
                    </div>
                    {isSkipped && (
                      <span className="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-slate-400">
                        {onboarding.skip}
                      </span>
                    )}
                  </div>
                );
              })}
            </div>

            {error && (
              <p
                role="alert"
                className="mt-6 w-full rounded-lg bg-red-50 px-3 py-2 text-xs font-medium text-red-700"
              >
                {error}
              </p>
            )}

            {steps === null ? (
              <div className="mt-8 flex flex-col items-end gap-2">
                <button
                  onClick={() => void loadChecklist()}
                  disabled={loading}
                  className="flex items-center justify-center gap-2 rounded-xl bg-slate-900 px-6 py-3 font-bold text-white transition hover:bg-slate-800 disabled:opacity-50"
                >
                  {loading ? (
                    <Loader2 className="h-4 w-4 animate-spin" aria-hidden="true" />
                  ) : (
                    onboarding.retry
                  )}
                </button>
              </div>
            ) : (
              <div className="mt-8 flex flex-col items-end gap-2">
                {currentStep && !currentStep.required && (
                  <button
                    onClick={() => void mutateStep(currentStep, 'skipped')}
                    disabled={actionKey === currentStep.step_key}
                    className="text-xs font-semibold text-slate-400 transition hover:text-slate-600 disabled:opacity-50"
                  >
                    {onboarding.skip}
                  </button>
                )}
                <button
                  onClick={() => void handlePrimary()}
                  disabled={loading || actionKey !== null}
                  className="flex items-center justify-center gap-2 rounded-xl bg-slate-900 px-6 py-3 font-bold text-white transition hover:bg-slate-800 disabled:opacity-50"
                >
                  {actionKey !== null ? (
                    <Loader2 className="h-4 w-4 animate-spin" aria-hidden="true" />
                  ) : done ? (
                    onboarding.finish
                  ) : pendingSteps.length === 1 ? (
                    onboarding.finish
                  ) : (
                    onboarding.next
                  )}
                  {!actionKey && pendingSteps.length > 1 && (
                    <ChevronRight className="h-4 w-4" aria-hidden="true" />
                  )}
                </button>
              </div>
            )}
          </div>
        </motion.div>
      </div>
    </AnimatePresence>
  );
}
