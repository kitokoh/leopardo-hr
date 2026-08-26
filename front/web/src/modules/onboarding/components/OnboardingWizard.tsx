'use client';

import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { AnimatePresence, motion } from 'framer-motion';
import {
  BarChart2,
  Building2,
  CalendarClock,
  CheckCircle2,
  ChevronRight,
  Fingerprint,
  Loader2,
  MapPin,
  QrCode,
  ScanLine,
  Send,
  SkipForward,
  Users,
  Wallet,
  X,
} from 'lucide-react';
import QRCode from 'qrcode';
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
  // #R6 — employees_count exposé par OnboardingStepController pour le Quick Start.
  employees_count?: number;
  steps?: ChecklistStep[];
};

// #R1 — aligné sur les clés réelles de DEFAULT_STEPS (SeedDefaultSteps.php).
// Les anciens step_key (add_employees, setup_schedules, first_checkin…) étaient
// ceux d'une version précédente du wizard : aucun ne matchait, toutes les étapes
// affichaient Building2 par défaut et le QR de l'étape first_attendance n'était
// jamais rendu.
const STEP_ICONS: Record<string, React.ComponentType<{ className?: string }>> = {
  company_info: Building2,
  first_department: Building2,
  first_employee: Users,
  first_attendance: Fingerprint,
  invite_manager: Send,
  configure_schedules: CalendarClock,
  first_report: BarChart2,
  configure_payroll: Wallet,
  install_kiosk: ScanLine,
  activate_geofence: MapPin,
};

// #R7 — Correspondance clé du moteur calculé (OnboardingChecklistController)
// → clé de la table onboarding_steps (DEFAULT_STEPS). Permet l'auto-complétion
// contextuelle : si la condition réelle est remplie, l'étape est marquée
// complétée sans action manuelle de l'utilisateur.
const CALCULATED_TO_SETUP_MAPPING: Record<string, string> = {
  employees_added: 'first_employee',
  payroll_ready: 'configure_payroll',
  geofence_configured: 'activate_geofence',
  kiosk_connected: 'install_kiosk',
};

