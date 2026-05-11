'use client';

import { useEffect, useState } from 'react';
import { ApiError, apiFetch } from '@/lib/api-client';
import { ModulePageShell } from '@/components/module-page-shell';

type AttendanceItem = {
  employee_id?: number;
  name?: string;
  matricule?: string;
  checked_in?: boolean;
  check_in_time?: string | null;
  check_out_time?: string | null;
  status?: string;
  hours_worked?: number | string | null;
};

type AttendancePayload = {
  data?: {
    mode?: string;
    items?: AttendanceItem[];
    item?: AttendanceItem;
    checked_in?: boolean;
    check_in_time?: string | null;
    check_out_time?: string | null;
    status?: string;
    hours_worked?: number | string | null;
  };
};

export default function AttendancePage() {
  const [mode, setMode] = useState<'collection' | 'single'>('single');
  const [items, setItems] = useState<AttendanceItem[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    let active = true;

    async function load() {
      try {
        const response = await apiFetch('/attendance/today');
        const payload = await response.json() as AttendancePayload;
        const data = payload.data;

        if (!active) {
          return;
        }

        if (data?.mode === 'collection' && Array.isArray(data.items)) {
          setMode('collection');
          setItems(data.items);
          return;
        }

        const singleItem = data?.item ?? data;
        setMode('single');
        setItems(singleItem ? [singleItem] : []);
      } catch (err) {
        if (!active) {
          return;
        }

        setError(err instanceof ApiError ? err.message : 'Impossible de charger le pointage.');
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
      title="Pointage du jour"
      subtitle="Etat temps reel depuis l’API RH. La page s’adapte aux comptes manager ou employe selon le payload backend."
      accentClassName="bg-gradient-to-br from-warning/10 via-white to-white"
    >
      {error ? (
        <div className="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
          {error}
        </div>
      ) : null}

      <section className="grid gap-4 md:grid-cols-3">
        <div className="rounded-2xl border border-app-border bg-white p-5 shadow-sm">
          <p className="text-xs font-bold uppercase tracking-widest text-slate-400">Mode</p>
          <p className="mt-3 text-3xl font-black text-slate-950">{loading ? '...' : mode === 'collection' ? 'Manager' : 'Employe'}</p>
        </div>
        <div className="rounded-2xl border border-app-border bg-white p-5 shadow-sm">
          <p className="text-xs font-bold uppercase tracking-widest text-slate-400">Lignes</p>
          <p className="mt-3 text-3xl font-black text-slate-950">{loading ? '...' : items.length}</p>
        </div>
        <div className="rounded-2xl border border-app-border bg-white p-5 shadow-sm">
          <p className="text-xs font-bold uppercase tracking-widest text-slate-400">Source</p>
          <p className="mt-3 text-lg font-bold text-slate-950">GET /attendance/today</p>
        </div>
      </section>

      <section className="overflow-hidden rounded-3xl border border-app-border bg-white shadow-sm">
        <div className="border-b border-app-border px-6 py-4">
          <h2 className="text-sm font-bold uppercase tracking-wider text-slate-800">Etat courant</h2>
        </div>
        <div className="divide-y divide-app-border">
          {loading ? (
            <div className="px-6 py-8 text-sm text-slate-500">Chargement du pointage...</div>
          ) : items.length === 0 ? (
            <div className="px-6 py-8 text-sm text-slate-500">Aucune donnee visible pour aujourd hui.</div>
          ) : (
            items.map((item, index) => (
              <div key={`${item.employee_id ?? 'self'}-${index}`} className="flex flex-col gap-3 px-6 py-5 md:flex-row md:items-center md:justify-between">
                <div>
                  <p className="text-sm font-bold text-slate-950">{item.name ?? 'Mon pointage'}</p>
                  <p className="text-xs text-slate-500">{item.matricule ?? 'Session personnelle'}</p>
                </div>
                <div className="flex flex-wrap gap-2 text-[11px] font-bold uppercase tracking-wider">
                  <span className="rounded-full bg-slate-100 px-3 py-1 text-slate-600">{item.check_in_time ?? 'Pas d entree'}</span>
                  <span className="rounded-full bg-slate-100 px-3 py-1 text-slate-600">{item.check_out_time ?? 'Pas de sortie'}</span>
                  <span className="rounded-full bg-warning/15 px-3 py-1 text-warning">{item.status ?? 'inconnu'}</span>
                </div>
              </div>
            ))
          )}
        </div>
      </section>
    </ModulePageShell>
  );
}
