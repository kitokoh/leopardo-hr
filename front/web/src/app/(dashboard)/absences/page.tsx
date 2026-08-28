'use client';

import { useCallback, useEffect, useState } from 'react';
import { Check, Loader2, X } from 'lucide-react';
import { ApiError, apiFetch } from '@/lib/api-client';
import LeaveRequestForm from '@/components/absences/LeaveRequestForm';
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
    name?: string;
  } | null;
};

type AbsencesPayload = {
  data?: AbsenceRecord[];
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

  const load = useCallback(async () => {
    try {
      const response = await apiFetch('/absences');
      const payload = await response.json() as AbsencesPayload;
      setAbsences(Array.isArray(payload.data) ? payload.data : []);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : labels.loadError);
    } finally {
      setLoading(false);
    }
  }, [labels.loadError]);

  useEffect(() => {
    let active = true;

    void load().then(() => {
      active = false;
    });

    return () => {
      active = false;
    };
  }, [load]);

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
      setRejectError(labels.rejectReasonRequired);
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

  const statusLabel = (status: string | undefined) => {
    const key = STATUS_LABEL_KEY[status ?? ''] ?? 'statusPending';
    return labels[key];
  };

  return (
    <ModulePageShell
      title={labels.title}
      subtitle=""
      accentClassName="from-emerald-500 to-teal-600"
    >
      <LeaveRequestForm locale={appLocale} onSubmitted={() => { void load(); }} />

      <section className="rounded-2xl border border-app-border bg-white/40 shadow-glass-sm backdrop-blur-xl dark:bg-slate-900/40">
        {error && (
          <p role="alert" className="m-4 rounded-lg bg-red-50 px-3 py-2 text-xs font-medium text-red-700">
            {error}
          </p>
        )}

        {loading ? (
          <div className="flex items-center justify-center gap-2 px-6 py-10 text-sm text-slate-500">
            <Loader2 className="h-4 w-4 animate-spin" aria-hidden="true" />
            <span>{labels.statusPending}</span>
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
