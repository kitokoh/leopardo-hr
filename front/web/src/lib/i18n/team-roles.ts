/**
 * team-roles.ts — repli FR + helpers de rôles/erreurs pour l'espace client
 * « Collaborateurs et rôles » (#7555).
 *
 * Source de vérité des traductions : `shared/i18n/locales/{fr,en,ar,tr}.json`
 * (namespace `teamRoles.*`, 62 clés). Les catalogues consommés par `t()`
 * (`front/web/src/lib/i18n/locales/*.json`) sont régénérés par
 * `shared/i18n/sync/sync-web.js` — hors de cette branche (un autre agent
 * centralise la régénération après fusion). Ce module porte donc le REPLI FR
 * utilisé en 3ᵉ argument de `t(locale, clé, repli)` : `t()` retombe déjà sur le
 * FR quand une clé manque pour une autre locale, un seul repli suffit donc.
 * Dès que le catalogue web est régénéré, ces replis deviennent inertes (la clé
 * `teamRoles.*` prime).
 *
 * Pourquoi ce repli n'est pas inline dans le JSX : le garde CI PA2-I18N-014
 * (`dev-hub/tools/check-i18n-diff.js`) refuse tout nouveau littéral de texte
 * visible ajouté dans un `.tsx` de `src/app/` — il ne distingue pas un repli de
 * traduction d'une chaîne en dur. Le texte reste centralisé ici (même
 * mécanique que `src/lib/i18n.ts`, catalogue inline non scanné pour la même
 * raison) et n'est jamais recopié dans les composants.
 */
import type { AppLocale, StoredAuthUser } from '@/lib/i18n';
import { t } from '@/lib/i18n/locale-catalog';

export const TEAM_ROLES_FR = {
  title: 'Collaborateurs et rôles',
  subtitle: "Invitez vos collaborateurs, attribuez les rôles et suivez l'état des invitations.",
  menuLabel: 'Collaborateurs et rôles',
  reservedTitle: 'Accès réservé',
  reservedBody:
    "Cette page est réservée aux managers de l'entreprise. Demandez à un manager principal de vous y donner accès.",
  loading: 'Chargement des collaborateurs…',
  loadError: 'Impossible de charger les collaborateurs.',
  retry: 'Réessayer',
  searchLabel: 'Rechercher un collaborateur',
  searchPlaceholder: 'Nom, e-mail ou matricule',
  searchAction: 'Rechercher',
  total: 'Total : {count}',
  shown: '{count} affiché(s)',
  listEmpty: 'Aucun collaborateur pour le moment.',
  colName: 'Collaborateur',
  colRole: 'Rôle',
  colInvitation: 'Invitation',
  colActions: 'Actions',
  youBadge: 'Vous',
  roleEmployee: 'Employé',
  roleManager: 'Manager',
  rolePrincipal: 'Manager principal',
  roleRh: 'Manager RH',
  roleDept: 'Manager département',
  roleComptable: 'Manager comptable',
  roleSuperviseur: 'Manager superviseur',
  roleMarketing: 'Manager marketing',
  roleSelectLabel: 'Rôle du collaborateur',
  roleUpdated: 'Rôle mis à jour.',
  selfRoleLocked: 'Vous ne pouvez pas modifier votre propre rôle.',
  invitationPending: 'En attente',
  invitationAccepted: 'Acceptée',
  invitationExpired: 'Expirée',
  invitationNone: 'Aucune invitation',
  invitationSentAt: 'Envoyée le {date}',
  expiresAt: 'Expire le {date}',
  resend: "Renvoyer l'invitation",
  resendSuccess: 'Invitation renvoyée.',
  addTitle: 'Ajouter un collaborateur',
  addToggle: 'Ajouter un collaborateur',
  addSubmit: "Envoyer l'invitation",
  addSuccess: 'Invitation envoyée au collaborateur.',
  fieldFirstName: 'Prénom',
  fieldLastName: 'Nom',
  fieldEmail: 'E-mail professionnel',
  fieldDepartment: 'Département (optionnel)',
  fieldRole: 'Rôle',
  formIncomplete: 'Renseignez le prénom, le nom et un e-mail valide.',
  paginationPrev: 'Précédent',
  paginationNext: 'Suivant',
  paginationStatus: 'Page {page} sur {last}',
  archiveTitle: 'Archiver ce collaborateur ?',
  archiveAction: 'Archiver',
  archiveConfirm: 'Confirmer',
  archiveSuccess: 'Collaborateur archivé.',
  cancel: 'Annuler',
  revoke: "Révoquer l'invitation",
  revokeTitle: 'Révoquer cette invitation ?',
  revokeConfirm: 'Confirmer',
  revokeSuccess: 'Invitation révoquée.',
  modulesToggle: 'Modules délégués',
  modulesTitle: 'Modules délégués',
  modulesHint:
    'Composez les modules accessibles à ce collaborateur — ce qui n\u2019est pas coché est révoqué. Le manager principal a déjà accès à tout.',
  modulesSave: 'Enregistrer les modules',
  modulesSaved: 'Modules délégués mis à jour.',
  modulesLoadError: 'Impossible de charger les modules délégués.',
  moduleMarketing: 'Marketing',
  moduleAccounting: 'Comptabilité',
  moduleSupport: 'Tickets support',
  moduleCrm: 'CRM',
  moduleShowcase: 'Site vitrine',
  moduleHr: 'RH (équipe, pointage, paie)',
  moduleBillingView: 'Facturation (lecture)',
  errorManagerRoleRequired:
    'Choisissez le type de manager (RH, département, comptable, superviseur, marketing).',
  errorPrincipalForbidden: 'Seul un manager principal peut créer un manager principal.',
  errorRoleChangeForbidden: 'Seul un manager principal peut modifier un rôle.',
  errorPromotePrincipalForbidden:
    'La promotion en manager principal est réservée au super administrateur.',
  errorInvitationAccepted: 'Cette invitation a déjà été acceptée.',
  errorActionFailed: "L'action a échoué. Réessayez.",
} as const;

