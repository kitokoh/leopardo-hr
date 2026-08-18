'use client';

import { useEffect, useState, useSyncExternalStore } from 'react';
import { ApiError, apiFetch } from '@/lib/api-client';
import { getCopy, getPreferredLocale, type AppLocale } from '@/lib/i18n';
import { ModulePageShell } from '@/components/module-page-shell';

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

const emptySubscribe = () => () => {};

const STATUS_PENDING = 'pending';

export default function AbsencesPage() {
  const locale = useSyncExternalStore<AppLocale>(emptySubscribe, getPreferredLocale, () => 'fr');
  const copy = getCopy(locale);
  const [absences, setAbsences] = useState<AbsenceRecord[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);
  const [actingId, setActingId] = useState<number | null>(null);
  const [feedback, setFeedback] = useState<{ kind: 'success' | 'error'; text: string } | null>(null);

  // Demande en cours de refus (id) + motif saisi.
  const [rejecting, setRejecting] = useState<AbsenceRecord | null>(null);
  const [rejectReason, setRejectReason] = useState('');
  const [rejectError, setRejectError] = useState<string | null>(null);

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

        setError(err instanceof ApiError ? err.message : copy.absencesPage.loadError);
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

  async function refresh() {
    const response = await apiFetch('/absences');
    const payload = await response.json() as AbsencesPayload;
    setAbsences(Array.isArray(payload.data) ? payload.data : []);
  }

  async function approve(absence: AbsenceRecord) {
    setActingId(absence.id);
    setFeedback(null);
    try {
      await apiFetch(`/absences/${absence.id}/approve`, { method: 'POST' });
      setFeedback({ kind: 'success', text: copy.absencesPage.approveSuccess });
      await refresh();
    } catch (err) {
      setFeedback({ kind: 'error', text: err instanceof ApiError ? err.message : copy.absencesPage.actionError });
    } finally {
      setActingId(null);
    }
  }

  async function confirmReject() {
    if (!rejecting) {
      return;
    }
    const reason = rejectReason.trim();
    if (reason === '') {
      setRejectError(copy.absencesPage.reasonRequired);
      return;
    }

    setActingId(rejecting.id);
    setRejectError(null);
    setFeedback(null);
    try {
      await apiFetch(`/absences/${rejecting.id}/reject`, {
        method: 'POST',
        body: JSON.stringify({ rejected_reason: reason }),
      });
      setFeedback({ kind: 'success', text: copy.absencesPage.rejectSuccess });
      setRejecting(null);
      setRejectReason('');
      await refresh();
    } catch (err) {
      setFeedback({ kind: 'error', text: err instanceof ApiError ? err.message : copy.absencesPage.actionError });
    } finally {
      setActingId(null);
    }
  }

  return (
    <ModulePageShell
      title="Absences"
      subtitle="Lecture directe des demandes et statuts exposes par le backend RH pour verifier le bon dialogue client/API."
      accentClassName="bg-gradient-to-br from-info/10 via-white to-white"
    >
      {error ? (
        <div className="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
          {error}
        </div>
      ) : null}

      {feedback ? (
        <div className={`mb-4 rounded-2xl border px-4 py-3 text-sm ${
          feedback.kind === 'success'
            ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
            : 'border-red-200 bg-red-50 text-red-700'
        }`}>
          {feedback.text}
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
                </div>
                <div className="flex flex-wrap items-center gap-2">
                  <span className="text-[11px] font-bold uppercase tracking-wider rounded-full bg-slate-100 px-3 py-1 text-slate-600">
                    {absence.days_count ?? 0} j
                  </span>
                  <span className="text-[11px] font-bold uppercase tracking-wider rounded-full bg-info/15 px-3 py-1 text-info">
                    {absence.status ?? 'pending'}
                  </span>
                  {absence.status === STATUS_PENDING ? (
                    <div className="flex items-center gap-2">
                      <button
                        type="button"
                        disabled={actingId === absence.id}
                        onClick={() => void approve(absence)}
                        className="rounded-full bg-emerald-600 px-4 py-1.5 text-xs font-bold text-white transition hover:bg-emerald-700 disabled:opacity-50"
                      >
                        {copy.absencesPage.approve}
                      </button>
                      <button
                        type="button"
                        disabled={actingId === absence.id}
                        onClick={() => {
                          setRejecting(absence);
                          setRejectReason('');
                          setRejectError(null);
                        }}
                        className="rounded-full bg-red-600 px-4 py-1.5 text-xs font-bold text-white transition hover:bg-red-700 disabled:opacity-50"
                      >
                        {copy.absencesPage.reject}
                      </button>
                    </div>
                  ) : null}
                </div>
              </div>
            ))
          )}
        </div>
      </section>

      {rejecting ? (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4">
          <div className="w-full max-w-md rounded-3xl border border-app-border bg-white p-6 shadow-xl">
            <h3 className="text-base font-bold text-slate-950">{copy.absencesPage.rejectTitle}</h3>
            <p className="mt-1 text-xs text-slate-500">
              {rejecting.absence_type?.name ?? 'Absence'} — {rejecting.start_date ?? ''} au {rejecting.end_date ?? ''}
            </p>
            <textarea
              value={rejectReason}
              onChange={(event) => setRejectReason(event.target.value)}
              placeholder={copy.absencesPage.rejectReasonPlaceholder}
              rows={4}
              className="mt-4 w-full rounded-xl border border-app-border bg-slate-50 px-3 py-2 text-sm text-slate-900 outline-none focus:border-info"
            />
            {rejectError ? (
              <p className="mt-2 text-xs text-red-600">{rejectError}</p>
            ) : null}
            <div className="mt-4 flex justify-end gap-2">
              <button
                type="button"
                onClick={() => setRejecting(null)}
                className="rounded-full bg-slate-100 px-4 py-2 text-xs font-bold text-slate-700 transition hover:bg-slate-200"
              >
                {copy.absencesPage.cancel}
              </button>
              <button
                type="button"
                disabled={actingId === rejecting.id}
                onClick={() => void confirmReject()}
                className="rounded-full bg-red-600 px-4 py-2 text-xs font-bold text-white transition hover:bg-red-700 disabled:opacity-50"
              >
                {copy.absencesPage.confirmReject}
              </button>
            </div>
          </div>
        </div>
      ) : null}
    </ModulePageShell>
  );
}
