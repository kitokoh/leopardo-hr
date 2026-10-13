'use client';

/**
 * BC-15 Fuel — équipe & affectations aux shifts (FUEL-005, #5799).
 *
 * Surface web du pont RH ↔ FuelStation : un responsable affecte les employés
 * du tenant à un shift pour une date (`POST /fuel-station/shifts/{shift}/assignments`)
 * et retire une affectation (`DELETE /fuel-station/shift-assignments/{id}`).
 */
import { useCallback, useEffect, useState } from 'react';
import { UsersRound } from 'lucide-react';
import { ModulePageShell } from '@/components/module-page-shell';
import { apiFetch } from '@/lib/api-client';
import { getPreferredLocale } from '@/lib/i18n';
import { t } from '@/lib/i18n/locale-catalog';

type Shift = { id: number; name: string; start_time: string | null; end_time: string | null };
type Assignment = {
  id: number;
  shift_id: number;
  employee_id: number;
  assignment_date: string;
  status?: string;
  employee?: { id: number; first_name?: string | null; last_name?: string | null } | null;
};
type EmployeeOption = { id: number; label: string };

function todayIso(): string {
  return new Date().toISOString().slice(0, 10);
}

export default function FuelTeamPage() {
  const locale = getPreferredLocale();
  const [shifts, setShifts] = useState<Shift[]>([]);
  const [employees, setEmployees] = useState<EmployeeOption[]>([]);
  const [assignments, setAssignments] = useState<Assignment[]>([]);
  const [selectedShift, setSelectedShift] = useState('');
  const [assignmentDate, setAssignmentDate] = useState(todayIso());
  const [employeeId, setEmployeeId] = useState('');
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [working, setWorking] = useState(false);

  const loadShifts = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      const res = await apiFetch('/fuel-station/shifts?per_page=200');
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const payload = (await res.json()) as { data?: Shift[] };
      setShifts(Array.isArray(payload.data) ? payload.data : []);
    } catch {
      setError(t(locale, 'fuel.team.loadError', "Impossible de charger les shifts et l'équipe."));
    } finally {
      setLoading(false);
    }
  }, [locale]);

  useEffect(() => {
    void loadShifts();
  }, [loadShifts]);

  useEffect(() => {
    let cancelled = false;
    void (async () => {
      try {
        const res = await apiFetch('/employees?per_page=200');
        if (!res.ok) return;
        const payload = (await res.json()) as {
          data?: { id: number; first_name?: string | null; last_name?: string | null; name?: string | null }[];
        };
        if (cancelled) return;
        setEmployees(
          (Array.isArray(payload.data) ? payload.data : []).map((employee) => ({
            id: employee.id,
            label:
              [employee.first_name, employee.last_name].filter(Boolean).join(' ') ||
              employee.name ||
              String(employee.id),
          })),
        );
      } catch {
        // référentiel employés optionnel.
      }
    })();
    return () => {
      cancelled = true;
    };
  }, []);

  const loadAssignments = useCallback(async () => {
    if (selectedShift === '') {
      setAssignments([]);
      return;
    }
    try {
      const res = await apiFetch(`/fuel-station/shifts/${selectedShift}/assignments`);
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const payload = (await res.json()) as { data?: Assignment[] };
      setAssignments(Array.isArray(payload.data) ? payload.data : []);
    } catch {
      setError(t(locale, 'fuel.team.assignmentsError', 'Impossible de charger les affectations.'));
    }
  }, [locale, selectedShift]);

  useEffect(() => {
    void loadAssignments();
  }, [loadAssignments]);

  const assign = async () => {
    if (selectedShift === '' || employeeId === '') {
      setError(t(locale, 'fuel.team.formIncomplete', 'Sélectionnez un shift et un employé.'));
      return;
    }
    setWorking(true);
    setError('');
    try {
      const res = await apiFetch(`/fuel-station/shifts/${selectedShift}/assignments`, {
        method: 'POST',
        body: JSON.stringify({ employee_id: Number(employeeId), assignment_date: assignmentDate }),
      });
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      setEmployeeId('');
      await loadAssignments();
    } catch {
      setError(t(locale, 'fuel.team.assignFailed', "L'affectation a échoué."));
    } finally {
      setWorking(false);
    }
  };

  const unassign = async (assignment: Assignment) => {
    setWorking(true);
    setError('');
    try {
      const res = await apiFetch(`/fuel-station/shift-assignments/${assignment.id}`, { method: 'DELETE' });
      if (!res.ok && res.status !== 204) throw new Error(`HTTP ${res.status}`);
      await loadAssignments();
    } catch {
      setError(t(locale, 'fuel.team.removeFailed', 'Le retrait a échoué.'));
    } finally {
      setWorking(false);
    }
  };

  const employeeLabel = (assignment: Assignment): string => {
    const name = [assignment.employee?.first_name, assignment.employee?.last_name].filter(Boolean).join(' ');
    if (name !== '') return name;
    return employees.find((employee) => employee.id === assignment.employee_id)?.label ?? `#${assignment.employee_id}`;
  };

  const inputClass =
    'w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:border-fuchsia-400 focus:outline-none';

  return (
    <ModulePageShell
      icon={UsersRound}
      title={t(locale, 'fuel.team.title', 'Équipe & shifts')}
      description={t(locale, 'fuel.team.subtitle', 'Affectez vos employés aux shifts de vos stations.')}
    >
      {error ? (
        <p role="alert" className="rounded-lg bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">
          {error}
        </p>
      ) : null}

      <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 className="text-sm font-bold uppercase tracking-wide text-slate-500">
          {t(locale, 'fuel.team.assignTitle', 'Nouvelle affectation')}
        </h2>
        <div className="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
          <label className="block text-sm">
            <span className="mb-1 block font-semibold text-slate-700">{t(locale, 'fuel.team.shift', 'Shift')}</span>
            <select value={selectedShift} onChange={(e) => setSelectedShift(e.target.value)} className={inputClass} data-testid="team-shift">
              <option value="">{t(locale, 'fuel.team.selectPlaceholder', 'Sélectionner…')}</option>
              {shifts.map((shift) => (
                <option key={shift.id} value={shift.id}>
                  {shift.name} · {(shift.start_time ?? '').slice(0, 5)}–{(shift.end_time ?? '').slice(0, 5)}
                </option>
              ))}
            </select>
          </label>
          <label className="block text-sm">
            <span className="mb-1 block font-semibold text-slate-700">{t(locale, 'fuel.team.employee', 'Employé')}</span>
            <select value={employeeId} onChange={(e) => setEmployeeId(e.target.value)} className={inputClass} data-testid="team-employee">
              <option value="">{t(locale, 'fuel.team.selectPlaceholder', 'Sélectionner…')}</option>
              {employees.map((employee) => (
                <option key={employee.id} value={employee.id}>
                  {employee.label}
                </option>
              ))}
            </select>
          </label>
          <label className="block text-sm">
            <span className="mb-1 block font-semibold text-slate-700">{t(locale, 'fuel.team.date', 'Date')}</span>
            <input
              type="date"
              value={assignmentDate}
              onChange={(e) => setAssignmentDate(e.target.value)}
              className={inputClass}
            />
          </label>
          <div className="flex items-end">
            <button
              type="button"
              onClick={() => void assign()}
              disabled={working}
              data-testid="team-assign"
              className="rounded-xl bg-fuchsia-600 px-4 py-2 text-sm font-black text-white transition hover:bg-fuchsia-700 disabled:opacity-50"
            >
              {t(locale, 'fuel.team.assign', 'Affecter')}
            </button>
          </div>
        </div>
      </section>

      <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 className="text-sm font-bold uppercase tracking-wide text-slate-500">
          {t(locale, 'fuel.team.listTitle', 'Affectations du shift')}
        </h2>
        {selectedShift === '' ? (
          <p className="mt-3 text-sm text-slate-500">
            {t(locale, 'fuel.team.selectShiftHint', 'Sélectionnez un shift pour voir ses affectations.')}
          </p>
        ) : loading ? (
          <p className="mt-3 text-sm text-slate-500">{t(locale, 'fuel.common.loading', 'Chargement…')}</p>
        ) : assignments.length === 0 ? (
          <p className="mt-3 text-sm text-slate-500" data-testid="team-empty">
            {t(locale, 'fuel.team.empty', 'Aucune affectation pour ce shift.')}
          </p>
        ) : (
          <div className="mt-3 overflow-x-auto">
            <table className="w-full text-left text-sm">
              <thead>
                <tr className="border-b border-slate-200 text-xs font-bold uppercase tracking-wide text-slate-500">
                  <th className="px-3 py-2">{t(locale, 'fuel.team.employee', 'Employé')}</th>
                  <th className="px-3 py-2">{t(locale, 'fuel.team.date', 'Date')}</th>
                  <th className="px-3 py-2">{t(locale, 'fuel.team.status', 'Statut')}</th>
                  <th className="px-3 py-2" />
                </tr>
              </thead>
              <tbody>
                {assignments.map((assignment) => (
                  <tr key={assignment.id} className="border-b border-slate-100" data-testid={`team-row-${assignment.id}`}>
                    <td className="px-3 py-2 font-semibold text-slate-800">{employeeLabel(assignment)}</td>
                    <td className="px-3 py-2 text-slate-600">{assignment.assignment_date}</td>
                    <td className="px-3 py-2 text-slate-600">{assignment.status ?? '—'}</td>
                    <td className="px-3 py-2 text-right">
                      <button
                        type="button"
                        onClick={() => void unassign(assignment)}
                        disabled={working}
                        className="rounded-lg border border-red-200 px-3 py-1.5 text-xs font-bold text-red-700 transition hover:bg-red-50 disabled:opacity-50"
                      >
                        {t(locale, 'fuel.team.remove', 'Retirer')}
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </section>
    </ModulePageShell>
  );
}
