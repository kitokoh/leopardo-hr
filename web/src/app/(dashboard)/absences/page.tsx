'use client';

import { useEffect, useState } from 'react';
import { ApiError, apiFetch } from '@/lib/api-client';
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

export default function AbsencesPage() {
  const [absences, setAbsences] = useState<AbsenceRecord[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);

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

        setError(err instanceof ApiError ? err.message : 'Impossible de charger les absences.');
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
  }, []);

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
                <div className="flex flex-wrap gap-2 text-[11px] font-bold uppercase tracking-wider">
                  <span className="rounded-full bg-slate-100 px-3 py-1 text-slate-600">{absence.days_count ?? 0} j</span>
                  <span className="rounded-full bg-info/15 px-3 py-1 text-info">{absence.status ?? 'pending'}</span>
                </div>
              </div>
            ))
          )}
        </div>
      </section>
    </ModulePageShell>
  );
}
