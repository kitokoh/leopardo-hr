'use client';

import { useCallback, useEffect, useState } from 'react';
import { useSyncExternalStore } from 'react';
import { Plus, X } from 'lucide-react';
import { ApiError, apiFetch } from '@/lib/api-client';
import { ModulePageShell } from '@/components/module-page-shell';
import { getPreferredLocale, type AppLocale } from '@/lib/i18n';
import { t as i18nT } from '@/lib/i18n/locale-catalog';

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

type DepartmentRecord = {
  id: number;
  name: string;
};

type DepartmentsPayload = {
  data?: DepartmentRecord[];
};

// Erreur API exploitable : le backend fournit un message deja localise, on le
// prefere toujours a un repli local.
function apiErrorMessage(error: unknown, fallback: string): string {
  if (error instanceof ApiError) {
    return error.message || fallback;
  }
  return error instanceof Error && error.message ? error.message : fallback;
}

/**
 * Page Équipe — liste + création.
 *
 * Audit onboarding 2026-09-13 : les étapes obligatoires `first_department` et
 * `first_employee` de l'assistant de démarrage exigeaient une donnée réelle
 * (garde backend `StepCompletionGuard`) mais AUCUN écran du web client ne
 * permettait de la créer — `go_live_ready` était donc inatteignable pour un
 * essai. Cette page expose désormais les deux créations, et confirme l'étape
 * correspondante auprès de `/onboarding-setup/{stepKey}/complete` dès que la
 * donnée existe (best-effort : la garde backend reste seule juge).
 */
