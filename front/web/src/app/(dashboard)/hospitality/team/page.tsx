'use client';

/**
 * HOSP-007 (#7949) — equipe par etablissement (pattern restaurant/team #7909).
 *
 * Affecte des employes RH du tenant a un etablissement via
 * `/hospitality/properties/{id}/staff`, avec modification du role (PATCH) et
 * retrait (DELETE). Le referentiel employes vient de `/employees`, comme sur
 * les autres verticales — une deuxieme source de verite pour les personnes
 * ferait diverger les deux listes.
 */
import { useCallback, useEffect, useState } from 'react';
import { UsersRound, Loader2, AlertTriangle, Plus, Trash2 } from 'lucide-react';
import { ModulePageShell } from '@/components/module-page-shell';
import { apiFetch } from '@/lib/api-client';
import { getPreferredLocale } from '@/lib/i18n';
import { t } from '@/lib/i18n/locale-catalog';

type Property = { id: number; name: string };
type Assignment = {
  id: number;
  property_id: number;
  employee_id: number;
  employee?: { id: number; first_name?: string | null; last_name?: string | null } | null;
  role: string | null;
  assigned_at: string | null;
};
type EmployeeOption = { id: number; label: string };

export default function HospitalityTeamPage() {
  const locale = getPreferredLocale();
  const [properties, setProperties] = useState<Property[]>([]);
  const [propertyId, setPropertyId] = useState<number | null>(null);
  const [assignments, setAssignments] = useState<Assignment[]>([]);
  const [employees, setEmployees] = useState<EmployeeOption[]>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');
  const [actionError, setActionError] = useState('');
  const [saving, setSaving] = useState(false);
  const [busyId, setBusyId] = useState<number | null>(null);
  const [formEmployeeId, setFormEmployeeId] = useState('');
  const [formRole, setFormRole] = useState('');
  const [editingId, setEditingId] = useState<number | null>(null);
  const [editingRole, setEditingRole] = useState('');

  const loadProperties = useCallback(async () => {
    try {
      const res = await apiFetch('/hospitality/properties');
      const payload = res.ok ? ((await res.json()) as { data?: Property[] }) : {};
      const list = Array.isArray(payload.data) ? payload.data : [];
      setProperties(list);
      if (list.length > 0) setPropertyId((current) => current ?? list[0].id);
    } catch {
      setError(t(locale, 'hospitality.properties.loadError', 'Impossible de charger les établissements.'));
    }
  }, [locale]);

  // Referentiel employes du tenant (meme source que restaurant/team).
  useEffect(() => {
    let cancelled = false;
    void (async () => {
      try {
        const res = await apiFetch('/employees?per_page=200');
        if (!res.ok) return;
        const payload = (await res.json()) as {
          data?: { id: number; first_name?: string | null; last_name?: string | null }[];
        };
        const list = Array.isArray(payload.data) ? payload.data : [];
        if (!cancelled) {
          setEmployees(
            list.map((e) => ({
              id: e.id,
              label: [e.first_name, e.last_name].filter(Boolean).join(' ') || `#${e.id}`,
            })),
          );
        }
      } catch {
        // Liste vide : le formulaire reste utilisable, l'utilisateur peut
        // saisir l'identifiant. Pas d'ecran d'erreur bloquant pour cela.
      }
    })();
    return () => {
      cancelled = true;
    };
  }, []);

  const loadAssignments = useCallback(async () => {
    if (propertyId === null) return;
    setLoading(true);
    setError('');
    try {
      const res = await apiFetch(`/hospitality/properties/${propertyId}/staff`);
      const payload = res.ok ? ((await res.json()) as { data?: Assignment[] }) : {};
      setAssignments(Array.isArray(payload.data) ? payload.data : []);
    } catch {
      setError(t(locale, 'hospitality.team.loadError', 'Impossible de charger l’équipe.'));
    } finally {
      setLoading(false);
    }
  }, [locale, propertyId]);

  useEffect(() => {
    void loadProperties();
  }, [loadProperties]);

  useEffect(() => {
    void loadAssignments();
  }, [loadAssignments]);

  async function assign(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (propertyId === null) return;
    setActionError('');
    if (formEmployeeId.trim() === '') {
      setActionError(t(locale, 'hospitality.team.employeeRequired', 'Sélectionnez un employé.'));
      return;
    }
    setSaving(true);
    try {
      const res = await apiFetch(`/hospitality/properties/${propertyId}/staff`, {
        method: 'POST',
        body: JSON.stringify({
          employee_id: Number.parseInt(formEmployeeId, 10),
          role: formRole.trim() === '' ? null : formRole.trim(),
        }),
      });
      if (!res.ok) {
        setActionError(
          t(
            locale,
            'hospitality.team.assignError',
            'Affectation refusée : cet employé est peut-être déjà rattaché à cet établissement.',
          ),
        );
        return;
      }
      setFormEmployeeId('');
      setFormRole('');
      await loadAssignments();
    } finally {
      setSaving(false);
    }
  }

  async function updateRole(assignment: Assignment) {
    if (propertyId === null) return;
    setActionError('');
    setBusyId(assignment.id);
    try {
      const res = await apiFetch(`/hospitality/properties/${propertyId}/staff/${assignment.id}`, {
        method: 'PATCH',
        body: JSON.stringify({ role: editingRole.trim() === '' ? null : editingRole.trim() }),
      });
      if (!res.ok) {
        setActionError(t(locale, 'hospitality.team.updateError', 'Modification refusée par l’API.'));
        return;
      }
      setEditingId(null);
      setEditingRole('');
      await loadAssignments();
    } finally {
      setBusyId(null);
    }
  }

  async function remove(assignment: Assignment) {
    if (propertyId === null) return;
    const confirmed = window.confirm(
      t(locale, 'hospitality.team.confirmRemove', 'Retirer cet employé de l’établissement ?'),
    );
    if (!confirmed) return;
    setActionError('');
    setBusyId(assignment.id);
    try {
      const res = await apiFetch(`/hospitality/properties/${propertyId}/staff/${assignment.id}`, {
        method: 'DELETE',
      });
      if (!res.ok) {
        setActionError(t(locale, 'hospitality.team.removeError', 'Retrait refusé par l’API.'));
        return;
      }
      await loadAssignments();
    } finally {
      setBusyId(null);
    }
  }

  function employeeLabel(assignment: Assignment): string {
    const e = assignment.employee;
    if (!e) return `#${assignment.employee_id}`;
    return [e.first_name, e.last_name].filter(Boolean).join(' ') || `#${assignment.employee_id}`;
  }

  return (
    <ModulePageShell
      title={t(locale, 'hospitality.team.title', 'Équipe')}
      subtitle={t(
        locale,
        'hospitality.team.subtitle',
        'Employés affectés à chaque établissement.',
      )}
      icon={UsersRound}
    >
      {error !== '' ? (
        <div role="alert" className="flex items-center gap-2 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
          <AlertTriangle className="h-4 w-4" />
          {error}
        </div>
      ) : null}

      <label className="flex flex-col gap-1 text-sm text-neutral-700">
        {t(locale, 'hospitality.common.property', 'Établissement')}
        <select
          value={propertyId ?? ''}
          onChange={(e) => setPropertyId(e.target.value === '' ? null : Number(e.target.value))}
          className="max-w-sm rounded-lg border border-neutral-300 px-3 py-2 text-sm"
        >
          {properties.length === 0 ? (
            <option value="">{t(locale, 'hospitality.common.noProperty', 'Aucun établissement')}</option>
          ) : null}
          {properties.map((p) => (
            <option key={p.id} value={p.id}>
              {p.name}
            </option>
          ))}
        </select>
      </label>

      {actionError !== '' ? (
        <div role="alert" className="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
          {actionError}
        </div>
      ) : null}

      {propertyId !== null ? (
        <form onSubmit={assign} className="rounded-xl border border-neutral-200 bg-white p-4">
          <p className="mb-3 text-sm font-medium text-neutral-900">
            {t(locale, 'hospitality.team.assign', 'Affecter un employé')}
          </p>
          <div className="grid gap-3 sm:grid-cols-3">
            <select
              value={formEmployeeId}
              onChange={(e) => setFormEmployeeId(e.target.value)}
              className="rounded-lg border border-neutral-300 px-3 py-2 text-sm"
              required
            >
              <option value="">{t(locale, 'hospitality.team.selectEmployee', 'Choisir un employé')}</option>
              {employees.map((emp) => (
                <option key={emp.id} value={emp.id}>
                  {emp.label}
                </option>
              ))}
            </select>
            <input
              value={formRole}
              onChange={(e) => setFormRole(e.target.value)}
              placeholder={t(locale, 'hospitality.team.field.role', 'Rôle (ex. Réception)')}
              className="rounded-lg border border-neutral-300 px-3 py-2 text-sm"
            />
            <button
              type="submit"
              disabled={saving}
              className="inline-flex items-center justify-center gap-2 rounded-lg bg-emerald-600 px-3 py-2 text-sm font-medium text-white disabled:opacity-60"
            >
              {saving ? <Loader2 className="h-4 w-4 animate-spin" /> : <Plus className="h-4 w-4" />}
              {t(locale, 'hospitality.team.submit', 'Affecter')}
            </button>
          </div>
        </form>
      ) : null}

      {loading ? (
        <div className="flex items-center gap-2 rounded-xl border border-neutral-200 bg-white p-4 text-sm text-neutral-600">
          <Loader2 className="h-4 w-4 animate-spin" />
          {t(locale, 'common.loading', 'Chargement…')}
        </div>
      ) : null}

      {propertyId !== null && !loading && assignments.length === 0 ? (
        <div className="rounded-xl border border-neutral-200 bg-white p-6 text-sm text-neutral-600">
          {t(locale, 'hospitality.team.empty', 'Aucun employé affecté à cet établissement.')}
        </div>
      ) : null}

      {assignments.length > 0 ? (
        <div className="overflow-hidden rounded-xl border border-neutral-200 bg-white">
          <table className="w-full text-sm">
            <thead className="bg-neutral-50 text-left text-neutral-600">
              <tr>
                <th className="px-4 py-2 font-medium">{t(locale, 'hospitality.team.col.employee', 'Employé')}</th>
                <th className="px-4 py-2 font-medium">{t(locale, 'hospitality.team.col.role', 'Rôle')}</th>
                <th className="px-4 py-2" />
              </tr>
            </thead>
            <tbody>
              {assignments.map((assignment) => (
                <tr key={assignment.id} className="border-t border-neutral-100">
                  <td className="px-4 py-3 font-medium text-neutral-900">{employeeLabel(assignment)}</td>
                  <td className="px-4 py-3">
                    {editingId === assignment.id ? (
                      <input
                        value={editingRole}
                        onChange={(e) => setEditingRole(e.target.value)}
                        className="w-full rounded-lg border border-neutral-300 px-2 py-1 text-sm"
                      />
                    ) : (
                      <span className="text-neutral-700">{assignment.role ?? '—'}</span>
                    )}
                  </td>
                  <td className="px-4 py-3">
                    <div className="flex justify-end gap-2">
                      {editingId === assignment.id ? (
                        <>
                          <button
                            type="button"
                            onClick={() => void updateRole(assignment)}
                            disabled={busyId === assignment.id}
                            className="rounded-lg border border-emerald-300 px-2 py-1 text-xs text-emerald-700 disabled:opacity-50"
                          >
                            {t(locale, 'common.save', 'Enregistrer')}
                          </button>
                          <button
                            type="button"
                            onClick={() => {
                              setEditingId(null);
                              setEditingRole('');
                            }}
                            className="rounded-lg border border-neutral-300 px-2 py-1 text-xs text-neutral-700"
                          >
                            {t(locale, 'common.cancel', 'Annuler')}
                          </button>
                        </>
                      ) : (
                        <>
                          <button
                            type="button"
                            onClick={() => {
                              setEditingId(assignment.id);
                              setEditingRole(assignment.role ?? '');
                            }}
                            className="rounded-lg border border-neutral-300 px-2 py-1 text-xs text-neutral-700"
                          >
                            {t(locale, 'common.edit', 'Modifier')}
                          </button>
                          <button
                            type="button"
                            onClick={() => void remove(assignment)}
                            disabled={busyId === assignment.id}
                            className="inline-flex items-center gap-1 rounded-lg border border-red-200 px-2 py-1 text-xs text-red-700 disabled:opacity-50"
                          >
                            <Trash2 className="h-3 w-3" />
                            {t(locale, 'hospitality.team.remove', 'Retirer')}
                          </button>
                        </>
                      )}
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      ) : null}
    </ModulePageShell>
  );
}
