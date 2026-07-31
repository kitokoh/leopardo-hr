# Role-Based Access Control (RBAC) — Leopardo RH

Leopardo RH implements a granular RBAC system to ensure that users have exactly the permissions they need to perform their roles, and nothing more.

Cette page a été réalignée le 2026-07-21 (PA2-SEC-005) sur l'implémentation réelle du code après correction du scope `dept`/`superviseur` (PA2-SEC-002/003). Les sections ci-dessous décrivent ce que le code applique aujourd'hui, pas un modèle RBAC générique aspirationnel.

## 👥 Deux couches d'authentification distinctes

Leopardo RH n'a **pas** de super-admin unifié qui traverserait tous les tenants via le même modèle. Deux couches complètement séparées coexistent :

1. **Platform Admin** (`api/app/Modules/Platform`, guard Sanctum `super_admin_api`, préfixe de routes `/platform/*`, middleware `auth:super_admin_api`). Isolé de la table `employees`/du modèle `Employee` décrit ci-dessous ; gère les tenants (billing, activation client, métriques plateforme), pas les données RH d'une entreprise.
2. **Employee** (`App\Core\Auth\Domain\Models\Employee`, guard Sanctum par défaut, middleware `auth:sanctum` + `tenant`). C'est le modèle décrit dans tout le reste de ce document — un employé, manager ou non, toujours rattaché à un `company_id` (tenant) unique.

Le tableau générique "Platform Admin = Global (All Tenants)" d'une ancienne version de ce document était trompeur : il n'existe aucun rôle `Employee` avec une portée multi-tenant. Le Platform Admin est un système d'authentification à part, pas une valeur de `manager_role`.

## 🧩 Champs qui déterminent l'accès d'un `Employee`

