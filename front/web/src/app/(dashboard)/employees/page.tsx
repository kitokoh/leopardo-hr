'use client';

import { useCallback, useEffect, useMemo, useState, useSyncExternalStore } from 'react';
import {
  Archive,
  ChevronDown,
  ChevronUp,
  Plus,
  RefreshCw,
  Search,
  Trash2,
  Users,
  X,
} from 'lucide-react';

import { apiFetch } from '@/lib/api-client';
import { ModulePageShell } from '@/components/module-page-shell';
import { getPreferredLocale, getStoredUser, toIntlLocale, type AppLocale, type StoredAuthUser } from '@/lib/i18n';
import { interpolate, t as i18nT } from '@/lib/i18n/locale-catalog';
import {
  isManagerUser,
  parseTeamRoleOption,
  teamRoleLabel,
  teamRoleOptionValue,
  teamRoleOptions,
  teamRolesErrorMessage,
  teamRolesT,
  type TeamRolesKey,
} from '@/lib/i18n/team-roles';

import { ModuleGrantsPanel } from './module-grants-panel';
import { ResourceAccessPanel } from './resource-access-panel';

type EmployeeRecord = {
  id: number;
  first_name?: string | null;
  last_name?: string | null;
  email?: string | null;
  role?: string | null;
  manager_role?: string | null;
  status?: string | null;
  matricule?: string | null;
  /** Département d'affectation quand l'API le renvoie (extra_data). */
  extra_data?: { department?: string | null } | null;
};

type EmployeesPayload = {
  data?: EmployeeRecord[];
  meta?: {
    total?: number;
    current_page?: number;
    last_page?: number;
    per_page?: number;
  };
};

type InvitationRecord = {
  id: string | number;
  email?: string | null;
  employee_id?: number | null;
  role?: string | null;
  manager_role?: string | null;
  status?: string | null;
  expires_at?: string | null;
  last_sent_at?: string | null;
  accepted_at?: string | null;
};

type InvitationsPayload = { data?: InvitationRecord[] };

type DepartmentRecord = { id: number; name: string };

type DepartmentsPayload = { data?: DepartmentRecord[] };

const PAGE_SIZE = 12;

/** Statuts d'invitation renvoyés par `GET /invitations` (UserInvitationResource). */
const INVITATION_STATUS_KEYS: Record<string, TeamRolesKey> = {
  pending: 'invitationPending',
  accepted: 'invitationAccepted',
  expired: 'invitationExpired',
};

const inputClassName =
  'w-full rounded-xl border border-app-border bg-white px-3 py-2 text-sm text-slate-900 outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20';

const selectClassName =
  'rounded-xl border border-app-border bg-white px-3 py-2 text-sm font-medium text-slate-900 outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-500';

/**
 * Page Équipe — gestion d'équipe UNIQUE de l'espace client (#7862).
 *
 * L'espace client exposait DEUX pages listant les employés : celle-ci
 * (onboarding + création + accès ressources #7599) et
 * `/settings/team` (« Collaborateurs et rôles » #7555/#7762 : invitations,
 * rôles, modules délégués, archivage). Doublon déroutant : les deux listes se
 * contredisaient selon les droits et les actions étaient éparpillées.
 *
 * `/settings/team` est désormais une simple redirection vers cette page, qui
 * regroupe tout : liste riche (nom, e-mail, département, statut synthétique
 * actif / invitation en attente), et par collaborateur un panneau de détails
 * extensible réunissant rôle, invitation (renvoi/révocation), modules
 * délégués (#7762) et accès ressources (#7599), plus l'archivage.
 *
 * Le flux d'onboarding est conservé : création du premier département et du
 * premier collaborateur, confirmées best-effort auprès de
 * `/onboarding-setup/{stepKey}/complete` (la garde backend reste seule juge).
 *
 * Garde-fous API reflétés dans l'UI (jamais contournés) :
 * - `principal` n'est jamais proposé (création réservée, promotion interdite) ;
 * - le rôle de sa PROPRE ligne n'est pas modifiable (ignoré côté API) ;
 * - invitations, rôles et archivage sont réservés aux managers, les modules
 *   délégués au manager principal (policies `manageInvitations`,
 *   `manageModuleGrants`).
 */
