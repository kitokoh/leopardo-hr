'use client';

/**
 * #7909 — page Équipe du module Restaurant : affecter des employés RH du
 * tenant aux succursales (`POST /restaurant/branches/{id}/staff`), lister
 * les affectations (nom, rôle, date), modifier le rôle (PATCH) et retirer
 * avec confirmation (DELETE, soft delete côté API). Sélecteur de succursale
 * partagé `BranchSelect` (RESTO-904), pattern travel/staff pour la liste
 * des employés (`GET /employees`).
 */
import { useCallback, useEffect, useState } from 'react';
import { UsersRound } from 'lucide-react';
import { ModulePageShell } from '@/components/module-page-shell';
import { BranchSelect, useRestaurantBranches } from '@/components/restaurant/BranchSelect';
import { apiFetch } from '@/lib/api-client';
import { getPreferredLocale } from '@/lib/i18n';
import { t } from '@/lib/i18n/locale-catalog';

/** `RestaurantBranchStaffResource` (#7909) — employé embarqué (nom). */
type BranchStaffAssignment = {
  id: number;
  branch_id: number;
  employee_id: number;
  employee?: { id: number; first_name?: string | null; last_name?: string | null } | null;
  role: string | null;
  assigned_at: string | null;
  created_at: string | null;
};

type EmployeeOption = { id: number; label: string };

