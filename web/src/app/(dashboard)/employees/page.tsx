'use client';

import { useEffect, useState } from 'react';
import { ApiError, apiFetch } from '@/lib/api-client';
import { ModulePageShell } from '@/components/module-page-shell';

type EmployeeRecord = {
  id: number;
  first_name?: string;
  last_name?: string;
  email?: string;
  role?: string;
  status?: string;
  matricule?: string;
};

type EmployeesPayload = {
  data?: EmployeeRecord[];
  meta?: {
    total?: number;
  };
};

export default function EmployeesPage() {
  const [employees, setEmployees] = useState<EmployeeRecord[]>([]);
  const [total, setTotal] = useState(0);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    let active = true;

    async function load() {
      try {
        const response = await apiFetch('/employees?per_page=12');
        const payload = await response.json() as EmployeesPayload;

        if (!active) {
          return;
        }

        setEmployees(Array.isArray(payload.data) ? payload.data : []);
        setTotal(payload.meta?.total ?? (Array.isArray(payload.data) ? payload.data.length : 0));
      } catch (err) {
        if (!active) {
          return;
        }

        setError(err instanceof ApiError ? err.message : 'Impossible de charger les employes.');
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
      title="Equipe"
      subtitle="Vue manager branchee a l’API RH: liste des collaborateurs, statut et points d’entree essentiels."
      accentClassName="bg-gradient-to-br from-rh-light via-white to-white"
    >
      {error ? (
        <div className="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
          {error}
        </div>
      ) : null}

      <section className="grid gap-4 md:grid-cols-3">
        <div className="rounded-2xl border border-app-border bg-white p-5 shadow-sm">
          <p className="text-xs font-bold uppercase tracking-widest text-slate-400">Total equipe</p>
          <p className="mt-3 text-4xl font-black text-slate-950">{loading ? '...' : total}</p>
        </div>
        <div className="rounded-2xl border border-app-border bg-white p-5 shadow-sm">
          <p className="text-xs font-bold uppercase tracking-widest text-slate-400">Source</p>
          <p className="mt-3 text-lg font-bold text-slate-950">GET /employees</p>
        </div>
        <div className="rounded-2xl border border-app-border bg-white p-5 shadow-sm">
          <p className="text-xs font-bold uppercase tracking-widest text-slate-400">Etat</p>
          <p className="mt-3 text-lg font-bold text-slate-950">{loading ? 'Chargement' : 'Connecte a l API'}</p>
        </div>
      </section>

      <section className="overflow-hidden rounded-3xl border border-app-border bg-white shadow-sm">
        <div className="border-b border-app-border px-6 py-4">
          <h2 className="text-sm font-bold uppercase tracking-wider text-slate-800">Collaborateurs recents</h2>
        </div>
        <div className="divide-y divide-app-border">
          {loading ? (
            <div className="px-6 py-8 text-sm text-slate-500">Chargement de la liste equipe...</div>
          ) : employees.length === 0 ? (
            <div className="px-6 py-8 text-sm text-slate-500">Aucun employe visible pour ce compte.</div>
          ) : (
            employees.map((employee) => {
              const fullName = `${employee.first_name ?? ''} ${employee.last_name ?? ''}`.trim() || employee.email || `Employe #${employee.id}`;

              return (
                <div key={employee.id} className="flex flex-col gap-3 px-6 py-5 md:flex-row md:items-center md:justify-between">
                  <div>
                    <p className="text-sm font-bold text-slate-950">{fullName}</p>
                    <p className="text-xs text-slate-500">{employee.email ?? 'Email indisponible'}</p>
                  </div>
                  <div className="flex flex-wrap gap-2 text-[11px] font-bold uppercase tracking-wider">
                    <span className="rounded-full bg-slate-100 px-3 py-1 text-slate-600">{employee.matricule ?? 'Sans matricule'}</span>
                    <span className="rounded-full bg-rh-light px-3 py-1 text-rh-dark">{employee.role ?? 'employee'}</span>
                    <span className="rounded-full bg-slate-900 px-3 py-1 text-white">{employee.status ?? 'active'}</span>
                  </div>
                </div>
              );
            })
          )}
        </div>
      </section>
    </ModulePageShell>
  );
}
