# Mini-spécification — Issue #3268

## Objectif

Corriger le parcours de détail et d’impersonation des utilisateurs administratifs en évitant de traiter un identifiant `super_admins` comme un identifiant `public.users`.

## Correction

`PlatformUserController` enrichit la liste et le détail `/platform/users` avec la société et l’employé liés au compte public dont l’email correspond exactement, après normalisation minuscule. La résolution expose uniquement `id`, `name`, `employee_id` et `link_status`, et prend le lien le plus récent. Si les tables publiques ne sont pas disponibles, la réponse reste compatible avec `company: null`.

`UsersView.vue` conserve cette liaison lors du mapping de la liste et recharge le détail via `/platform/users/{id}`, le même espace d’identifiants que la liste. L’appel croisé `/admin/users/{id}` est supprimé ; l’impersonation peut ainsi lire `company.employee_id` depuis le même contrat.

## Critères d’acceptation

1. La liste et le détail super-admin utilisent `/platform/users`.
2. La liaison société/employé est résolue par email et non par égalité d’ID entre tables.
3. Les comptes sans liaison restent valides et renvoient `company: null`.
4. Le dashboard ne génère plus de 404 silencieux vers `/admin/users/{super_admin_id}`.
5. PHP syntax check, dashboard lint/build et `git diff --check` passent.

## Trace Spec Kit

Issue : #3268  
Branche : `fix/3268-admin-users-source-alignment`  
Date : 2026-08-15