export default function RestaurantTeamPage() {
  const locale = getPreferredLocale();
  const { branches } = useRestaurantBranches();
  const [branchId, setBranchId] = useState<number | null>(null);
  const [assignments, setAssignments] = useState<BranchStaffAssignment[]>([]);
  const [employees, setEmployees] = useState<EmployeeOption[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [formEmployeeId, setFormEmployeeId] = useState('');
  const [formRole, setFormRole] = useState('');
  const [formError, setFormError] = useState('');
  const [saving, setSaving] = useState(false);
  const [editingId, setEditingId] = useState<number | null>(null);
  const [editingRole, setEditingRole] = useState('');
  const [removingId, setRemovingId] = useState<number | null>(null);

  // Première branche sélectionnée par défaut dès que la liste arrive.
  useEffect(() => {
    if (branchId === null && branches.length > 0) {
      setBranchId(branches[0].id);
    }
  }, [branches, branchId]);

  // Référentiel employés du tenant (pattern travel/staff/page.tsx).
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
        // référentiel optionnel : le tableau reste vide, l'erreur de la liste prime
      }
    })();
    return () => {
      cancelled = true;
    };
  }, []);

  const load = useCallback(async () => {
    if (branchId === null) {
      setAssignments([]);
      setLoading(false);
      return;
    }
    setLoading(true);
    setError('');
    try {
      const res = await apiFetch(`/restaurant/branches/${branchId}/staff?per_page=500`);
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const payload = (await res.json()) as { data?: BranchStaffAssignment[] };
      setAssignments(Array.isArray(payload.data) ? payload.data : []);
    } catch {
      setError(t(locale, 'restaurant.team.loadError', 'Impossible de charger les affectations.'));
    } finally {
      setLoading(false);
    }
  }, [branchId, locale]);

  useEffect(() => {
    void load();
  }, [load]);

  const employeeName = useCallback(
    (assignment: BranchStaffAssignment) => {
      const embedded = [assignment.employee?.first_name, assignment.employee?.last_name]
        .filter(Boolean)
        .join(' ');
      if (embedded) return embedded;
      return employees.find((e) => e.id === assignment.employee_id)?.label ?? `#${assignment.employee_id}`;
    },
    [employees],
  );

  const readApiError = async (res: Response): Promise<string | null> => {
    try {
      const payload = (await res.json()) as { message?: string };
      return typeof payload.message === 'string' && payload.message !== '' ? payload.message : null;
    } catch {
      return null;
    }
  };

  const submit = async (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setFormError('');
    if (branchId === null || !formEmployeeId) {
      setFormError(t(locale, 'restaurant.team.formIncomplete', 'Choisissez un employé.'));
      return;
    }
    setSaving(true);
    try {
      const res = await apiFetch(`/restaurant/branches/${branchId}/staff`, {
        method: 'POST',
        body: JSON.stringify({
          employee_id: Number(formEmployeeId),
          role: formRole.trim() === '' ? null : formRole.trim(),
        }),
      });
      if (!res.ok) {
        setFormError(
          (await readApiError(res)) ?? t(locale, 'restaurant.team.assignFailed', "L'affectation a échoué."),
        );
        return;
      }
      setFormEmployeeId('');
      setFormRole('');
      await load();
    } catch {
      setFormError(t(locale, 'restaurant.team.assignFailed', "L'affectation a échoué."));
    } finally {
      setSaving(false);
    }
  };

  const saveRole = async (assignment: BranchStaffAssignment) => {
    if (branchId === null) return;
    setError('');
    try {
      const res = await apiFetch(`/restaurant/branches/${branchId}/staff/${assignment.id}`, {
        method: 'PATCH',
        body: JSON.stringify({ role: editingRole.trim() === '' ? null : editingRole.trim() }),
      });
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      setEditingId(null);
      await load();
    } catch {
      setError(t(locale, 'restaurant.team.updateFailed', 'La mise à jour du rôle a échoué.'));
    }
  };

  const remove = async (assignment: BranchStaffAssignment) => {
    if (branchId === null) return;
    if (!window.confirm(t(locale, 'restaurant.team.confirmRemove', 'Retirer cet employé de la succursale ?'))) {
      return;
    }
    setRemovingId(assignment.id);
    setError('');
    try {
      const res = await apiFetch(`/restaurant/branches/${branchId}/staff/${assignment.id}`, {
        method: 'DELETE',
      });
      if (!res.ok && res.status !== 204) throw new Error(`HTTP ${res.status}`);
      await load();
    } catch {
      setError(t(locale, 'restaurant.team.removeFailed', 'Le retrait a échoué.'));
    } finally {
      setRemovingId(null);
    }
  };

  const inputClass =
    'w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:border-emerald-400 focus:outline-none';

  return (
    <ModulePageShell
      icon={UsersRound}
      title={t(locale, 'restaurant.team.title', 'Équipe')}
      description={t(
        locale,
        'restaurant.team.subtitle',
        'Affectez vos employés aux succursales du restaurant, gérez leur rôle et retirez-les en deux clics.',
      )}
    >
      {error ? (
        <p role="alert" className="rounded-lg bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">
          {error}
        </p>
      ) : null}

      <div className="flex flex-wrap items-center gap-3">
        <span className="text-sm font-semibold text-slate-700">{t(locale, 'restaurant.branch', 'Branche')}</span>
        <BranchSelect branches={branches} value={branchId} onChange={setBranchId} />
      </div>

      <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 className="text-sm font-bold uppercase tracking-wide text-slate-500">
          {t(locale, 'restaurant.team.assignTitle', 'Affecter un employé')}
        </h2>
        <form onSubmit={submit} className="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
          <label className="block text-sm">
            <span className="mb-1 block font-semibold text-slate-700">
              {t(locale, 'restaurant.team.employee', 'Employé')}
            </span>
            <select
              value={formEmployeeId}
              onChange={(e) => setFormEmployeeId(e.target.value)}
              className={inputClass}
              data-testid="team-employee"
            >
              <option value="">{t(locale, 'restaurant.team.selectPlaceholder', 'Sélectionner…')}</option>
              {employees.map((employee) => (
                <option key={employee.id} value={employee.id}>
                  {employee.label}
                </option>
              ))}
            </select>
          </label>
          <label className="block text-sm">
            <span className="mb-1 block font-semibold text-slate-700">
              {t(locale, 'restaurant.team.role', 'Rôle')}
            </span>
            <input
              type="text"
              value={formRole}
              onChange={(e) => setFormRole(e.target.value)}
              placeholder={t(locale, 'restaurant.team.rolePlaceholder', 'Serveur, cuisinier, gérant…')}
              maxLength={80}
              className={inputClass}
              data-testid="team-role"
            />
          </label>
          <div className="flex items-end">
            <button
              type="submit"
              disabled={saving}
              data-testid="team-assign"
              className="w-full rounded-xl bg-emerald-600 px-4 py-2 text-sm font-black text-white transition hover:bg-emerald-700 disabled:opacity-50"
            >
              {saving
                ? t(locale, 'restaurant.team.saving', 'Enregistrement…')
                : t(locale, 'restaurant.team.assign', 'Affecter')}
            </button>
          </div>
        </form>
        {formError ? (
          <p role="alert" className="mt-3 text-sm font-semibold text-red-600">
            {formError}
          </p>
        ) : null}
      </section>

      <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 className="text-sm font-bold uppercase tracking-wide text-slate-500">
          {t(locale, 'restaurant.team.title', 'Équipe')}
        </h2>
        {loading ? (
          <p className="mt-3 text-sm text-slate-500">{t(locale, 'restaurant.loading', 'Chargement…')}</p>
        ) : assignments.length === 0 ? (
          <p className="mt-3 text-sm text-slate-500" data-testid="team-empty">
            {t(locale, 'restaurant.team.empty', 'Aucun employé affecté à cette succursale.')}
          </p>
        ) : (
          <div className="mt-3 overflow-x-auto">
            <table className="w-full text-left text-sm">
              <thead>
                <tr className="border-b border-slate-200 text-xs font-bold uppercase tracking-wide text-slate-500">
                  <th className="px-3 py-2">{t(locale, 'restaurant.team.employee', 'Employé')}</th>
                  <th className="px-3 py-2">{t(locale, 'restaurant.team.role', 'Rôle')}</th>
                  <th className="px-3 py-2">{t(locale, 'restaurant.team.assignedAt', 'Affecté le')}</th>
                  <th className="px-3 py-2" />
                </tr>
              </thead>
              <tbody>
                {assignments.map((assignment) => (
                  <tr key={assignment.id} className="border-b border-slate-100" data-testid={`team-row-${assignment.id}`}>
                    <td className="px-3 py-2 font-semibold text-slate-800">{employeeName(assignment)}</td>
                    <td className="px-3 py-2">
                      {editingId === assignment.id ? (
                        <span className="flex items-center gap-2">
                          <input
                            type="text"
                            value={editingRole}
                            onChange={(e) => setEditingRole(e.target.value)}
                            maxLength={80}
                            className={inputClass}
                            data-testid={`team-role-input-${assignment.id}`}
                          />
                          <button
                            type="button"
                            onClick={() => void saveRole(assignment)}
                            data-testid={`team-role-save-${assignment.id}`}
                            className="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-bold text-white transition hover:bg-emerald-700"
                          >
                            {t(locale, 'restaurant.team.save', 'Enregistrer')}
                          </button>
                          <button
                            type="button"
                            onClick={() => setEditingId(null)}
                            className="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-bold text-slate-600 transition hover:bg-slate-50"
                          >
                            {t(locale, 'restaurant.team.cancel', 'Annuler')}
                          </button>
                        </span>
                      ) : (
                        <span className="flex items-center gap-2">
                          <span>{assignment.role ?? '—'}</span>
                          <button
                            type="button"
                            onClick={() => {
                              setEditingId(assignment.id);
                              setEditingRole(assignment.role ?? '');
                            }}
                            data-testid={`team-role-edit-${assignment.id}`}
                            className="rounded-lg border border-slate-200 px-2 py-1 text-xs font-bold text-slate-600 transition hover:bg-slate-50"
                          >
                            {t(locale, 'restaurant.team.editRole', 'Modifier le rôle')}
                          </button>
                        </span>
                      )}
                    </td>
                    <td className="px-3 py-2 text-slate-500">
                      {(assignment.assigned_at ?? assignment.created_at)?.slice(0, 10) ?? '—'}
                    </td>
                    <td className="px-3 py-2 text-right">
                      <button
                        type="button"
                        onClick={() => void remove(assignment)}
                        disabled={removingId === assignment.id}
                        data-testid={`team-remove-${assignment.id}`}
                        className="rounded-lg border border-red-200 px-3 py-1.5 text-xs font-bold text-red-700 transition hover:bg-red-50 disabled:opacity-50"
                      >
                        {t(locale, 'restaurant.team.remove', 'Retirer')}
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