| Champ | Valeurs réelles | Rôle |
| :--- | :--- | :--- |
| `role` | `employee`, `manager` | Distingue un compte self-service (accès à ses propres données uniquement) d'un compte manager (accès étendu, sous-typé par `manager_role`). |
| `manager_role` | `principal`, `rh`, `comptable`, `marketing`, `dept`, `superviseur` (nullable si `role = employee`) | Sous-type du rôle manager. Détermine la **portée** (voir tableau ci-dessous), pas seulement un libellé. |
| `department_id` | FK nullable vers `departments` | Utilisé pour scoper `manager_role = dept`. |
| `manager_id` | FK nullable vers `employees` (auto-référence) | Hiérarchie directe ; utilisé pour scoper `manager_role = superviseur` (l'équipe assignée = les employés dont `manager_id` pointe vers ce superviseur). |

Voir `App\Core\Auth\Domain\Models\Employee` : `isManager()`, `hasManagerRole()`, `isPrincipal()/isHr()/isMarketing()/isComptable()/isDept()/isSuperviseur()`.

## 🎯 Portée réelle par `manager_role` (PA2-SEC-002 / PA2-SEC-003)

| `manager_role` | Portée | Implémentation |
| :--- | :--- | :--- |
| `principal` | Toute l'entreprise (tenant-wide) | Company-wide, aucun scoping supplémentaire. |
| `rh` | Toute l'entreprise (tenant-wide) | Idem. |
| `comptable` | Toute l'entreprise (tenant-wide) | Idem. |
| `marketing` | Toute l'entreprise (tenant-wide) | Idem. |
| `dept` | **Un seul département** — celui de `department_id` | `Employee::isDepartmentScoped()` / `managesDepartmentOf()` / `scopeVisibleToManager()`. **Fail-closed** : un `dept` manager sans `department_id` défini ne voit aucun employé (pas toute l'entreprise). |
| `superviseur` | **Équipe assignée directe uniquement** — les employés dont `manager_id` pointe vers ce superviseur (plus lui-même) | `Employee::isSupervisorScoped()` / `managesEmployeeDirectly()` / `scopeVisibleToManager()`. **Fail-closed** : un superviseur sans aucun rapport direct (`manager_id`) ne voit que lui-même, jamais toute l'entreprise. |

`Employee::isTeamScoped()` retourne `true` pour `dept` et `superviseur` uniquement — ce sont les deux seuls rôles avec une portée restreinte inférieure à l'entreprise. `Employee::managesTeamMemberOf($target)` dispatch vers le bon check (`managesDepartmentOf` pour `dept`, `managesEmployeeDirectly` pour `superviseur`) et sert de point d'entrée unique utilisé par policies et contrôleurs.

`Employee::scopeVisibleToManager(Builder $query, Employee $actor)` est le scope Eloquent réutilisable qui applique la même contrainte directement sur une requête (utilisé pour les listings, pas seulement les checks record-par-record).

## 🏗 Couche d'autorisation réelle

### 1. Middlewares
- `auth:sanctum` : authentifie l'employé (guard par défaut).
- `tenant` : isole les données par `company_id`.
- `App\Http\Middleware\EnsureApiManagerMiddleware` (alias de route `api.manager`, ex. `api.manager:rh,principal`) : exige `role = manager`, puis optionnellement un ou plusieurs `manager_role` autorisés (`hasManagerRole(...$roles)`). Ne fait **aucun** scoping département/équipe lui-même — c'est le rôle des Policies/contrôleurs ci-dessous.
- Platform Admin : `auth:super_admin_api` (guard Sanctum séparé, voir plus haut).

### 2. Policies (Laravel authorization)
Les Policies appliquent le scoping `dept`/`superviseur` record-par-record via `isTeamScoped()` + `managesTeamMemberOf()` :

- `App\Policies\EmployeePolicy::view()` — un manager `dept`/`superviseur` ne peut voir un employé hors de sa portée.
- `App\Policies\DepartmentPolicy::view()`/`update()` — un manager `dept` est limité à son propre département ; un `superviseur` n'a pas de département propre et reste company-wide pour les listings de département (documenté explicitement dans le code : sa portée se définit par employés assignés, pas par département).
- `App\Policies\AttendancePolicy::viewForEmployee()` — même pattern que `EmployeePolicy`.
- `App\Policies\EvaluationPolicy::managesEvaluatedEmployee()` (privée, utilisée par `view`/`update`/`delete`/`submit`) — même pattern, appliqué aux évaluations RH.

### 3. Contrôleurs / listings (scoping de requête)
Au-delà des checks record-par-record des Policies, les listings utilisent `isTeamScoped()`/`managesTeamMemberOf()`/`scopeVisibleToManager()` directement dans la requête pour filtrer une collection entière plutôt que de tout charger puis rejeter :

- `App\Modules\HR\Interfaces\Api\V1\Controllers\EmployeeController` (listing employés)
- `App\Modules\HR\Interfaces\Api\V1\Controllers\EvaluationController` (listing + actions évaluations)
- `App\Modules\Planning\Interfaces\Api\V1\Controllers\ScheduleController` (assignation d'employés à un planning)
- `App\Modules\Attendance\Interfaces\Api\V1\Controllers\AttendanceController` (consultation pointages équipe)
- `App\Modules\Attendance\Infrastructure\Services\AttendanceMonthlyReportService` / `AttendanceAnomalyService` (rapports mensuels/anomalies — prennent l'`Employee` agissant plutôt qu'un `departmentId` brut, pour appliquer le même scope `dept`/`superviseur`)

## 🧪 Tests de régression RBAC (PA2-SEC-004)

- `api/tests/Feature/Security/DepartmentScopedRbacTest.php` — scope `manager_role = dept`.
- `api/tests/Feature/Security/SupervisorScopedRbacTest.php` — scope `manager_role = superviseur` (miroir du précédent).
- `api/tests/Feature/Security/AdminMiddlewareRbacTest.php` — middleware admin/manager.
- `api/tests/Feature/EmployeesRbacTest.php` — RBAC employé générique.

## 🔒 Security Principles
- **Fail-closed par défaut** : un manager team-scoped (`dept`/`superviseur`) mal configuré (pas de département, pas de rapport direct) ne voit **personne**, jamais toute l'entreprise. Voir les commentaires `Employee::managesDepartmentOf()`/`managesEmployeeDirectly()`.
- **Isolation tenant** : toute requête `Employee`/RH passe par le middleware `tenant`, scopée sur `company_id`. Le Platform Admin est un système d'authentification distinct (`auth:super_admin_api`), jamais un rôle `Employee` avec portée multi-tenant.
- **Auditable** : voir `App\Core\Auth\Domain\Models\AuditLog`.

---

Pour plus de détails, voir [Security Policy](SECURITY.md) et `docs/archive/PLAN_ACTION2/02_BACKLOG_ATOMIQUE.md` (tickets `PA2-SEC-001` à `PA2-SEC-005`).
