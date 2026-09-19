'use client';

/**
 * TravelManager (BC-24, #7639) — page Équipe de l'espace gérant : affecter
 * un rôle travel (chauffeur, guichetier, contrôleur, chef de bureau) à un
 * employé RH du tenant, sur un bureau de vente OU un voyage, et révoquer en
 * deux clics. L'historique des affectations révoquées/expirées reste visible
 * (les lignes ne sont jamais supprimées, cf. TravelStaffAssignmentController).
 *
 * Consomme le pont RH backend (#7638) :
 *   - GET/POST /travel/staff-assignments
 *   - POST /travel/staff-assignments/{id}/revoke
 * plus les référentiels employés (`/employees`), bureaux (`/travel/offices`)
 * et voyages (`/travel/trips`) pour les sélecteurs.
 */
import { useCallback, useEffect, useMemo, useState } from 'react';
import { UsersRound } from 'lucide-react';
import { ModulePageShell } from '@/components/module-page-shell';
import { readApiError } from '@/components/travel/TravelCrudTable';
import { apiFetch } from '@/lib/api-client';
import { getPreferredLocale, type AppLocale } from '@/lib/i18n';
import { t } from '@/lib/i18n/locale-catalog';

/** `TravelStaffAssignmentResource` (#7638) — employee_id par valeur. */
type TravelStaffAssignment = {
  id: number;
  employee_id: number;
  role: 'driver' | 'agent' | 'controller' | 'office_manager' | string;
  office_id: number | null;
  trip_id: number | null;
  status: 'active' | 'revoked' | 'expired' | string;
  revoked_at: string | null;
  created_at: string | null;
};

type EmployeeOption = { id: number; label: string };
type OfficeOption = { id: number; name: string };
type TripOption = { id: number; code: string; departure_date: string };

type FormState = {
  employee_id: string;
  role: string;
  scope: 'office' | 'trip';
  office_id: string;
  trip_id: string;
};

const EMPTY_FORM: FormState = { employee_id: '', role: 'agent', scope: 'office', office_id: '', trip_id: '' };

const ROLES = ['driver', 'agent', 'controller', 'office_manager'] as const;

function roleLabel(locale: AppLocale, role: string): string {
  return t(locale, `travel.staffRole.${role}`, role);
}

function statusLabel(locale: AppLocale, status: string): string {
  return t(locale, `travel.staffStatus.${status}`, status);
}

