'use client';

import { useEffect, useMemo, useState } from 'react';
import { CalendarPlus, Check, Loader2, X } from 'lucide-react';
import { ApiError, apiFetch } from '@/lib/api-client';
import { getCopy, normalizeLocale } from '@/lib/i18n';
import { ModulePageShell } from '@/components/module-page-shell';
import { Button } from '@/components/ui/Button';
import { useVitrineLocale } from '@/modules/vitrine/lib/vitrine-locale';

type AbsenceRecord = {
  id: number;
  start_date?: string;
  end_date?: string;
  status?: string;
  reason?: string | null;
  days_count?: number | string | null;
  absence_type?: {
    id?: number;
    name?: string;
  } | null;
};

type AbsencesPayload = {
  data?: AbsenceRecord[];
};

/** Solde de congés (GET /me/leave-balances) — source des types d'absence. */
type LeaveBalance = {
  id: number;
  absence_type_id?: number;
  balance?: number | string;
  used?: number | string;
  pending?: number | string;
  year?: number;
  absence_type?: {
    id?: number;
    name?: string;
    code?: string;
  } | null;
};

type BalancesPayload = {
  data?: LeaveBalance[];
};

const STATUS_LABEL_KEY: Record<string, keyof ReturnType<typeof getCopy>['absences']> = {
  pending: 'statusPending',
  approved: 'statusApproved',
  rejected: 'statusRejected',
  cancelled: 'statusCancelled',
};

