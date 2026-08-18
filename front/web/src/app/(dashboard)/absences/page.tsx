'use client';

import { useEffect, useState, useSyncExternalStore } from 'react';
import { ApiError, apiFetch } from '@/lib/api-client';
import { getCopy, getPreferredLocale, getStoredUser, type AppLocale } from '@/lib/i18n';
import { ModulePageShell } from '@/components/module-page-shell';
import { AbsenceRejectModal } from './_components/absence-reject-modal';

type AbsenceRecord = {
  id: number;
  start_date?: string;
  end_date?: string;
  status?: string;
  reason?: string | null;
  rejected_reason?: string | null;
  days_count?: number | string | null;
  absence_type?: {
    name?: string;
  } | null;
  employee?: {
    id?: number;
    first_name?: string;
    last_name?: string;
    email?: string;
  } | null;
};

type AbsencesPayload = {
  data?: AbsenceRecord[];
};

const emptySubscribe = () => () => {};

function isManagerRole(role: string | null | undefined): boolean {
  const value = (role ?? '').toLowerCase();
  return value === 'super_admin' || value === 'admin' || value === 'manager';
}

export default function AbsencesPage() {
  const locale = useSyncExternalStore<AppLocale>(emptySubscribe, getPreferredLocale, () => 'fr');
  const copy = getCopy(locale);
  const [absences, setAbsences] = useState<AbsenceRecord[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);
  const [rejectTarget, setRejectTarget] = useState<AbsenceRecord | null>(null);
  const [actionError, setActionError] = useState<string | null>(null);
  const [busyId, setBusyId] = useState<number | null>(null);
  const [manager, setManager] = useState(false);

  const labels = copy.absencesPage;
  const isPending = (a: AbsenceRecord) => (a.status ?? 'pending').toLowerCase() === 'pending';

  function employeeName(a: AbsenceRecord): string {
    const e = a.employee;
    if (e?.first_name || e?.last_name) {
      return `${e.first_name ?? ''} ${e.last_name ?? ''}`.trim();
    }
    return copy.smartAttendancePage.employeeFallback ?? 'Employé';
  }

  async function reload() {
    try {
      const response = await apiFetch('/absences');
      const payload = await response.json() as AbsencesPayload;
      setAbsences(Array.isArray(payload.data) ? payload.data : []);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : labels.loadError);
    }
  }

  useEffect(() => {
    let active = true;

    const stored = getStoredUser();
    if (stored) {
      setManager(isManagerRole(stored.role) || isManagerRole(stored.manager_role));
    }

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
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  async function handleApprove(absence: AbsenceRecord) {
    setBusyId(absence.id);
    setActionError(null);
    try {
      const response = await apiFetch(`/absences/${absence.id}/approve`, { method: 'POST' });
      if (!response.ok) {
        const err = await response.json().catch(() => null) as { message?: string } | null;
        throw new Error(err?.message ?? labels.actionError);
      }
      await reload();
    } catch (err) {
      setActionError(err instanceof Error ? err.message : labels.actionError);
    } finally {
      setBusyId(null);
    }
  }

  async function handleReject(absence: AbsenceRecord, reason: string) {
    setBusyId(absence.id);
    setActionError(null);
    try {
      const response = await apiFetch(`/absences/${absence.id}/reject`, {
        method: 'POST',
        body: JSON.stringify({ rejected_reason: reason }),
      });
      if (!response.ok) {
        const err = await response.json().catch(() => null) as { message?: string } | null;
        throw new Error(err?.message ?? labels.actionError);
      }
      setRejectTarget(null);
      await reload();
    } catch (err) {
      setActionError(err instanceof Error ? err.message : labels.actionError);
    } finally {
      setBusyId(null);
    }
  }

  return (
    <ModulePageShell
      title="Absences"
      subtitle="Lecture directe des demandes et statuts exposés par le backend RH pour vérifier le bon dialogue client/API."
      accentClassName="bg-gradient-to-br from-info/10 via-white to-white"
    >
      {error ? (
        <div className="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
          {error}
        </div>
      ) : null}

      {actionError ? (
        <div className="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700">
          {actionError}
        </div>
      ) : null}

      <section className="rounded-3xl border border-app-border bg-white shadow-sm">
        <div className="border-b border-app-border px-6 py-4">
          <h2 className="text-sm font-bold uppercase tracking-wider text-slate-800">Demandes visibles</h2>
        </div>
        <div className="divide-y divide-app-border">
          {loading ? (
            <div className="px-6 py-8 text-sm text-slate-500">Chargement des absences...</div>
          ) : absences.length === 0 ? (
            <div className="px-6 py-8 text-sm text-slate-500">Aucune absence retournee par l API pour ce compte.</div>
          ) : (
            absences.map((absence) => (
              <div key={absence.id} className="flex flex-col gap-3 px-6 py-5 md:flex-row md:items-center md:justify-between">
                <div>
                  <p className="text-sm font-bold text-slate-950">{absence.absence_type?.name ?? 'Absence'}</p>
                  <p className="text-xs text-slate-500">
                    {absence.start_date ?? 'Date inconnue'} au {absence.end_date ?? 'Date inconnue'}
                  </p>
                  {absence.reason ? (
                    <p className="mt-2 text-xs text-slate-500">{absence.reason}</p>
                  ) : null}
                  {absence.rejected_reason ? (
                    <p className="mt-2 text-xs text-red-600">{labels.rejectReasonLabel} : {absence.rejected_reason}</p>
                  ) : null}
                </div>
                <div className="flex flex-wrap items-center gap-2">
                  <div className="flex flex-wrap gap-2 text-[11px] font-bold uppercase tracking-wider">
                    <span className="rounded-full bg-slate-100 px-3 py-1 text-slate-600">{absence.days_count ?? 0} j</span>
                    <span className="rounded-full bg-info/15 px-3 py-1 text-info">{absence.status ?? 'pending'}</span>
                  </div>
                  {manager && isPending(absence) ? (
                    <div className="flex gap-2">
                      <button
                        type="button"
                        disabled={busyId === absence.id}
                        onClick={() => void handleApprove(absence)}
                        className="rounded-xl bg-emerald-600 px-3 py-1.5 text-xs font-bold text-white transition hover:bg-emerald-700 disabled:opacity-50"
                      >
                        {labels.approveAction}
                      </button>
                      <button
                        type="button"
                        disabled={busyId === absence.id}
                        onClick={() => setRejectTarget(absence)}
                        className="rounded-xl border border-red-200 bg-white px-3 py-1.5 text-xs font-bold text-red-600 transition hover:bg-red-50 disabled:opacity-50"
                      >
                        {labels.rejectAction}
                      </button>
                    </div>
                  ) : null}
                </div>
              </div>
            ))
          )}
        </div>
      </section>

      {rejectTarget ? (
        <AbsenceRejectModal
          employeeName={employeeName(rejectTarget)}
          onCancel={() => setRejectTarget(null)}
          onConfirm={(reason) => void handleReject(rejectTarget, reason)}
          loading={busyId === rejectTarget.id}
          labels={{
            rejectModalTitle: labels.rejectTitle,
            rejectModalBody: labels.rejectBody,
            rejectModalReasonLabel: labels.rejectReasonLabel,
            rejectModalReasonPlaceholder: labels.rejectReasonPlaceholder,
            rejectModalReasonRequired: labels.rejectReasonRequired,
            rejectModalConfirm: labels.rejectAction,
            rejectModalInProgress: labels.inProgress,
            cancel: labels.cancel,
          }}
        />
      ) : null}
    </ModulePageShell>
  );
}