// #R10 — estimations de durée par étape (sourcées ONBOARDING_PILOTE.md).
// Non bloquantes : si une clé est absente, aucune estimation n'est affichée.
const STEP_ESTIMATED_MINUTES: Record<string, number> = {
  company_info: 3,
  first_department: 2,
  first_employee: 6,
  first_attendance: 3,
  invite_manager: 3,
  configure_schedules: 3,
  first_report: 2,
  configure_payroll: 4,
  install_kiosk: 5,
  activate_geofence: 2,
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
  const [employeesCount, setEmployeesCount] = useState<number | null>(null);
  const [qrData, setQrData] = useState<string | null>(null);
  const [qrLoading, setQrLoading] = useState(false);
  const [qrError, setQrError] = useState<string | null>(null);
  const qrCanvasRef = useRef<HTMLCanvasElement | null>(null);
  // #R7 — guard : l'auto-complétion ne tourne qu'une seule fois par session.
  const autoCompletedRef = useRef(false);

  const loadChecklist = useCallback(async () => {
    setError(null);
    setLoading(true);
    try {
      const response = await apiFetch('/onboarding-setup/checklist');
      const payload = (await response.json()) as { data?: ChecklistData };
      const list = Array.isArray(payload.data?.steps) ? payload.data.steps : [];
      setSteps(list.sort((a, b) => a.order - b.order));
      // #R6 — employees_count désormais exposé par le setup checklist.
      if (typeof payload.data?.employees_count === 'number') {
        setEmployeesCount(payload.data.employees_count);
      }
    } catch (e) {
      console.error(e);
      setError(onboarding.errorGeneric);
    } finally {
      setLoading(false);
    }
  }, [onboarding.errorGeneric]);

  // Quick Start (#4939) + #R7 (auto-complétion contextuelle) :
  // charge le moteur calculé (GET /onboarding/checklist) pour :
  //   1. Obtenir employees_count → badge Quick Start.
  //   2. Détecter les étapes déjà remplies en réalité → les marquer complétées
  //      automatiquement (sans action manuelle) — non bloquant dans les deux cas.
  const loadCalculatedAndAutoComplete = useCallback(async (currentSteps: ChecklistStep[]) => {
    try {
      const response = await apiFetch('/onboarding/checklist');
      const payload = (await response.json()) as {
        data?: { steps?: Array<{ key?: string; completed?: boolean; metrics?: { employees_count?: number } }> };
      };
      const calculatedSteps = payload.data?.steps ?? [];

      // Quick Start : taille de l'entreprise.
      const empStep = calculatedSteps.find((s) => s.key === 'employees_added');
      if (typeof empStep?.metrics?.employees_count === 'number') {
        setEmployeesCount(empStep.metrics.employees_count);
      }

      // #R7 — auto-complétion : pour chaque étape pending du setup checklist
      // dont la condition réelle est confirmée par le moteur calculé, envoyer
      // PATCH complete et mettre à jour l'état local.
      const autoCompletable = currentSteps.filter((setupStep) => {
        if (setupStep.status !== 'pending') return false;
        const calcKey = Object.entries(CALCULATED_TO_SETUP_MAPPING).find(
          ([, setupKey]) => setupKey === setupStep.step_key,
        )?.[0];
        if (!calcKey) return false;
        return calculatedSteps.find((s) => s.key === calcKey)?.completed === true;
      });

      for (const step of autoCompletable) {
        try {
          await apiFetch(`/onboarding-setup/${step.step_key}/complete`, { method: 'PATCH' });
          setSteps((prev) =>
            prev?.map((s) => (s.step_key === step.step_key ? { ...s, status: 'completed' } : s)) ?? null,
          );
        } catch {
          // Non-bloquant : si le PATCH échoue, le wizard reste fonctionnel.
        }
      }
    } catch (e) {
      console.error(e);
    }
  }, []);

  useEffect(() => {
    void loadChecklist();
  }, [loadChecklist]);

  // #R7 — déclencher l'auto-complétion une seule fois après le premier chargement.
  useEffect(() => {
    if (steps === null || autoCompletedRef.current) return;
    autoCompletedRef.current = true;
    void loadCalculatedAndAutoComplete(steps);
  }, [steps, loadCalculatedAndAutoComplete]);

  useEffect(() => {
    if (qrData && qrCanvasRef.current) {
      QRCode.toCanvas(qrCanvasRef.current, qrData, { width: 180, margin: 1 }, (err: Error | null | undefined) => {
        if (err) {
          console.error(err);
          setQrError(onboarding.qrError);
        }
      });
    }
  }, [qrData, onboarding.qrError]);

  const fetchQr = async () => {
    if (qrData) {
      setQrData(null);
      return;
    }
    setQrLoading(true);
    setQrError(null);
    try {
      // #4938 — QR d'onboarding de l'entreprise (manager principal/rh requis, 403 sinon).
      const response = await apiFetch('/company/qr-onboarding');
      const payload = (await response.json()) as { data?: { token?: string } };
      if (payload.data?.token) {
        setQrData(payload.data.token);
      } else {
        setQrError(onboarding.qrError);
      }
    } catch (e) {
      console.error(e);
      setQrError(e instanceof Error ? e.message : onboarding.qrError);
    } finally {
      setQrLoading(false);
    }
  };

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

  // Quick Start (#4939) : < 15 employés → expérience raccourcie (badge + skip renforcé).
  const quickStart = employeesCount !== null && employeesCount < 15;
  // #R1 — clé corrigée : DEFAULT_STEPS utilise 'first_attendance', pas 'first_checkin'.
  const isFirstAttendance = currentStep?.step_key === 'first_attendance';
  // #R5 — feedback queue à l'étape invite_manager.
  const isInviteManager = currentStep?.step_key === 'invite_manager';
  // #R13 — aide CSV à l'étape first_employee.
  const isFirstEmployee = currentStep?.step_key === 'first_employee';

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
            <div className="mb-4 flex items-center justify-between gap-2">
              <span className="rounded-full bg-white/20 px-3 py-1 text-xs font-bold uppercase tracking-wider text-white">
                {badgeText}
              </span>
              <span className="flex items-center gap-2">
                {quickStart && (
                  <span className="rounded-full bg-amber-400/90 px-3 py-1 text-xs font-black uppercase tracking-wider text-amber-950">
                    {onboarding.quickStart}
                  </span>
                )}
                <span className="text-xs font-semibold text-emerald-50">{progress}%</span>
              </span>
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
                    {quickStart && !isCompleted && !isSkipped && s.required === false && (
                      <span className="rounded-full bg-amber-50 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-amber-600">
                        {onboarding.later}
                      </span>
                    )}
                    {/* #R10 — estimation de temps pour les étapes non terminées */}
                    {!isCompleted && !isSkipped && STEP_ESTIMATED_MINUTES[s.step_key] && (
                      <span className="shrink-0 text-[10px] font-semibold text-slate-400">
                        {onboarding.estimatedMinutes.replace('{n}', String(STEP_ESTIMATED_MINUTES[s.step_key]))}
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
                {/* #R13 — aide CSV à l'étape first_employee */}
                {isFirstEmployee && (
                  <div className="w-full rounded-2xl border border-blue-100 bg-blue-50 p-4">
                    <p className="text-xs font-medium text-blue-700">{onboarding.csvColumnsHint}</p>
                  </div>
                )}
                {/* #R5 — feedback queue à l'étape invite_manager */}
                {isInviteManager && (
                  <div className="w-full rounded-2xl border border-amber-100 bg-amber-50 p-4">
                    <p className="text-xs font-medium text-amber-700">{onboarding.inviteManagerHint}</p>
                  </div>
                )}
                {isFirstAttendance && (
                  <div className="w-full rounded-2xl border border-slate-200 bg-slate-50 p-4">
                    <p className="text-xs font-medium text-slate-600">{onboarding.firstCheckinHint}</p>
                    <button
                      onClick={() => void fetchQr()}
                      disabled={qrLoading}
                      className="mt-3 inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-4 py-2 text-xs font-bold text-slate-700 transition hover:border-emerald-400 hover:text-emerald-700 disabled:opacity-50"
                    >
                      {qrLoading ? (
                        <Loader2 className="h-4 w-4 animate-spin" aria-hidden="true" />
                      ) : (
                        <QrCode className="h-4 w-4" aria-hidden="true" />
                      )}
                      {qrLoading ? onboarding.qrLoading : qrData ? onboarding.qrHide : onboarding.qrShow}
                    </button>
                    {qrData && (
                      <div className="mt-3 flex flex-col items-center gap-2">
                        <canvas
                          ref={qrCanvasRef}
                          role="img"
                          aria-label={onboarding.qrHint}
                          className="rounded-xl bg-white p-2 shadow-sm"
                        />
                        <p className="text-center text-xs text-slate-500">{onboarding.qrHint}</p>
                      </div>
                    )}
                    {qrError && (
                      <p role="alert" className="mt-2 rounded-lg bg-red-50 px-3 py-2 text-xs font-medium text-red-700">
                        {qrError}
                      </p>
                    )}
                  </div>
                )}
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