export default function AbsencesPage() {
  const { locale } = useVitrineLocale();
  const appLocale = normalizeLocale(locale ?? 'fr');
  const labels = getCopy(appLocale).absences;

  const [absences, setAbsences] = useState<AbsenceRecord[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);
  const [actionId, setActionId] = useState<number | null>(null);
  const [rejectTarget, setRejectTarget] = useState<AbsenceRecord | null>(null);
  const [rejectReason, setRejectReason] = useState('');
  const [rejecting, setRejecting] = useState(false);
  const [rejectError, setRejectError] = useState<string | null>(null);

  // Demande de congé (issue #5693) : soldes = types disponibles + formulaire.
  const [balances, setBalances] = useState<LeaveBalance[]>([]);
  const [balancesError, setBalancesError] = useState(false);
  const [showForm, setShowForm] = useState(false);
  const [formTypeId, setFormTypeId] = useState('');
  const [formStart, setFormStart] = useState('');
  const [formEnd, setFormEnd] = useState('');
  const [formReason, setFormReason] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [formError, setFormError] = useState<string | null>(null);
  const [submitted, setSubmitted] = useState(false);

  useEffect(() => {
    let active = true;

    async function load() {
      try {
        const response = await apiFetch('/absences');
        const payload = await response.json() as AbsencesPayload;

        if (!active) {
          return;
        }

        setAbsences(Array.isArray(payload.data) ? payload.data : []);
      } catch (err) {
        if (!active) {
          return;
        }

        setError(err instanceof ApiError ? err.message : labels.loadError);
      } finally {
        if (active) {
          setLoading(false);
        }
      }
    }

    void load();

    return () => {
      active = false;
    };
  }, [labels.loadError]);

  // Soldes de congés — nécessaires pour lister les types disponibles dans le
  // formulaire de demande (même source que l'app mobile, issue #5693).
  useEffect(() => {
    let active = true;

    async function loadBalances() {
      try {
        const response = await apiFetch('/me/leave-balances');
        const payload = await response.json() as BalancesPayload;

        if (!active) {
          return;
        }

        setBalances(Array.isArray(payload.data) ? payload.data : []);
        setBalancesError(false);
      } catch {
        if (!active) {
          return;
        }

        setBalancesError(true);
      }
    }

    void loadBalances();

    return () => {
      active = false;
    };
  }, []);

  /** Types d'absence dédupliqués depuis les soldes (un solde par type/an). */
  const absenceTypeOptions = useMemo(() => {
    const seen = new Map<number, { id: number; name: string }>();

    for (const balance of balances) {
      const type = balance.absence_type ?? null;
      const typeId = type?.id ?? balance.absence_type_id;

      if (typeId === undefined || typeId === null) {
        continue;
      }

      if (!seen.has(typeId)) {
        seen.set(typeId, {
          id: typeId,
          name: type?.name ?? labels.typeFallback,
        });
      }
    }

    return [...seen.values()];
  }, [balances, labels.typeFallback]);

  const applyStatus = (id: number, status: string) => {
    setAbsences((prev) => prev.map((a) => (a.id === id ? { ...a, status } : a)));
  };

  const handleApprove = async (absence: AbsenceRecord) => {
    setActionId(absence.id);
    setError(null);
    try {
      await apiFetch(`/absences/${absence.id}/approve`, { method: 'PUT' });
      applyStatus(absence.id, 'approved');
    } catch (err) {
      setError(err instanceof ApiError ? err.message : labels.loadError);
    } finally {
      setActionId(null);
    }
  };

  const handleReject = async () => {
    if (!rejectTarget) {
      return;
    }
    const reason = rejectReason.trim();
    if (!reason) {
      setRejectError(labels.reasonRequired);
      return;
    }
    setRejecting(true);
    setRejectError(null);
    try {
      // Contrat API : PUT /absences/{id}/reject — rejected_reason obligatoire (1-1000).
      await apiFetch(`/absences/${rejectTarget.id}/reject`, {
        method: 'PUT',
        body: JSON.stringify({ rejected_reason: reason }),
      });
      applyStatus(rejectTarget.id, 'rejected');
      setRejectTarget(null);
      setRejectReason('');
    } catch (err) {
      setRejectError(err instanceof ApiError ? err.message : labels.loadError);
    } finally {
      setRejecting(false);
    }
  };

  /** Soumet une nouvelle demande de congé (issue #5693). */
  const handleSubmitRequest = async () => {
    const typeId = Number(formTypeId);
    const start = formStart.trim();
    const end = formEnd.trim();

    if (!typeId || Number.isNaN(typeId)) {
      setFormError(labels.typeRequired);
      return;
    }
    if (!start || !end) {
      setFormError(labels.dateMissing);
      return;
    }
    if (end < start) {
      setFormError(labels.dateMissing);
      return;
    }

    setSubmitting(true);
    setFormError(null);
    try {
      await apiFetch('/absences', {
        method: 'POST',
        body: JSON.stringify({
          absence_type_id: typeId,
          start_date: start,
          end_date: end,
          reason: formReason.trim() === '' ? null : formReason.trim(),
        }),
      });

      setShowForm(false);
      setFormTypeId('');
      setFormStart('');
      setFormEnd('');
      setFormReason('');
      setSubmitted(true);
      // Recharge la liste pour faire apparaître la demande en attente.
      const response = await apiFetch('/absences', { _cacheBust: true });
      const payload = await response.json() as AbsencesPayload;
      setAbsences(Array.isArray(payload.data) ? payload.data : []);
      window.setTimeout(() => setSubmitted(false), 5000);
    } catch (err) {
      setFormError(err instanceof ApiError ? err.message : labels.failure);
    } finally {
      setSubmitting(false);
    }
  };

  const statusLabel = (status: string | undefined) => {
    const key = STATUS_LABEL_KEY[status ?? ''] ?? 'statusPending';
    return labels[key];
  };

  return (
    <ModulePageShell
      title={labels.title}
      subtitle={labels.subtitle}
      accentClassName="from-emerald-500 to-teal-600"
    >
      <section className="rounded-2xl border border-app-border bg-white/40 shadow-glass-sm backdrop-blur-xl dark:bg-slate-900/40">
        {error && (
          <p role="alert" className="m-4 rounded-lg bg-red-50 px-3 py-2 text-xs font-medium text-red-700">
            {error}
          </p>
        )}

        {submitted && (
          <p
            role="status"
            className="m-4 rounded-lg bg-emerald-50 px-3 py-2 text-xs font-medium text-emerald-700"
          >
            {labels.submittedSnack}
          </p>
        )}

        <div className="flex flex-col gap-3 border-b border-app-border px-6 py-5 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <p className="text-sm font-bold text-slate-950 dark:text-white">{labels.listTitle}</p>
            <p className="text-xs text-slate-500">{labels.subtitle}</p>
          </div>
          <Button
            variant="primary"
            size="sm"
            onClick={() => {
              setFormError(null);
              setShowForm(true);
            }}
            disabled={absenceTypeOptions.length === 0}
            icon={<CalendarPlus className="h-4 w-4" />}
            className="bg-emerald-600 hover:bg-emerald-500"
          >
            {labels.request}
          </Button>
        </div>

        {loading ? (
          <div className="flex items-center justify-center gap-2 px-6 py-10 text-sm text-slate-500">
            <Loader2 className="h-4 w-4 animate-spin" aria-hidden="true" />
            <span>{labels.loading}</span>
          </div>
        ) : absences.length === 0 ? (
          <div className="px-6 py-8 text-sm text-slate-500">{labels.empty}</div>
        ) : (
          absences.map((absence) => {
            const isPending = absence.status === 'pending';
            const busy = actionId === absence.id;
            return (
              <div
                key={absence.id}
                className="flex flex-col gap-3 border-b border-app-border px-6 py-5 last:border-b-0 md:flex-row md:items-center md:justify-between"
              >
                <div>
                  <p className="text-sm font-bold text-slate-950">{absence.absence_type?.name ?? labels.title}</p>
                  <p className="text-xs text-slate-500">
                    {absence.start_date ?? '—'} au {absence.end_date ?? '—'}
                  </p>
                  {absence.reason ? (
                    <p className="mt-2 text-xs text-slate-500">{absence.reason}</p>
                  ) : null}
                </div>
                <div className="flex flex-wrap items-center gap-2">
                  <span className="rounded-full bg-slate-100 px-3 py-1 text-[11px] font-bold uppercase tracking-wider text-slate-600">
                    {absence.days_count ?? 0} j
                  </span>
                  <span
                    className={`rounded-full px-3 py-1 text-[11px] font-bold uppercase tracking-wider ${
                      absence.status === 'approved'
                        ? 'bg-emerald-50 text-emerald-700'
                        : absence.status === 'rejected'
                          ? 'bg-red-50 text-red-700'
                          : 'bg-info/15 text-info'
                    }`}
                  >
                    {statusLabel(absence.status)}
                  </span>
                  {isPending && (
                    <>
                      <Button
                        variant="primary"
                        size="sm"
                        onClick={() => void handleApprove(absence)}
                        disabled={busy}
                        loading={busy}
                        icon={<Check className="h-3.5 w-3.5" />}
                        className="bg-emerald-600 hover:bg-emerald-500"
                      >
                        {labels.approve}
                      </Button>
                      <Button
                        variant="outline"
                        size="sm"
                        onClick={() => {
                          setRejectTarget(absence);
                          setRejectReason('');
                          setRejectError(null);
                        }}
                        disabled={busy}
                        className="border-red-200 text-red-700 hover:border-red-300 hover:text-red-800"
                      >
                        {labels.reject}
                      </Button>
                    </>
                  )}
                </div>
              </div>
            );
          })
        )}
      </section>

      {showForm && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4 backdrop-blur-sm">
          <div
            className="w-full max-w-lg overflow-hidden rounded-3xl bg-white shadow-2xl dark:bg-slate-900"
            role="dialog"
            aria-modal="true"
            aria-label={labels.newAbsence}
          >
            <div className="flex items-center justify-between border-b border-app-border px-6 py-4">
              <div>
                <h2 className="text-lg font-bold text-slate-900 dark:text-white">{labels.newAbsence}</h2>
                <p className="mt-0.5 text-xs text-slate-500">{labels.newAbsenceHint}</p>
              </div>
              <button
                onClick={() => setShowForm(false)}
                aria-label={labels.cancel}
                className="rounded-full p-2 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600"
              >
                <X className="h-4 w-4" />
              </button>
            </div>

            <div className="p-6">
              {balancesError ? (
                <p className="rounded-lg bg-amber-50 px-3 py-2 text-xs font-medium text-amber-700">
                  {labels.noTypeAvailable}
                </p>
              ) : absenceTypeOptions.length === 0 ? (
                <p className="rounded-lg bg-amber-50 px-3 py-2 text-xs font-medium text-amber-700">
                  {labels.noTypeAvailable}
                </p>
              ) : (
                <form
                  className="space-y-4"
                  onSubmit={(event) => {
                    event.preventDefault();
                    void handleSubmitRequest();
                  }}
                >
                  <div>
                    <label htmlFor="absence-type" className="block text-xs font-bold uppercase tracking-wider text-slate-500">
                      {labels.type} <span className="text-red-500">*</span>
                    </label>
                    <select
                      id="absence-type"
                      value={formTypeId}
                      onChange={(e) => setFormTypeId(e.target.value)}
                      className="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-emerald-400 focus:ring-2 focus:ring-emerald-400/30 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                    >
                      <option value="">{labels.type}</option>
                      {absenceTypeOptions.map((option) => (
                        <option key={option.id} value={option.id}>
                          {option.name}
                        </option>
                      ))}
                    </select>
                  </div>

                  <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                      <label htmlFor="absence-start" className="block text-xs font-bold uppercase tracking-wider text-slate-500">
                        {labels.start} <span className="text-red-500">*</span>
                      </label>
                      <input
                        id="absence-start"
                        type="date"
                        value={formStart}
                        min={new Date().toISOString().split('T')[0]}
                        onChange={(e) => {
                          setFormStart(e.target.value);
                          if (formEnd && e.target.value > formEnd) {
                            setFormEnd(e.target.value);
                          }
                        }}
                        className="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-emerald-400 focus:ring-2 focus:ring-emerald-400/30 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                      />
                    </div>
                    <div>
                      <label htmlFor="absence-end" className="block text-xs font-bold uppercase tracking-wider text-slate-500">
                        {labels.end} <span className="text-red-500">*</span>
                      </label>
                      <input
                        id="absence-end"
                        type="date"
                        value={formEnd}
                        min={formStart || new Date().toISOString().split('T')[0]}
                        onChange={(e) => setFormEnd(e.target.value)}
                        className="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-emerald-400 focus:ring-2 focus:ring-emerald-400/30 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                      />
                    </div>
                  </div>

                  <div>
                    <label htmlFor="absence-reason" className="block text-xs font-bold uppercase tracking-wider text-slate-500">
                      {labels.reason}
                    </label>
                    <textarea
                      id="absence-reason"
                      value={formReason}
                      onChange={(e) => setFormReason(e.target.value)}
                      placeholder={labels.reasonHint}
                      rows={3}
                      maxLength={1000}
                      className="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-emerald-400 focus:ring-2 focus:ring-emerald-400/30 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                    />
                  </div>

                  {formError && (
                    <p role="alert" className="rounded-lg bg-red-50 px-3 py-2 text-xs font-medium text-red-700">
                      {formError}
                    </p>
                  )}

                  <div className="flex justify-end gap-3">
                    <Button variant="outline" onClick={() => setShowForm(false)} disabled={submitting} className="bg-white">
                      {labels.cancel}
                    </Button>
                    <Button
                      variant="primary"
                      type="submit"
                      disabled={submitting}
                      loading={submitting}
                      className="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-bold text-white hover:bg-emerald-500"
                    >
                      {submitting ? labels.statusPending : labels.submitToHr}
                    </Button>
                  </div>
                </form>
              )}
            </div>
          </div>
        </div>
      )}

      {rejectTarget && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4 backdrop-blur-sm">
          <div
            className="w-full max-w-md overflow-hidden rounded-3xl bg-white shadow-2xl dark:bg-slate-900"
            role="dialog"
            aria-modal="true"
            aria-label={labels.rejectTitle}
          >
            <div className="flex items-center justify-between border-b border-app-border px-6 py-4">
              <h2 className="text-lg font-bold text-slate-900 dark:text-white">{labels.rejectTitle}</h2>
              <button
                onClick={() => setRejectTarget(null)}
                aria-label={labels.cancel}
                className="rounded-full p-2 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600"
              >
                <X className="h-4 w-4" />
              </button>
            </div>
            <div className="p-6">
              <p className="text-sm text-slate-500">{labels.rejectBody}</p>
              <label htmlFor="reject-reason" className="mt-4 block text-xs font-bold uppercase tracking-wider text-slate-500">
                {labels.reasonLabel} <span className="text-red-500">*</span>
              </label>
              <textarea
                id="reject-reason"
                value={rejectReason}
                onChange={(e) => setRejectReason(e.target.value)}
                placeholder={labels.reasonPlaceholder}
                rows={3}
                className="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-red-400 focus:ring-2 focus:ring-red-400/30 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
              />
              {rejectError && (
                <p role="alert" className="mt-2 rounded-lg bg-red-50 px-3 py-2 text-xs font-medium text-red-700">
                  {rejectError}
                </p>
              )}
              <div className="mt-5 flex justify-end gap-3">
                <Button variant="outline" onClick={() => setRejectTarget(null)} disabled={rejecting} className="bg-white">
                  {labels.cancel}
                </Button>
                <Button
                  variant="danger"
                  onClick={() => void handleReject()}
                  disabled={rejecting}
                  loading={rejecting}
                  className="rounded-xl bg-red-600 px-4 py-2 text-sm font-bold text-white hover:bg-red-500"
                >
                  {rejecting ? labels.rejectInProgress : labels.rejectConfirm}
                </Button>
              </div>
            </div>
          </div>
        </div>
      )}
    </ModulePageShell>
  );
}