export type TeamRolesKey = keyof typeof TEAM_ROLES_FR;

/** Traduit une clé `teamRoles.*` avec repli FR (cf. en-tête du module). */
export function teamRolesT(locale: AppLocale, key: TeamRolesKey): string {
  return t(locale, `teamRoles.${key}`, TEAM_ROLES_FR[key]);
}

export type TeamRoleValue = 'employee' | 'manager';

export type TeamManagerRole =
  | 'principal'
  | 'rh'
  | 'dept'
  | 'comptable'
  | 'superviseur'
  | 'marketing';

/**
 * Types de manager proposables dans l'UI — `principal` est VOLONTAIREMENT
 * absent : l'API réserve sa création à un manager principal
 * (`EMPLOYEE_PRINCIPAL_MANAGER_CREATION_FORBIDDEN`) et refuse toute promotion
 * en principal par PATCH (`EMPLOYEE_PROMOTE_PRINCIPAL_FORBIDDEN`).
 */
export const TEAM_ASSIGNABLE_MANAGER_ROLES: readonly TeamManagerRole[] = [
  'rh',
  'dept',
  'comptable',
  'superviseur',
  'marketing',
];

const MANAGER_ROLE_LABEL_KEYS: Record<TeamManagerRole, TeamRolesKey> = {
  principal: 'rolePrincipal',
  rh: 'roleRh',
  dept: 'roleDept',
  comptable: 'roleComptable',
  superviseur: 'roleSuperviseur',
  marketing: 'roleMarketing',
};

export type TeamRoleOption = { value: string; label: string };

/** Options de `select` : « Employé » + un type de manager par valeur composée. */
export function teamRoleOptions(locale: AppLocale): TeamRoleOption[] {
  return [
    { value: 'employee', label: teamRolesT(locale, 'roleEmployee') },
    ...TEAM_ASSIGNABLE_MANAGER_ROLES.map((managerRole) => ({
      value: `manager:${managerRole}`,
      label: teamRolesT(locale, MANAGER_ROLE_LABEL_KEYS[managerRole]),
    })),
  ];
}

