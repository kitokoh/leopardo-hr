'use client';

import { useCallback, useEffect, useMemo, useState, useSyncExternalStore } from 'react';
import { Archive, RefreshCw, Search, UserPlus, Users, X } from 'lucide-react';

import { apiFetch } from '@/lib/api-client';
import { ModulePageShell } from '@/components/module-page-shell';
import { getPreferredLocale, getStoredUser, toIntlLocale, type AppLocale, type StoredAuthUser } from '@/lib/i18n';
import { interpolate } from '@/lib/i18n/locale-catalog';
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

type EmployeeRecord = {
  id: number;
  first_name?: string | null;
  last_name?: string | null;
  email?: string | null;
  role?: string | null;
  manager_role?: string | null;
  status?: string | null;
  matricule?: string | null;
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

type InvitationsPayload = {
  data?: InvitationRecord[];
};

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

function isForbiddenError(error: unknown): boolean {
  return (error as { status?: unknown } | null)?.status === 403;
}

/**
 * Collaborateurs et rôles (#7555).
 *
 * L'Espace client n'exposait AUCUNE surface web pour : inviter un collaborateur
 * avec un rôle, changer un rôle, renvoyer une invitation ou archiver un compte
 * (`GET /invitations` et `POST /invitations/{id}/resend` n'étaient appelés par
 * aucune page). `/employees` créait en outre TOUS les comptes en
 * `role: 'employee'` en dur, alors que l'API accepte `manager` + `manager_role`.
 *
 * Garde-fous API reflétés dans l'UI (jamais contournés) :
 * - `principal` n'est jamais proposé (création réservée : seul un manager
 *   principal peut créer un principal — `EMPLOYEE_PRINCIPAL_MANAGER_CREATION_FORBIDDEN` —
 *   et la promotion par PATCH est interdite — `EMPLOYEE_PROMOTE_PRINCIPAL_FORBIDDEN`) ;
 * - le rôle de sa PROPRE ligne n'est pas modifiable (ignoré côté API).
 *
 * La page est réservée aux managers : un non-manager (ou un 403 API) obtient un
 * état « accès réservé » propre plutôt qu'une erreur brute.
 */
export default function TeamSettingsPage() {
  const locale = useSyncExternalStore<AppLocale>(() => () => {}, getPreferredLocale, () => 'fr');
  const [user] = useState<StoredAuthUser | null>(() => getStoredUser());

  const [employees, setEmployees] = useState<EmployeeRecord[]>([]);
  const [invitations, setInvitations] = useState<InvitationRecord[]>([]);
  const [departments, setDepartments] = useState<DepartmentRecord[]>([]);
  const [meta, setMeta] = useState({ total: 0, page: 1, lastPage: 1 });
  const [loading, setLoading] = useState(true);
  const [accessDenied, setAccessDenied] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);

  // ── Recherche / pagination ────────────────────────────────────────────
  const [searchDraft, setSearchDraft] = useState('');
  const [search, setSearch] = useState('');
  const [page, setPage] = useState(1);

  // ── Invitation d'un collaborateur ─────────────────────────────────────
  const [showAddForm, setShowAddForm] = useState(false);
  const [firstName, setFirstName] = useState('');
  const [lastName, setLastName] = useState('');
  const [email, setEmail] = useState('');
  const [department, setDepartment] = useState('');
  const [roleOption, setRoleOption] = useState('employee');
  const [submitting, setSubmitting] = useState(false);

  // ── Actions de ligne ──────────────────────────────────────────────────
  const [busyEmployeeId, setBusyEmployeeId] = useState<number | null>(null);
  const [busyInvitationId, setBusyInvitationId] = useState<string | number | null>(null);
  const [confirmArchiveId, setConfirmArchiveId] = useState<number | null>(null);

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
      // Datalist d'appoint : la saisie libre reste possible.
      setDepartments([]);
    }
  }, []);

  const reload = useCallback(
    async (targetPage: number, targetSearch: string) => {
      try {
        await loadEmployees(targetPage, targetSearch);
        await loadInvitations();
      } catch (err) {
        if (isForbiddenError(err)) {
          setAccessDenied(true);
          return;
        }
        setError(teamRolesErrorMessage(locale, err, 'loadError'));
      }
    },
    [loadEmployees, loadInvitations, locale],
  );

  useEffect(() => {
    let active = true;

    async function bootstrap() {
      if (!isManagerUser(user)) {
        setAccessDenied(true);
        setLoading(false);
        return;
      }

      try {
        await loadEmployees(1, '');
        await loadInvitations();
      } catch (err) {
        if (!active) {
          return;
        }
        if (isForbiddenError(err)) {
          setAccessDenied(true);
        } else {
          setError(teamRolesErrorMessage(locale, err, 'loadError'));
        }
      } finally {
        if (active) {
          setLoading(false);
        }
      }

      void loadDepartments();
    }

    void bootstrap();

    return () => {
      active = false;
    };
  }, [user, locale, loadEmployees, loadInvitations, loadDepartments]);

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

  const handleCreate = async (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setError(null);
    setNotice(null);

    const parsedRole = parseTeamRoleOption(roleOption);
    if (!firstName.trim() || !lastName.trim() || !email.trim()) {
      setError(teamRolesT(locale, 'formIncomplete'));
      return;
    }

    setSubmitting(true);

    try {
      await apiFetch('/employees', {
        method: 'POST',
        body: JSON.stringify({
          first_name: firstName.trim(),
          last_name: lastName.trim(),
          email: email.trim(),
          role: parsedRole.role,
          ...(parsedRole.manager_role ? { manager_role: parsedRole.manager_role } : {}),
          // L'API exige SOIT un mot de passe, SOIT `send_invitation` : le
          // parcours normal est l'invitation (le collaborateur définit son
          // mot de passe depuis le lien reçu).
          send_invitation: true,
          ...(department.trim() ? { extra_data: { department: department.trim() } } : {}),
        }),
      });

      setFirstName('');
      setLastName('');
      setEmail('');
      setDepartment('');
      setRoleOption('employee');
      setShowAddForm(false);
      setNotice(teamRolesT(locale, 'addSuccess'));
      await reload(page, search);
    } catch (err) {
      setError(teamRolesErrorMessage(locale, err));
    } finally {
      setSubmitting(false);
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

  const handleArchive = async (employee: EmployeeRecord) => {
    setError(null);
    setNotice(null);
    setBusyEmployeeId(employee.id);

    try {
      await apiFetch(`/employees/${employee.id}/archive`, { method: 'POST' });
      setConfirmArchiveId(null);
      setNotice(teamRolesT(locale, 'archiveSuccess'));
      await reload(page, search);
    } catch (err) {
      setError(teamRolesErrorMessage(locale, err));
    } finally {
      setBusyEmployeeId(null);
    }
  };

  const shell = (children: React.ReactNode) => (
    <ModulePageShell
      title={teamRolesT(locale, 'title')}
      subtitle={teamRolesT(locale, 'subtitle')}
      icon={Users}
      accentClassName="bg-gradient-to-br from-slate-50 via-white to-white"
    >
      {children}
    </ModulePageShell>
  );

  if (accessDenied) {
    return shell(
      <section
        role="status"
        data-testid="team-access-denied"
        className="rounded-3xl border border-amber-200 bg-amber-50 p-6 shadow-sm"
      >
        <h2 className="text-sm font-bold uppercase tracking-wider text-amber-900">
          {teamRolesT(locale, 'reservedTitle')}
        </h2>
        <p className="mt-2 max-w-2xl text-sm leading-6 text-amber-800">
          {teamRolesT(locale, 'reservedBody')}
        </p>
      </section>,
    );
  }

  return shell(
    <>
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

      {/* ── Recherche + invitation ─────────────────────────────────────── */}
      <section className="grid gap-4 lg:grid-cols-[1fr_auto]">
        <form
          onSubmit={handleSearch}
          className="flex flex-col gap-3 rounded-3xl border border-app-border bg-white p-6 shadow-sm sm:flex-row sm:items-end"
        >
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

        <div className="flex items-end rounded-3xl border border-app-border bg-white p-6 shadow-sm">
          <button
            type="button"
            onClick={() => {
              setShowAddForm((value) => !value);
              setError(null);
              setNotice(null);
            }}
            data-testid="team-add-toggle"
            className="inline-flex items-center gap-2 rounded-xl bg-brand-700 px-4 py-2 text-sm font-bold text-white transition hover:bg-brand-800"
          >
            {showAddForm ? <X className="h-4 w-4" aria-hidden="true" /> : <UserPlus className="h-4 w-4" aria-hidden="true" />}
            {teamRolesT(locale, showAddForm ? 'cancel' : 'addToggle')}
          </button>
        </div>
      </section>

      {showAddForm ? (
        <section className="rounded-3xl border border-app-border bg-white p-6 shadow-sm">
          <h2 className="text-sm font-bold uppercase tracking-wider text-slate-800">
            {teamRolesT(locale, 'addTitle')}
          </h2>
          <form onSubmit={handleCreate} className="mt-5 grid gap-4 md:grid-cols-2">
            <label className="text-sm font-medium text-slate-700">
              {teamRolesT(locale, 'fieldFirstName')}
              <input
                type="text"
                value={firstName}
                onChange={(event) => setFirstName(event.target.value)}
                required
                data-testid="team-add-first-name"
                className={`mt-1 ${inputClassName}`}
              />
            </label>

            <label className="text-sm font-medium text-slate-700">
              {teamRolesT(locale, 'fieldLastName')}
              <input
                type="text"
                value={lastName}
                onChange={(event) => setLastName(event.target.value)}
                required
                data-testid="team-add-last-name"
                className={`mt-1 ${inputClassName}`}
              />
            </label>

            <label className="text-sm font-medium text-slate-700">
              {teamRolesT(locale, 'fieldEmail')}
              <input
                type="email"
                value={email}
                onChange={(event) => setEmail(event.target.value)}
                required
                data-testid="team-add-email"
                className={`mt-1 ${inputClassName}`}
              />
            </label>

            <label className="text-sm font-medium text-slate-700">
              {teamRolesT(locale, 'fieldDepartment')}
              <input
                type="text"
                value={department}
                onChange={(event) => setDepartment(event.target.value)}
                list="team-roles-departments"
                data-testid="team-add-department"
                className={`mt-1 ${inputClassName}`}
              />
              <datalist id="team-roles-departments">
                {departments.map((item) => (
                  <option key={item.id} value={item.name} />
                ))}
              </datalist>
            </label>

            <label className="text-sm font-medium text-slate-700 md:col-span-2">
              {teamRolesT(locale, 'fieldRole')}
              <select
                value={roleOption}
                onChange={(event) => setRoleOption(event.target.value)}
                data-testid="team-add-role"
                className={`mt-1 ${selectClassName}`}
              >
                {roleOptions.map((option) => (
                  <option key={option.value} value={option.value}>
                    {option.label}
                  </option>
                ))}
              </select>
            </label>

            <div className="flex gap-3 md:col-span-2">
              <button
                type="submit"
                disabled={submitting}
                className="inline-flex items-center gap-2 rounded-xl bg-brand-700 px-4 py-2 text-sm font-bold text-white transition hover:bg-brand-800 disabled:opacity-60"
              >
                {teamRolesT(locale, 'addSubmit')}
              </button>
            </div>
          </form>
        </section>
      ) : null}

      {/* ── Liste des collaborateurs ───────────────────────────────────── */}
      <section className="overflow-hidden rounded-3xl border border-app-border bg-white shadow-sm">
        <div className="flex flex-wrap items-center justify-between gap-2 border-b border-app-border px-6 py-4">
          <h2 className="text-sm font-bold uppercase tracking-wider text-slate-800">
            {teamRolesT(locale, 'colName')}
          </h2>
          <p className="text-xs font-bold uppercase tracking-wider text-slate-500">
            {interpolate(teamRolesT(locale, 'total'), { count: meta.total })}
          </p>
        </div>

        <div className="hidden gap-4 border-b border-app-border px-6 py-3 text-[11px] font-bold uppercase tracking-wider text-slate-500 md:grid md:grid-cols-[minmax(0,2fr)_minmax(0,1.3fr)_minmax(0,1.6fr)_minmax(0,1.6fr)]">
          <span>{teamRolesT(locale, 'colName')}</span>
          <span>{teamRolesT(locale, 'colRole')}</span>
          <span>{teamRolesT(locale, 'colInvitation')}</span>
          <span>{teamRolesT(locale, 'colActions')}</span>
        </div>

        {loading ? (
          <p className="px-6 py-8 text-sm text-slate-500">{teamRolesT(locale, 'loading')}</p>
        ) : employees.length === 0 ? (
          <p className="px-6 py-8 text-sm text-slate-500">{teamRolesT(locale, 'listEmpty')}</p>
        ) : (
          <div className="divide-y divide-app-border">
            {employees.map((employee) => {
              const isSelf = viewerId !== '' && String(employee.id) === viewerId;
              const invitation = invitationFor(employee);
              const fullName =
                `${employee.first_name ?? ''} ${employee.last_name ?? ''}`.trim()
                || employee.email
                || `#${employee.id}`;
              const currentOption = teamRoleOptionValue(employee.role, employee.manager_role);
              const optionIsProposable = roleOptions.some((option) => option.value === currentOption);
              const invitationStatusKey = invitation
                ? INVITATION_STATUS_KEYS[invitation.status ?? 'pending'] ?? 'invitationPending'
                : null;

              return (
                <div
                  key={employee.id}
                  data-testid={`team-row-${employee.id}`}
                  className="grid gap-4 px-6 py-5 md:grid-cols-[minmax(0,2fr)_minmax(0,1.3fr)_minmax(0,1.6fr)_minmax(0,1.6fr)] md:items-center"
                >
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

                  <div>
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

                  <div className="flex flex-wrap items-center gap-2">
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
                        ) : null}
                      </>
                    ) : (
                      <span className="text-[11px] font-medium text-slate-400">
                        {teamRolesT(locale, 'invitationNone')}
                      </span>
                    )}
                  </div>

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
                </div>
              );
            })}
          </div>
        )}

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
    </>,
  );
}