export default function TravelStaffPage() {
  const locale = getPreferredLocale();
  const [assignments, setAssignments] = useState<TravelStaffAssignment[]>([]);
  const [employees, setEmployees] = useState<EmployeeOption[]>([]);
  const [offices, setOffices] = useState<OfficeOption[]>([]);
  const [trips, setTrips] = useState<TripOption[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [form, setForm] = useState<FormState>(EMPTY_FORM);
  const [saving, setSaving] = useState(false);
  const [formError, setFormError] = useState('');
  const [revokingId, setRevokingId] = useState<number | null>(null);
  const [showHistory, setShowHistory] = useState(false);

  const load = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      const [assignmentsRes, employeesRes, officesRes, tripsRes] = await Promise.all([
        apiFetch('/travel/staff-assignments?per_page=500'),
        apiFetch('/employees?per_page=200'),
        apiFetch('/travel/offices?per_page=500'),
        apiFetch('/travel/trips?per_page=500'),
      ]);
      if (!assignmentsRes.ok) throw new Error(`HTTP ${assignmentsRes.status}`);
      const assignmentsPayload = (await assignmentsRes.json()) as { data?: TravelStaffAssignment[] };
      setAssignments(Array.isArray(assignmentsPayload.data) ? assignmentsPayload.data : []);

      if (employeesRes.ok) {
        const payload = (await employeesRes.json()) as {
          data?: { id: number; first_name?: string | null; last_name?: string | null; name?: string | null }[];
        };
        setEmployees(
          (Array.isArray(payload.data) ? payload.data : []).map((employee) => ({
            id: employee.id,
            label:
              [employee.first_name, employee.last_name].filter(Boolean).join(' ') ||
              employee.name ||
              String(employee.id),
          })),
        );
      }
      if (officesRes.ok) {
        const payload = (await officesRes.json()) as { data?: OfficeOption[] };
        setOffices(Array.isArray(payload.data) ? payload.data : []);
      }
      if (tripsRes.ok) {
        const payload = (await tripsRes.json()) as { data?: TripOption[] };
        setTrips(Array.isArray(payload.data) ? payload.data : []);
      }
    } catch {
      setError(t(locale, 'travel.error.loadFailed', 'Impossible de charger les données.'));
    } finally {
      setLoading(false);
    }
  }, [locale]);

  useEffect(() => {
    void load();
  }, [load]);

  const employeeName = useCallback(
    (employeeId: number) => employees.find((e) => e.id === employeeId)?.label ?? `#${employeeId}`,
    [employees],
  );

  const scopeLabel = useCallback(
    (assignment: TravelStaffAssignment) => {
      if (assignment.office_id !== null) {
        const office = offices.find((o) => o.id === assignment.office_id);
        return `${t(locale, 'travel.staff.scopeOffice', 'Bureau')} — ${office?.name ?? `#${assignment.office_id}`}`;
      }
      if (assignment.trip_id !== null) {
        const trip = trips.find((tr) => tr.id === assignment.trip_id);
        return `${t(locale, 'travel.staff.scopeTrip', 'Voyage')} — ${trip ? `${trip.code} (${trip.departure_date})` : `#${assignment.trip_id}`}`;
      }
      return '—';
    },
    [locale, offices, trips],
  );

  const active = useMemo(() => assignments.filter((a) => a.status === 'active'), [assignments]);
  const history = useMemo(() => assignments.filter((a) => a.status !== 'active'), [assignments]);

  const submit = async (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setFormError('');
    if (!form.employee_id || (form.scope === 'office' ? !form.office_id : !form.trip_id)) {
      setFormError(t(locale, 'travel.staff.formIncomplete', 'Choisissez un employé, un rôle et un scope.'));
      return;
    }
    setSaving(true);
    try {
      const payload: Record<string, unknown> = {
        employee_id: Number(form.employee_id),
        role: form.role,
      };
      if (form.scope === 'office') {
        payload.office_id = Number(form.office_id);
      } else {
        payload.trip_id = Number(form.trip_id);
      }
      const res = await apiFetch('/travel/staff-assignments', {
        method: 'POST',
        body: JSON.stringify(payload),
      });
      if (!res.ok) {
        setFormError(
          (await readApiError(res)) ?? t(locale, 'travel.staff.assignFailed', "L'affectation a échoué."),
        );
        return;
      }
      setForm(EMPTY_FORM);
      await load();
    } catch {
      setFormError(t(locale, 'travel.staff.assignFailed', "L'affectation a échoué."));
    } finally {
      setSaving(false);
    }
  };

  const revoke = async (assignment: TravelStaffAssignment) => {
    setRevokingId(assignment.id);
    setError('');
    try {
      const res = await apiFetch(`/travel/staff-assignments/${assignment.id}/revoke`, { method: 'POST' });
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      await load();
    } catch {
      setError(t(locale, 'travel.staff.revokeFailed', 'La révocation a échoué.'));
    } finally {
      setRevokingId(null);
    }
  };

  const inputClass =
    'w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:border-emerald-400 focus:outline-none';

  return (
    <ModulePageShell
      icon={UsersRound}
      title={t(locale, 'travel.staff.title', 'Équipe')}
      description={t(
        locale,
        'travel.staff.subtitle',
        'Affectez vos employés aux rôles travel (chauffeur, guichetier, contrôleur, chef de bureau) sur un bureau ou un voyage.',
      )}
    >
      {error ? (
        <p role="alert" className="rounded-lg bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">
          {error}
        </p>
      ) : null}

      <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 className="text-sm font-bold uppercase tracking-wide text-slate-500">
          {t(locale, 'travel.staff.assignTitle', 'Nommer un membre de l’équipe')}
        </h2>
        <form onSubmit={submit} className="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
          <label className="block text-sm">
            <span className="mb-1 block font-semibold text-slate-700">
              {t(locale, 'travel.staff.employee', 'Employé')}
            </span>
            <select
              value={form.employee_id}
              onChange={(e) => setForm((f) => ({ ...f, employee_id: e.target.value }))}
              className={inputClass}
              data-testid="staff-employee"
            >
              <option value="">{t(locale, 'travel.common.selectPlaceholder', 'Sélectionner…')}</option>
              {employees.map((employee) => (
                <option key={employee.id} value={employee.id}>
                  {employee.label}
                </option>
              ))}
            </select>
          </label>
          <label className="block text-sm">
            <span className="mb-1 block font-semibold text-slate-700">
              {t(locale, 'travel.staff.role', 'Rôle')}
            </span>
            <select
              value={form.role}
              onChange={(e) => setForm((f) => ({ ...f, role: e.target.value }))}
              className={inputClass}
              data-testid="staff-role"
            >
              {ROLES.map((role) => (
                <option key={role} value={role}>
                  {roleLabel(locale, role)}
                </option>
              ))}
            </select>
          </label>
          <label className="block text-sm">
            <span className="mb-1 block font-semibold text-slate-700">
              {t(locale, 'travel.staff.scope', 'Scope')}
            </span>
            <select
              value={form.scope}
              onChange={(e) =>
                setForm((f) => ({ ...f, scope: e.target.value === 'trip' ? 'trip' : 'office' }))
              }
              className={inputClass}
              data-testid="staff-scope"
            >
              <option value="office">{t(locale, 'travel.staff.scopeOffice', 'Bureau')}</option>
              <option value="trip">{t(locale, 'travel.staff.scopeTrip', 'Voyage')}</option>
            </select>
          </label>
          <label className="block text-sm">
            <span className="mb-1 block font-semibold text-slate-700">
              {form.scope === 'office'
                ? t(locale, 'travel.staff.scopeOffice', 'Bureau')
                : t(locale, 'travel.staff.scopeTrip', 'Voyage')}
            </span>
            {form.scope === 'office' ? (
              <select
                value={form.office_id}
                onChange={(e) => setForm((f) => ({ ...f, office_id: e.target.value }))}
                className={inputClass}
                data-testid="staff-office"
              >
                <option value="">{t(locale, 'travel.common.selectPlaceholder', 'Sélectionner…')}</option>
                {offices.map((office) => (
                  <option key={office.id} value={office.id}>
                    {office.name}
                  </option>
                ))}
              </select>
            ) : (
              <select
                value={form.trip_id}
                onChange={(e) => setForm((f) => ({ ...f, trip_id: e.target.value }))}
                className={inputClass}
                data-testid="staff-trip"
              >
                <option value="">{t(locale, 'travel.common.selectPlaceholder', 'Sélectionner…')}</option>
                {trips.map((trip) => (
                  <option key={trip.id} value={trip.id}>
                    {trip.code} ({trip.departure_date})
                  </option>
                ))}
              </select>
            )}
          </label>
          <div className="flex items-end">
            <button
              type="submit"
              disabled={saving}
              data-testid="staff-assign"
              className="w-full rounded-xl bg-emerald-600 px-4 py-2 text-sm font-black text-white transition hover:bg-emerald-700 disabled:opacity-50"
            >
              {saving
                ? t(locale, 'travel.common.saving', 'Enregistrement…')
                : t(locale, 'travel.staff.assign', 'Nommer')}
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
          {t(locale, 'travel.staff.activeTitle', 'Affectations actives')}
        </h2>
        {loading ? (
          <p className="mt-3 text-sm text-slate-500">{t(locale, 'travel.loading', 'Chargement…')}</p>
        ) : active.length === 0 ? (
          <p className="mt-3 text-sm text-slate-500" data-testid="staff-empty">
            {t(locale, 'travel.staff.empty', 'Aucune affectation active.')}
          </p>
        ) : (
          <div className="mt-3 overflow-x-auto">
            <table className="w-full text-left text-sm">
              <thead>
                <tr className="border-b border-slate-200 text-xs font-bold uppercase tracking-wide text-slate-500">
                  <th className="px-3 py-2">{t(locale, 'travel.staff.employee', 'Employé')}</th>
                  <th className="px-3 py-2">{t(locale, 'travel.staff.role', 'Rôle')}</th>
                  <th className="px-3 py-2">{t(locale, 'travel.staff.scope', 'Scope')}</th>
                  <th className="px-3 py-2">{t(locale, 'travel.common.createdAt', 'Créé le')}</th>
                  <th className="px-3 py-2" />
                </tr>
              </thead>
              <tbody>
                {active.map((assignment) => (
                  <tr key={assignment.id} className="border-b border-slate-100" data-testid={`staff-row-${assignment.id}`}>
                    <td className="px-3 py-2 font-semibold text-slate-800">{employeeName(assignment.employee_id)}</td>
                    <td className="px-3 py-2">{roleLabel(locale, assignment.role)}</td>
                    <td className="px-3 py-2">{scopeLabel(assignment)}</td>
                    <td className="px-3 py-2 text-slate-500">
                      {assignment.created_at ? assignment.created_at.slice(0, 10) : '—'}
                    </td>
                    <td className="px-3 py-2 text-right">
                      <button
                        type="button"
                        onClick={() => void revoke(assignment)}
                        disabled={revokingId === assignment.id}
                        data-testid={`staff-revoke-${assignment.id}`}
                        className="rounded-lg border border-red-200 px-3 py-1.5 text-xs font-bold text-red-700 transition hover:bg-red-50 disabled:opacity-50"
                      >
                        {t(locale, 'travel.staff.revoke', 'Révoquer')}
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </section>

      <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <button
          type="button"
          onClick={() => setShowHistory((v) => !v)}
          aria-expanded={showHistory}
          data-testid="staff-history-toggle"
          className="text-sm font-bold uppercase tracking-wide text-slate-500 hover:text-slate-800"
        >
          {t(locale, 'travel.staff.historyTitle', 'Historique des affectations')} ({history.length})
        </button>
        {showHistory ? (
          history.length === 0 ? (
            <p className="mt-3 text-sm text-slate-500">
              {t(locale, 'travel.staff.historyEmpty', 'Aucune affectation révoquée ou expirée.')}
            </p>
          ) : (
            <div className="mt-3 overflow-x-auto">
              <table className="w-full text-left text-sm">
                <thead>
                  <tr className="border-b border-slate-200 text-xs font-bold uppercase tracking-wide text-slate-500">
                    <th className="px-3 py-2">{t(locale, 'travel.staff.employee', 'Employé')}</th>
                    <th className="px-3 py-2">{t(locale, 'travel.staff.role', 'Rôle')}</th>
                    <th className="px-3 py-2">{t(locale, 'travel.staff.scope', 'Scope')}</th>
                    <th className="px-3 py-2">{t(locale, 'travel.common.status', 'Statut')}</th>
                    <th className="px-3 py-2">{t(locale, 'travel.staff.revokedAt', 'Révoquée le')}</th>
                  </tr>
                </thead>
                <tbody>
                  {history.map((assignment) => (
                    <tr key={assignment.id} className="border-b border-slate-100">
                      <td className="px-3 py-2 font-semibold text-slate-800">{employeeName(assignment.employee_id)}</td>
                      <td className="px-3 py-2">{roleLabel(locale, assignment.role)}</td>
                      <td className="px-3 py-2">{scopeLabel(assignment)}</td>
                      <td className="px-3 py-2">{statusLabel(locale, assignment.status)}</td>
                      <td className="px-3 py-2 text-slate-500">
                        {assignment.revoked_at ? assignment.revoked_at.slice(0, 10) : '—'}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )
        ) : null}
      </section>
    </ModulePageShell>
  );
}