export default function EmployeesPage() {
  const locale = useSyncExternalStore<AppLocale>(() => () => {}, getPreferredLocale, () => 'fr');
  const [employees, setEmployees] = useState<EmployeeRecord[]>([]);
  const [total, setTotal] = useState(0);
  const [departments, setDepartments] = useState<DepartmentRecord[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);

  // ── Création d'un département ──────────────────────────────────────────
  const [departmentName, setDepartmentName] = useState('');
  const [isCreatingDepartment, setIsCreatingDepartment] = useState(false);
  const [departmentError, setDepartmentError] = useState<string | null>(null);

  // ── Création d'un collaborateur ────────────────────────────────────────
  const [showAddForm, setShowAddForm] = useState(false);
  const [firstName, setFirstName] = useState('');
  const [lastName, setLastName] = useState('');
  const [email, setEmail] = useState('');
  const [department, setDepartment] = useState('');
  const [isCreatingEmployee, setIsCreatingEmployee] = useState(false);
  const [employeeError, setEmployeeError] = useState<string | null>(null);
  const [employeeAdded, setEmployeeAdded] = useState(false);

  const loadEmployees = useCallback(async () => {
    const response = await apiFetch('/employees?per_page=12', { _cacheBust: true });
    const payload = (await response.json()) as EmployeesPayload;
    const list = Array.isArray(payload.data) ? payload.data : [];
    setEmployees(list);
    setTotal(payload.meta?.total ?? list.length);
  }, []);

  const loadDepartments = useCallback(async () => {
    try {
      const response = await apiFetch('/departments?per_page=100', { _cacheBust: true });
      const payload = (await response.json()) as DepartmentsPayload;
      setDepartments(Array.isArray(payload.data) ? payload.data : []);
    } catch {
      // Un non-manager n'a pas accès aux départements : la liste reste vide,
      // la page fonctionne malgré tout.
      setDepartments([]);
    }
  }, []);

  useEffect(() => {
    let active = true;

    async function load() {
      try {
        await Promise.all([loadEmployees(), loadDepartments()]);
      } catch (err) {
        if (active) {
          setError(apiErrorMessage(err, i18nT(locale, 'employees.load_error')));
        }
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
  }, [locale, loadEmployees, loadDepartments]);

  /** Confirme une étape d'onboarding (best-effort : si la garde refuse, on ignore). */
  const confirmOnboardingStep = useCallback(async (stepKey: string) => {
    try {
      await apiFetch(`/onboarding-setup/${stepKey}/complete`, { method: 'POST' });
    } catch {
      // Silencieux : l'assistant de démarrage reste la source de vérité.
    }
  }, []);

  const handleCreateDepartment = async (event: React.FormEvent) => {
    event.preventDefault();
    const name = departmentName.trim();
    if (!name) {
      setDepartmentError(i18nT(locale, 'settingsFieldRequired'));
      return;
    }

    setIsCreatingDepartment(true);
    setDepartmentError(null);

    try {
      await apiFetch('/departments', {
        method: 'POST',
        body: JSON.stringify({ name }),
      });
      setDepartmentName('');
      await loadDepartments();
      await confirmOnboardingStep('first_department');
    } catch (err) {
      setDepartmentError(
        apiErrorMessage(err, i18nT(locale, 'employees.create_department_error')),
      );
    } finally {
      setIsCreatingDepartment(false);
    }
  };

  const handleCreateEmployee = async (event: React.FormEvent) => {
    event.preventDefault();

    const payload = {
      first_name: firstName.trim(),
      last_name: lastName.trim(),
      email: email.trim(),
      role: 'employee',
      // Le backend exige SOIT un mot de passe, SOIT une invitation
      // (`StoreEmployeeRequest::withValidator` → EMPLOYEE_PASSWORD_OR_INVITATION_REQUIRED).
      // On envoie l'invitation : c'est le parcours normal (le collaborateur
      // reçoit son lien et définit lui-même son mot de passe).
      send_invitation: true,
      ...(department.trim() ? { extra_data: { department: department.trim() } } : {}),
    };

    if (!payload.first_name || !payload.last_name || !payload.email) {
      setEmployeeError(i18nT(locale, 'settingsFieldRequired'));
      return;
    }

    setIsCreatingEmployee(true);
    setEmployeeError(null);

    try {
      await apiFetch('/employees', {
        method: 'POST',
        body: JSON.stringify(payload),
      });

      setFirstName('');
      setLastName('');
      setEmail('');
      setDepartment('');
      setEmployeeAdded(true);
      setShowAddForm(false);
      await loadEmployees();
      await confirmOnboardingStep('first_employee');
    } catch (err) {
      setEmployeeError(
        apiErrorMessage(err, i18nT(locale, 'employees.create_employee_error')),
      );
    } finally {
      setIsCreatingEmployee(false);
    }
  };

  const inputClassName =
    'w-full rounded-xl border border-app-border bg-white px-3 py-2 text-sm text-slate-900 outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20';

  return (
    <ModulePageShell
      title={i18nT(locale, 'employees.title')}
      subtitle={i18nT(locale, 'employees.subtitle')}
      accentClassName="bg-gradient-to-br from-rh-light via-white to-white"
    >
      {error ? (
        <div className="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
          {error}
        </div>
      ) : null}

      {employeeAdded ? (
        <div className="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
          {i18nT(locale, 'teamEmployeeAdded')}
        </div>
      ) : null}

      <section className="grid gap-4 md:grid-cols-3">
        <div className="rounded-2xl border border-app-border bg-white p-5 shadow-sm">
          <p className="text-xs font-bold uppercase tracking-widest text-slate-400">
            {i18nT(locale, 'employees.total_team')}
          </p>
          <p className="mt-3 text-4xl font-black text-slate-950">{loading ? '...' : total}</p>
        </div>
        <div className="rounded-2xl border border-app-border bg-white p-5 shadow-sm">
          <p className="text-xs font-bold uppercase tracking-widest text-slate-400">
            {i18nT(locale, 'employees.state')}
          </p>
          <p className="mt-3 text-lg font-bold text-slate-950">
            {loading
              ? i18nT(locale, 'employees.loading_short')
              : i18nT(locale, 'employees.connected_api')}
          </p>
        </div>
        <div className="rounded-2xl border border-app-border bg-white p-5 shadow-sm">
          <p className="text-xs font-bold uppercase tracking-widest text-slate-400">
            {i18nT(locale, 'dashboard.departments')}
          </p>
          <p className="mt-3 text-4xl font-black text-slate-950">{loading ? '...' : departments.length}</p>
        </div>
      </section>

      {/* ── Départements ─────────────────────────────────────────────────── */}
      <section className="overflow-hidden rounded-3xl border border-app-border bg-white shadow-sm">
        <div className="border-b border-app-border px-6 py-4">
          <h2 className="text-sm font-bold uppercase tracking-wider text-slate-800">
            {i18nT(locale, 'dashboard.departments')}
          </h2>
        </div>

        <div className="space-y-4 px-6 py-5">
          {departments.length === 0 ? (
            <p className="text-sm text-slate-500">
              {i18nT(locale, 'employees.empty_departments')}
            </p>
          ) : (
            <ul className="flex flex-wrap gap-2">
              {departments.map((item) => (
                <li
                  key={item.id}
                  className="rounded-full bg-slate-100 px-3 py-1 text-[11px] font-bold uppercase tracking-wider text-slate-600"
                >
                  {item.name}
                </li>
              ))}
            </ul>
          )}

          <form onSubmit={handleCreateDepartment} className="flex flex-col gap-3 sm:flex-row">
            <input
              type="text"
              value={departmentName}
              onChange={(event) => setDepartmentName(event.target.value)}
              placeholder={i18nT(locale, 'profile.department_label')}
              aria-label={i18nT(locale, 'profile.department_label')}
              className={inputClassName}
            />
            <button
              type="submit"
              disabled={isCreatingDepartment}
              className="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl bg-brand-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-brand-500 disabled:opacity-60"
            >
              <Plus className="h-4 w-4" />
              {i18nT(locale, 'cabinet.screen.create')}
            </button>
          </form>

          {departmentError ? <p className="text-sm text-red-600">{departmentError}</p> : null}
        </div>
      </section>

      {/* ── Ajouter un collaborateur ─────────────────────────────────────── */}
      <section className="overflow-hidden rounded-3xl border border-app-border bg-white shadow-sm">
        <div className="flex items-center justify-between border-b border-app-border px-6 py-4">
          <h2 className="text-sm font-bold uppercase tracking-wider text-slate-800">
            {i18nT(locale, 'employees.recent_collaborators')}
          </h2>
          <button
            type="button"
            onClick={() => {
              setShowAddForm((value) => !value);
              setEmployeeError(null);
            }}
            className="inline-flex items-center gap-2 rounded-xl bg-slate-900 px-4 py-2 text-xs font-bold uppercase tracking-wider text-white transition hover:bg-slate-700"
          >
            {showAddForm ? <X className="h-4 w-4" /> : <Plus className="h-4 w-4" />}
            {i18nT(locale, 'teamAddCollaborator')}
          </button>
        </div>

        {showAddForm ? (
          <form onSubmit={handleCreateEmployee} className="grid gap-4 border-b border-app-border bg-slate-50 px-6 py-5 md:grid-cols-2">
            <label className="text-sm font-medium text-slate-700">
              {i18nT(locale, 'settingsFirstName')}
              <input
                type="text"
                value={firstName}
                onChange={(event) => setFirstName(event.target.value)}
                className={`mt-1 ${inputClassName}`}
                required
              />
            </label>

            <label className="text-sm font-medium text-slate-700">
              {i18nT(locale, 'settingsLastNameLabel')}
              <input
                type="text"
                value={lastName}
                onChange={(event) => setLastName(event.target.value)}
                className={`mt-1 ${inputClassName}`}
                required
              />
            </label>

            <label className="text-sm font-medium text-slate-700">
              {i18nT(locale, 'settingsEmailLabel')}
              <input
                type="email"
                value={email}
                onChange={(event) => setEmail(event.target.value)}
                className={`mt-1 ${inputClassName}`}
                required
              />
            </label>

            <label className="text-sm font-medium text-slate-700">
              {i18nT(locale, 'teamDepartmentOptional')}
              <input
                type="text"
                value={department}
                onChange={(event) => setDepartment(event.target.value)}
                list="leopardo-departments"
                className={`mt-1 ${inputClassName}`}
              />
              <datalist id="leopardo-departments">
                {departments.map((item) => (
                  <option key={item.id} value={item.name} />
                ))}
              </datalist>
            </label>

            {employeeError ? <p className="text-sm text-red-600 md:col-span-2">{employeeError}</p> : null}

            <div className="flex gap-3 md:col-span-2">
              <button
                type="submit"
                disabled={isCreatingEmployee}
                className="inline-flex items-center gap-2 rounded-xl bg-brand-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-brand-500 disabled:opacity-60"
              >
                {i18nT(locale, 'commonSave')}
              </button>
              <button
                type="button"
                onClick={() => setShowAddForm(false)}
                className="rounded-xl border border-app-border px-4 py-2 text-sm font-semibold text-slate-600 transition hover:bg-slate-100"
              >
                {i18nT(locale, 'teamConfirmCancel')}
              </button>
            </div>
          </form>
        ) : null}

        <div className="divide-y divide-app-border">
          {loading ? (
            <div className="px-6 py-8 text-sm text-slate-500">
              {i18nT(locale, 'employees.list_loading')}
            </div>
          ) : employees.length === 0 ? (
            <div className="px-6 py-8 text-sm text-slate-500">
              {i18nT(locale, 'employees.empty_list')}
            </div>
          ) : (
            employees.map((employee) => {
              const fullName =
                `${employee.first_name ?? ''} ${employee.last_name ?? ''}`.trim() ||
                employee.email ||
                `Employe #${employee.id}`;

              return (
                <div
                  key={employee.id}
                  className="flex flex-col gap-3 px-6 py-5 md:flex-row md:items-center md:justify-between"
                >
                  <div>
                    <p className="text-sm font-bold text-slate-950">{fullName}</p>
                    <p className="text-xs text-slate-500">{employee.email ?? 'Email indisponible'}</p>
                  </div>
                  <div className="flex flex-wrap gap-2 text-[11px] font-bold uppercase tracking-wider">
                    <span className="rounded-full bg-slate-100 px-3 py-1 text-slate-600">
                      {employee.matricule ?? 'Sans matricule'}
                    </span>
                    <span className="rounded-full bg-rh-light px-3 py-1 text-rh-dark">
                      {employee.role ?? 'employee'}
                    </span>
                    <span className="rounded-full bg-slate-900 px-3 py-1 text-white">
                      {employee.status ?? 'active'}
                    </span>
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