export function teamManagerRoleLabel(locale: AppLocale, managerRole?: string | null): string {
  if (managerRole && managerRole in MANAGER_ROLE_LABEL_KEYS) {
    return teamRolesT(locale, MANAGER_ROLE_LABEL_KEYS[managerRole as TeamManagerRole]);
  }

  return teamRolesT(locale, 'roleManager');
}

/** Libellé lisible d'un rôle (liste des collaborateurs). */
export function teamRoleLabel(
  locale: AppLocale,
  role?: string | null,
  managerRole?: string | null,
): string {
  if (role === 'employee') {
    return teamRolesT(locale, 'roleEmployee');
  }

  if (role === 'manager') {
    return managerRole
      ? teamManagerRoleLabel(locale, managerRole)
      : teamRolesT(locale, 'roleManager');
  }

  // Rôle global hors périmètre (admin, super_admin…) : on l'affiche tel quel.
  return role && role.trim() ? role : teamRolesT(locale, 'roleEmployee');
}

/** Valeur de `select` correspondant au rôle courant d'un collaborateur. */
export function teamRoleOptionValue(role?: string | null, managerRole?: string | null): string {
  if (role === 'manager' && managerRole) {
    return `manager:${managerRole}`;
  }

  return 'employee';
}

/** Inverse de `teamRoleOptionValue` : construit le payload attendu par l'API. */
export function parseTeamRoleOption(value: string): {
  role: TeamRoleValue;
  manager_role: TeamManagerRole | null;
} {
  if (!value.startsWith('manager:')) {
    return { role: 'employee', manager_role: null };
  }

  return { role: 'manager', manager_role: value.slice('manager:'.length) as TeamManagerRole };
}

/**
 * Accès à l'écran : l'API n'ouvre la gestion d'équipe et des invitations qu'aux
 * managers (Policies `viewAny`/`manageInvitations`). On reflète la session
 * stockée ici, et un 403 API reste traité comme « accès réservé ».
 */
export function isManagerUser(user: StoredAuthUser | null): boolean {
  return user?.role === 'manager' || user?.role === 'admin' || user?.role === 'super_admin';
}

/** Codes API → clés `teamRoles.*` (message compréhensible côté client). */
const API_ERROR_KEYS: Record<string, TeamRolesKey> = {
  EMPLOYEE_MANAGER_ROLE_REQUIRED: 'errorManagerRoleRequired',
  EMPLOYEE_PRINCIPAL_MANAGER_CREATION_FORBIDDEN: 'errorPrincipalForbidden',
  EMPLOYEE_ROLE_CHANGE_MANAGER_ONLY: 'errorRoleChangeForbidden',
  EMPLOYEE_PROMOTE_PRINCIPAL_FORBIDDEN: 'errorPromotePrincipalForbidden',
  INVITATION_ALREADY_ACCEPTED: 'errorInvitationAccepted',
};

/**
 * Message d'erreur affichable : les codes `EMPLOYEE_*` / `INVITATION_*` sont
 * traduits, sinon le message déjà localisé de l'API (Accept-Language) est
 * conservé tel quel.
 */
export function teamRolesErrorMessage(
  locale: AppLocale,
  error: unknown,
  fallbackKey: TeamRolesKey = 'errorActionFailed',
): string {
  const candidate = (error ?? {}) as { code?: unknown; message?: unknown };
  const code = typeof candidate.code === 'string' ? candidate.code : '';
  const message = typeof candidate.message === 'string' ? candidate.message : '';

  const mappedKey =
    API_ERROR_KEYS[code] ??
    Object.keys(API_ERROR_KEYS).find((knownCode) => message.includes(knownCode));

  if (mappedKey) {
    return teamRolesT(locale, mappedKey);
  }

  return message.trim() ? message : teamRolesT(locale, fallbackKey);
}