export default function EmployeesPage() {
  const locale = useSyncExternalStore<AppLocale>(() => () => {}, getPreferredLocale, () => 'fr');
  const [user] = useState<StoredAuthUser | null>(() => getStoredUser());

  const [employees, setEmployees] = useState<EmployeeRecord[]>([]);
  const [invitations, setInvitations] = useState<InvitationRecord[]>([]);
  const [departments, setDepartments] = useState<DepartmentRecord[]>([]);
  const [departmentsLoading, setDepartmentsLoading] = useState(true);
  const [meta, setMeta] = useState({ total: 0, page: 1, lastPage: 1 });
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);

  // ── Recherche / pagination ────────────────────────────────────────────
  const [searchDraft, setSearchDraft] = useState('');
  const [search, setSearch] = useState('');
  const [page, setPage] = useState(1);

  // ── Création d'un département (onboarding `first_department`) ─────────
  const [departmentName, setDepartmentName] = useState('');
  const [isCreatingDepartment, setIsCreatingDepartment] = useState(false);
  const [departmentError, setDepartmentError] = useState<string | null>(null);

  // ── Création d'un collaborateur (onboarding `first_employee`) ─────────
  const [showAddForm, setShowAddForm] = useState(false);
  const [firstName, setFirstName] = useState('');
  const [lastName, setLastName] = useState('');
  const [email, setEmail] = useState('');
  const [department, setDepartment] = useState('');
  const [roleOption, setRoleOption] = useState('employee');
  const [isCreatingEmployee, setIsCreatingEmployee] = useState(false);
  const [employeeError, setEmployeeError] = useState<string | null>(null);

  // ── Panneau de détails + actions de ligne ─────────────────────────────
  const [openDetailsId, setOpenDetailsId] = useState<number | null>(null);
  const [busyEmployeeId, setBusyEmployeeId] = useState<number | null>(null);
  const [busyInvitationId, setBusyInvitationId] = useState<string | number | null>(null);
  const [confirmArchiveId, setConfirmArchiveId] = useState<number | null>(null);
  const [confirmRevokeId, setConfirmRevokeId] = useState<string | number | null>(null);

  const isManager = isManagerUser(user);
  const isPrincipalViewer =
    (user?.role ?? '').toLowerCase() === 'manager'
    && (user?.manager_role ?? '').toLowerCase() === 'principal';
  const viewerId = user?.id !== undefined && user?.id !== null ? String(user.id) : '';
  const roleOptions = useMemo(() => teamRoleOptions(locale), [locale]);

  const loadEmployees = useCallback(async (targetPage: number, targetSearch: string) => {
    const params = new URLSearchParams({ per_page: String(PAGE_SIZE) });
    if (targetPage > 1) {
      params.set('page', String(targetPage));
    }
    if (targetSearch.trim()) {
      params.set('search', targetSearch.trim());
    }

    const response = await apiFetch(`/employees?${params.toString()}`, { _cacheBust: true });
    const payload = (await response.json()) as EmployeesPayload;
    const list = Array.isArray(payload.data) ? payload.data : [];
    setEmployees(list);
    setMeta({
      total: payload.meta?.total ?? list.length,
      page: payload.meta?.current_page ?? targetPage,
      lastPage: Math.max(payload.meta?.last_page ?? 1, 1),
    });
  }, []);

  const loadInvitations = useCallback(async () => {
    // `GET /invitations` est réservé aux managers (policy `manageInvitations`).
    if (!isManagerUser(getStoredUser())) {
      return;
    }

    try {
      const response = await apiFetch('/invitations', { _cacheBust: true });
      const payload = (await response.json()) as InvitationsPayload;
      setInvitations(Array.isArray(payload.data) ? payload.data : []);
    } catch {
      // Les invitations sont une information d'appoint : un échec (403/404)
      // ne doit pas masquer la liste des collaborateurs.
      setInvitations([]);
    }
  }, []);

  const loadDepartments = useCallback(async () => {
    try {
      const response = await apiFetch('/departments?per_page=100', { _cacheBust: true });
      const payload = (await response.json()) as DepartmentsPayload;
      setDepartments(Array.isArray(payload.data) ? payload.data : []);
    } catch {
      // Un non-manager n'a pas accès aux départements : la liste reste vide,
      // la page fonctionne malgré tout (saisie libre possible).
      setDepartments([]);
    } finally {
      setDepartmentsLoading(false);
    }
  }, []);

  const reload = useCallback(
    async (targetPage: number, targetSearch: string) => {
      try {
        await loadEmployees(targetPage, targetSearch);
        await loadInvitations();
      } catch (err) {
        setError(teamRolesErrorMessage(locale, err, 'loadError'));
      }
    },
    [loadEmployees, loadInvitations, locale],
  );

  useEffect(() => {
    let active = true;

    async function bootstrap() {
      try {
        // Seuls les employés conditionnent l'état de chargement de la page.
        await loadEmployees(1, '');
        await loadInvitations();
      } catch (err) {
        if (active) {
          setError(teamRolesErrorMessage(locale, err, 'loadError'));
        }
      } finally {
        if (active) {
          setLoading(false);
        }
      }
    }

    void bootstrap();

    // Les départements sont chargés EN PARALLÈLE mais ne conditionnent pas
    // l'affichage de l'équipe (un GET /departments lent bloquait la liste,
    // constaté en e2e, #7321).
    void loadDepartments();

    return () => {
      active = false;
    };
  }, [locale, loadEmployees, loadInvitations, loadDepartments]);

  /** Invitation dont l'état est fusionné sur un collaborateur. */
  const invitationFor = useCallback(
    (employee: EmployeeRecord): InvitationRecord | undefined => {
      const byId = invitations.find(
        (invitation) => invitation.employee_id !== null
          && invitation.employee_id !== undefined
          && String(invitation.employee_id) === String(employee.id),
      );
      if (byId) {
        return byId;
      }

      const employeeEmail = (employee.email ?? '').trim().toLowerCase();
      if (!employeeEmail) {
        return undefined;
      }

      return invitations.find((invitation) => (invitation.email ?? '').trim().toLowerCase() === employeeEmail);
    },
    [invitations],
  );

  /** Confirme une étape d'onboarding (best-effort : si la garde refuse, on ignore). */
  const confirmOnboardingStep = useCallback(async (stepKey: string) => {
    try {
      await apiFetch(`/onboarding-setup/${stepKey}/complete`, { method: 'POST' });
    } catch {
      // Silencieux : l'assistant de démarrage reste la source de vérité.
    }
  }, []);

  const handleSearch = async (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setError(null);
    setNotice(null);
    setPage(1);
    setSearch(searchDraft);
    setLoading(true);
    await reload(1, searchDraft);
    setLoading(false);
  };

  const goToPage = async (targetPage: number) => {
    if (targetPage < 1 || targetPage > meta.lastPage || targetPage === meta.page) {
      return;
    }
    setError(null);
    setNotice(null);
    setPage(targetPage);
    setLoading(true);
    await reload(targetPage, search);
    setLoading(false);
  };

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
      setDepartmentError(teamRolesErrorMessage(locale, err));
    } finally {
      setIsCreatingDepartment(false);
    }
  };

  const handleCreateEmployee = async (event: React.FormEvent) => {
    event.preventDefault();

    // #7555 : l'API accepte `role: manager` + `manager_role`
    // (`StoreEmployeeRequest`) et exige un `manager_role` pour un manager.
    const parsedRole = parseTeamRoleOption(roleOption);

    const payload = {
      first_name: firstName.trim(),
      last_name: lastName.trim(),
      email: email.trim(),
      role: parsedRole.role,
      ...(parsedRole.manager_role ? { manager_role: parsedRole.manager_role } : {}),
      // Le backend exige SOIT un mot de passe, SOIT une invitation
      // (EMPLOYEE_PASSWORD_OR_INVITATION_REQUIRED). On envoie l'invitation :
      // le collaborateur définit son mot de passe depuis le lien reçu.
      send_invitation: true,
      ...(department.trim() ? { extra_data: { department: department.trim() } } : {}),
    };

    if (!payload.first_name || !payload.last_name || !payload.email) {
      setEmployeeError(teamRolesT(locale, 'formIncomplete'));
      return;
    }

    setIsCreatingEmployee(true);
    setEmployeeError(null);
    setNotice(null);

    try {
      await apiFetch('/employees', {
        method: 'POST',
        body: JSON.stringify(payload),
      });

      setFirstName('');
      setLastName('');
      setEmail('');
      setDepartment('');
      setRoleOption('employee');
      setShowAddForm(false);
      setNotice(teamRolesT(locale, 'addSuccess'));
      await reload(page, search);
      await confirmOnboardingStep('first_employee');
    } catch (err) {
      setEmployeeError(teamRolesErrorMessage(locale, err));
    } finally {
      setIsCreatingEmployee(false);
    }
  };

  const handleRoleChange = async (employee: EmployeeRecord, optionValue: string) => {
    const parsedRole = parseTeamRoleOption(optionValue);
    setError(null);
    setNotice(null);
    setBusyEmployeeId(employee.id);

    try {
      await apiFetch(`/employees/${employee.id}`, {
        method: 'PATCH',
        body: JSON.stringify({ role: parsedRole.role, manager_role: parsedRole.manager_role }),
      });
      setNotice(teamRolesT(locale, 'roleUpdated'));
      await reload(page, search);
    } catch (err) {
      setError(teamRolesErrorMessage(locale, err));
    } finally {
      setBusyEmployeeId(null);
    }
  };

  const handleResend = async (invitation: InvitationRecord) => {
    setError(null);
    setNotice(null);
    setBusyInvitationId(invitation.id);

    try {
      await apiFetch(`/invitations/${invitation.id}/resend`, { method: 'POST' });
      setNotice(teamRolesT(locale, 'resendSuccess'));
      await loadInvitations();
    } catch (err) {
      setError(teamRolesErrorMessage(locale, err));
    } finally {
      setBusyInvitationId(null);
    }
  };

  /**
   * Révocation d'une invitation en attente (après confirmation inline, même
   * patron que l'archivage) : DELETE /invitations/{id}, le lien reçu par
   * e-mail devient inutilisable immédiatement.
   */
  const handleRevoke = async (invitation: InvitationRecord) => {
    setError(null);
    setNotice(null);
    setBusyInvitationId(invitation.id);

    try {
      await apiFetch(`/invitations/${invitation.id}`, { method: 'DELETE' });
      setConfirmRevokeId(null);
      setNotice(teamRolesT(locale, 'revokeSuccess'));
      await loadInvitations();
    } catch (err) {
      setError(teamRolesErrorMessage(locale, err));
    } finally {
      setBusyInvitationId(null);
    }
  };

  const handleArchive = async (employee: EmployeeRecord) => {
    setError(null);
    setNotice(null);
    setBusyEmployeeId(employee.id);

    try {
      await apiFetch(`/employees/${employee.id}/archive`, { method: 'POST' });
      setConfirmArchiveId(null);
      setOpenDetailsId(null);
      setNotice(teamRolesT(locale, 'archiveSuccess'));
      await reload(page, search);
    } catch (err) {
      setError(teamRolesErrorMessage(locale, err));
    } finally {
      setBusyEmployeeId(null);
    }
  };

  const toggleDetails = (employeeId: number) => {
    setConfirmArchiveId(null);
    setConfirmRevokeId(null);
    setOpenDetailsId((current) => (current === employeeId ? null : employeeId));
  };

  return (
    <ModulePageShell
      title={i18nT(locale, 'employees.title')}
      subtitle={i18nT(locale, 'employees.subtitle')}
      icon={Users}
      accentClassName="bg-gradient-to-br from-rh-light via-white to-white"
    >
      {notice ? (
        <div
          role="status"
          className="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800"
        >
          {notice}
        </div>
      ) : null}

      {error ? (
        <div
          role="alert"
          className="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700"
        >
          {error}
        </div>
      ) : null}

      {/* ── Indicateurs ──────────────────────────────────────────────────── */}
      <section className="grid gap-4 md:grid-cols-2">
        <div className="rounded-2xl border border-app-border bg-white p-5 shadow-sm">
          <p className="text-xs font-bold uppercase tracking-widest text-slate-500">
            {i18nT(locale, 'employees.total_team')}
          </p>
          <p className="mt-3 text-4xl font-black text-slate-950">{loading ? '...' : meta.total}</p>
        </div>
        <div className="rounded-2xl border border-app-border bg-white p-5 shadow-sm">
          <p className="text-xs font-bold uppercase tracking-widest text-slate-500">
            {i18nT(locale, 'dashboard.departments')}
          </p>
          <p className="mt-3 text-4xl font-black text-slate-950">
            {departmentsLoading ? '...' : departments.length}
          </p>
        </div>
      </section>

      {/* ── Départements (onboarding `first_department`) ─────────────────── */}
      <section className="overflow-hidden rounded-3xl border border-app-border bg-white shadow-sm">
        <div className="border-b border-app-border px-6 py-4">
          <h2 className="text-sm font-bold uppercase tracking-wider text-slate-800">
            {i18nT(locale, 'dashboard.departments')}
          </h2>
        </div>

        <div className="space-y-4 px-6 py-5">
          {departmentsLoading ? (
            <p className="text-sm text-slate-500">{i18nT(locale, 'employees.loading_short')}</p>
          ) : departments.length === 0 ? (
            <p className="text-sm text-slate-500">{i18nT(locale, 'employees.empty_departments')}</p>
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
              className="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl bg-brand-700 px-4 py-2 text-sm font-semibold text-white transition hover:bg-brand-800 disabled:opacity-60"
            >
              <Plus className="h-4 w-4" aria-hidden="true" />
              {i18nT(locale, 'cabinet.screen.create')}
            </button>
          </form>

          {departmentError ? <p className="text-sm text-red-600">{departmentError}</p> : null}
        </div>
      </section>

      {/* ── Recherche ────────────────────────────────────────────────────── */}
      <section className="rounded-3xl border border-app-border bg-white p-6 shadow-sm">
        <form onSubmit={handleSearch} className="flex flex-col gap-3 sm:flex-row sm:items-end">
          <label className="flex-1 text-sm font-semibold text-slate-700">
            {teamRolesT(locale, 'searchLabel')}
            <span className="mt-1 flex items-center gap-2">
              <Search className="h-4 w-4 shrink-0 text-slate-400" aria-hidden="true" />
              <input
                type="search"
                value={searchDraft}
                onChange={(event) => setSearchDraft(event.target.value)}
                placeholder={teamRolesT(locale, 'searchPlaceholder')}
                data-testid="team-search-input"
                className={inputClassName}
              />
            </span>
          </label>
          <button
            type="submit"
            className="inline-flex items-center justify-center gap-2 rounded-xl border border-app-border px-4 py-2 text-sm font-bold text-slate-700 transition hover:bg-slate-100"
          >
            {teamRolesT(locale, 'searchAction')}
          </button>
        </form>
      </section>

      {/* ── Liste + création d'un collaborateur ──────────────────────────── */}
      <section className="overflow-hidden rounded-3xl border border-app-border bg-white shadow-sm">
        <div className="flex flex-wrap items-center justify-between gap-3 border-b border-app-border px-6 py-4">
          <h2 className="text-sm font-bold uppercase tracking-wider text-slate-800">
            {teamRolesT(locale, 'colName')}
          </h2>
          <div className="flex items-center gap-3">
            <p className="text-xs font-bold uppercase tracking-wider text-slate-500">
              {interpolate(teamRolesT(locale, 'total'), { count: meta.total })}
            </p>
            <button
              type="button"
              onClick={() => {
                setShowAddForm((value) => !value);
                setEmployeeError(null);
                setNotice(null);
              }}
              data-testid="team-add-toggle"
              className="inline-flex items-center gap-2 rounded-xl bg-brand-700 px-4 py-2 text-xs font-bold uppercase tracking-wider text-white transition hover:bg-brand-800"
            >
              {showAddForm ? <X className="h-4 w-4" aria-hidden="true" /> : <Plus className="h-4 w-4" aria-hidden="true" />}
              {i18nT(locale, 'teamAddCollaborator')}
            </button>
          </div>
        </div>

        {showAddForm ? (
          <form
            onSubmit={handleCreateEmployee}
            className="grid gap-4 border-b border-app-border bg-slate-50 px-6 py-5 md:grid-cols-2"
          >
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

            {/* #7555 — choix du rôle à l'invitation : « employé » ou un type
                de manager, jamais `principal` (réservé côté API). */}
            <label className="text-sm font-medium text-slate-700 md:col-span-2">
              {teamRolesT(locale, 'fieldRole')}
              <select
                value={roleOption}
                onChange={(event) => setRoleOption(event.target.value)}
                data-testid="employees-role-select"
                className={`mt-1 ${inputClassName}`}
              >
                {roleOptions.map((option) => (
                  <option key={option.value} value={option.value}>
                    {option.label}
                  </option>
                ))}
              </select>
            </label>

            {employeeError ? <p className="text-sm text-red-600 md:col-span-2">{employeeError}</p> : null}

            <div className="flex gap-3 md:col-span-2">
              <button
                type="submit"
                disabled={isCreatingEmployee}
                className="inline-flex items-center gap-2 rounded-xl bg-brand-700 px-4 py-2 text-sm font-semibold text-white transition hover:bg-brand-800 disabled:opacity-60"
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
            <p className="px-6 py-8 text-sm text-slate-500">{i18nT(locale, 'employees.list_loading')}</p>
          ) : employees.length === 0 ? (
            <p className="px-6 py-8 text-sm text-slate-500">{i18nT(locale, 'employees.empty_list')}</p>
          ) : (
            employees.map((employee) => {
              const isSelf = viewerId !== '' && String(employee.id) === viewerId;
              const invitation = invitationFor(employee);
              const invitationStatusKey = invitation
                ? INVITATION_STATUS_KEYS[invitation.status ?? 'pending'] ?? 'invitationPending'
                : null;
              const fullName =
                `${employee.first_name ?? ''} ${employee.last_name ?? ''}`.trim()
                || employee.email
                || `#${employee.id}`;
              const currentOption = teamRoleOptionValue(employee.role, employee.manager_role);
              const optionIsProposable = roleOptions.some((option) => option.value === currentOption);
              const employeeDepartment = (employee.extra_data?.department ?? '').trim();
              // Statut synthétique : « en attente » tant que l'invitation
              // n'est pas acceptée, sinon le statut RH (actif par défaut).
              const isPendingInvitation = invitation?.status === 'pending';
              const statusLabel = isPendingInvitation
                ? teamRolesT(locale, 'invitationPending')
                : employee.status && employee.status !== 'active'
                  ? employee.status
                  : teamRolesT(locale, 'statusActive');
              const detailsOpen = openDetailsId === employee.id;

              return (
                <div key={employee.id} data-testid={`employee-row-${employee.id}`} className="px-6 py-5">
                  <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <div className="min-w-0">
                      <p className="flex flex-wrap items-center gap-2 text-sm font-bold text-slate-950">
                        <span className="truncate">{fullName}</span>
                        {isSelf ? (
                          <span className="rounded-full bg-slate-900 px-2 py-0.5 text-[10px] font-black uppercase tracking-wider text-white">
                            {teamRolesT(locale, 'youBadge')}
                          </span>
                        ) : null}
                      </p>
                      <p className="truncate text-xs text-slate-500">{employee.email ?? ''}</p>
                    </div>

                    <div className="flex flex-wrap items-center gap-2 text-[11px] font-bold uppercase tracking-wider">
                      {employee.matricule ? (
                        <span className="rounded-full bg-slate-100 px-3 py-1 text-slate-600">
                          {employee.matricule}
                        </span>
                      ) : null}
                      {employeeDepartment ? (
                        <span className="rounded-full bg-slate-100 px-3 py-1 text-slate-600">
                          {employeeDepartment}
                        </span>
                      ) : null}
                      <span className="rounded-full bg-rh-light px-3 py-1 text-rh-dark">
                        {teamRoleLabel(locale, employee.role, employee.manager_role)}
                      </span>
                      <span
                        data-testid={`employee-status-${employee.id}`}
                        className={
                          isPendingInvitation
                            ? 'rounded-full bg-amber-100 px-3 py-1 text-amber-800'
                            : 'rounded-full bg-emerald-100 px-3 py-1 text-emerald-800'
                        }
                      >
                        {statusLabel}
                      </span>
                      <button
                        type="button"
                        onClick={() => toggleDetails(employee.id)}
                        data-testid={`employee-details-toggle-${employee.id}`}
                        aria-expanded={detailsOpen}
                        className="inline-flex items-center gap-1.5 rounded-xl border border-app-border px-3 py-1.5 text-slate-600 transition hover:bg-slate-100"
                      >
                        {detailsOpen ? (
                          <ChevronUp className="h-3.5 w-3.5" aria-hidden="true" />
                        ) : (
                          <ChevronDown className="h-3.5 w-3.5" aria-hidden="true" />
                        )}
                        {teamRolesT(locale, detailsOpen ? 'detailsClose' : 'detailsOpen')}
                      </button>
                    </div>
                  </div>

                  {/* ── Panneau de détails (#7862) ─────────────────────── */}
                  {detailsOpen ? (
                    <div
                      data-testid={`employee-details-${employee.id}`}
                      className="mt-4 space-y-4 rounded-2xl border border-app-border bg-slate-50 p-4"
                    >
                      {/* Rôle (managers, hors propre ligne) */}
                      {isManager ? (
                        <div className="rounded-2xl border border-app-border bg-white p-4">
                          <p className="text-[11px] font-bold uppercase tracking-wider text-slate-600">
                            {teamRolesT(locale, 'colRole')}
                          </p>
                          <div className="mt-2">
                            {isSelf ? (
                              <span
                                title={teamRolesT(locale, 'selfRoleLocked')}
                                className="inline-flex rounded-full bg-rh-light px-3 py-1 text-[11px] font-bold uppercase tracking-wider text-rh-dark"
                              >
                                {teamRoleLabel(locale, employee.role, employee.manager_role)}
                              </span>
                            ) : (
                              <select
                                aria-label={teamRolesT(locale, 'roleSelectLabel')}
                                value={currentOption}
                                disabled={busyEmployeeId === employee.id}
                                onChange={(event) => void handleRoleChange(employee, event.target.value)}
                                data-testid={`team-role-select-${employee.id}`}
                                className={selectClassName}
                              >
                                {!optionIsProposable ? (
                                  // Rôle courant non proposable (ex. manager principal) :
                                  // affiché pour ne pas mentir sur l'état, non sélectionnable.
                                  <option value={currentOption} disabled>
                                    {teamRoleLabel(locale, employee.role, employee.manager_role)}
                                  </option>
                                ) : null}
                                {roleOptions.map((option) => (
                                  <option key={option.value} value={option.value}>
                                    {option.label}
                                  </option>
                                ))}
                              </select>
                            )}
                          </div>
                        </div>
                      ) : null}

                      {/* Invitation : statut + renvoi + révocation (managers) */}
                      {isManager ? (
                        <div className="rounded-2xl border border-app-border bg-white p-4">
                          <p className="text-[11px] font-bold uppercase tracking-wider text-slate-600">
                            {teamRolesT(locale, 'colInvitation')}
                          </p>
                          <div className="mt-2 flex flex-wrap items-center gap-2">
                            {invitation && invitationStatusKey ? (
                              <>
                                <span
                                  data-testid={`team-invitation-status-${employee.id}`}
                                  className="rounded-full bg-slate-100 px-3 py-1 text-[11px] font-bold uppercase tracking-wider text-slate-600"
                                >
                                  {teamRolesT(locale, invitationStatusKey)}
                                </span>
                                {invitation.last_sent_at ? (
                                  <span className="text-[11px] font-medium text-slate-400">
                                    {interpolate(teamRolesT(locale, 'invitationSentAt'), {
                                      date: new Date(invitation.last_sent_at).toLocaleDateString(toIntlLocale(locale)),
                                    })}
                                  </span>
                                ) : null}
                                {invitation.status !== 'accepted' ? (
                                  <>
                                    <button
                                      type="button"
                                      onClick={() => void handleResend(invitation)}
                                      disabled={busyInvitationId === invitation.id}
                                      data-testid={`team-resend-${employee.id}`}
                                      className="inline-flex items-center gap-1.5 rounded-xl border border-app-border px-3 py-1.5 text-[11px] font-bold text-slate-700 transition hover:bg-slate-100 disabled:opacity-60"
                                    >
                                      <RefreshCw className="h-3.5 w-3.5" aria-hidden="true" />
                                      {teamRolesT(locale, 'resend')}
                                    </button>
                                    {confirmRevokeId === invitation.id ? (
                                      <>
                                        <span className="text-[11px] font-bold text-slate-600">
                                          {teamRolesT(locale, 'revokeTitle')}
                                        </span>
                                        <button
                                          type="button"
                                          onClick={() => void handleRevoke(invitation)}
                                          disabled={busyInvitationId === invitation.id}
                                          data-testid={`team-revoke-confirm-${employee.id}`}
                                          className="rounded-xl bg-red-600 px-3 py-1.5 text-[11px] font-bold text-white transition hover:bg-red-700 disabled:opacity-60"
                                        >
                                          {teamRolesT(locale, 'revokeConfirm')}
                                        </button>
                                        <button
                                          type="button"
                                          onClick={() => setConfirmRevokeId(null)}
                                          className="rounded-xl border border-app-border px-3 py-1.5 text-[11px] font-bold text-slate-600 transition hover:bg-slate-100"
                                        >
                                          {teamRolesT(locale, 'cancel')}
                                        </button>
                                      </>
                                    ) : (
                                      <button
                                        type="button"
                                        onClick={() => {
                                          setConfirmRevokeId(invitation.id);
                                          setError(null);
                                          setNotice(null);
                                        }}
                                        data-testid={`team-revoke-${employee.id}`}
                                        className="inline-flex items-center gap-1.5 rounded-xl border border-red-200 px-3 py-1.5 text-[11px] font-bold text-red-600 transition hover:bg-red-50"
                                      >
                                        <Trash2 className="h-3.5 w-3.5" aria-hidden="true" />
                                        {teamRolesT(locale, 'revoke')}
                                      </button>
                                    )}
                                  </>
                                ) : null}
                              </>
                            ) : (
                              <span className="text-[11px] font-medium text-slate-400">
                                {teamRolesT(locale, 'invitationNone')}
                              </span>
                            )}
                          </div>
                        </div>
                      ) : null}

                      {/* Modules délégués (#7762) — manager principal, hors propre ligne */}
                      {isPrincipalViewer && !isSelf ? (
                        <ModuleGrantsPanel
                          employeeId={employee.id}
                          locale={locale}
                          onSaved={(message) => {
                            setError(null);
                            setNotice(message);
                          }}
                        />
                      ) : null}

                      {/* Accès ressources (#7599) */}
                      <ResourceAccessPanel employeeId={employee.id} locale={locale} />

                      {/* Archivage (managers, hors propre ligne) */}
                      {isManager && !isSelf ? (
                        <div className="flex flex-wrap items-center gap-2">
                          {confirmArchiveId === employee.id ? (
                            <>
                              <span className="text-[11px] font-bold text-slate-600">
                                {teamRolesT(locale, 'archiveTitle')}
                              </span>
                              <button
                                type="button"
                                onClick={() => void handleArchive(employee)}
                                disabled={busyEmployeeId === employee.id}
                                data-testid={`team-archive-confirm-${employee.id}`}
                                className="rounded-xl bg-red-600 px-3 py-1.5 text-[11px] font-bold text-white transition hover:bg-red-700 disabled:opacity-60"
                              >
                                {teamRolesT(locale, 'archiveConfirm')}
                              </button>
                              <button
                                type="button"
                                onClick={() => setConfirmArchiveId(null)}
                                className="rounded-xl border border-app-border px-3 py-1.5 text-[11px] font-bold text-slate-600 transition hover:bg-slate-100"
                              >
                                {teamRolesT(locale, 'cancel')}
                              </button>
                            </>
                          ) : (
                            <button
                              type="button"
                              onClick={() => {
                                setConfirmArchiveId(employee.id);
                                setError(null);
                                setNotice(null);
                              }}
                              data-testid={`team-archive-${employee.id}`}
                              className="inline-flex items-center gap-1.5 rounded-xl border border-app-border px-3 py-1.5 text-[11px] font-bold text-slate-600 transition hover:bg-slate-100"
                            >
                              <Archive className="h-3.5 w-3.5" aria-hidden="true" />
                              {teamRolesT(locale, 'archiveAction')}
                            </button>
                          )}
                        </div>
                      ) : null}
                    </div>
                  ) : null}
                </div>
              );
            })
          )}
        </div>

        <div className="flex flex-wrap items-center justify-between gap-3 border-t border-app-border px-6 py-4">
          <p className="text-xs font-medium text-slate-500">
            {interpolate(teamRolesT(locale, 'paginationStatus'), { page: meta.page, last: meta.lastPage })}
          </p>
          <div className="flex gap-2">
            <button
              type="button"
              onClick={() => void goToPage(meta.page - 1)}
              disabled={meta.page <= 1}
              className="rounded-xl border border-app-border px-3 py-1.5 text-xs font-bold text-slate-700 transition hover:bg-slate-100 disabled:opacity-50"
            >
              {teamRolesT(locale, 'paginationPrev')}
            </button>
            <button
              type="button"
              onClick={() => void goToPage(meta.page + 1)}
              disabled={meta.page >= meta.lastPage}
              className="rounded-xl border border-app-border px-3 py-1.5 text-xs font-bold text-slate-700 transition hover:bg-slate-100 disabled:opacity-50"
            >
              {teamRolesT(locale, 'paginationNext')}
            </button>
          </div>
        </div>
      </section>
    </ModulePageShell>
  );
}
