/// Sous-roles manager assignables depuis l'ecran Equipe (#9334).
///
/// Source de verite : la liste d'acceptation de l'API
/// (`StoreEmployeeRequest` / `UpdateEmployeeRequest`, desormais derivee de
/// `RoleInvitationService::ASSIGNABLE_MANAGER_ROLES`), MOINS `principal` qui
/// n'est pas assignable — c'est le proprietaire du tenant, et le web ne le
/// propose pas non plus.
///
/// Un client ne doit JAMAIS proposer un role que l'API refuse en 422. La
/// parite entre ce fichier, `front/web/src/lib/i18n/team-roles.ts`
/// (`TEAM_ASSIGNABLE_MANAGER_ROLES`) et la constante PHP est donc verrouillee
/// par `dev-hub/tools/check-team-role-parity.py`.
const List<String> kAssignableManagerRoles = <String>[
  'rh',
  'dept',
  'comptable',
  'superviseur',
  'marketing',
];
