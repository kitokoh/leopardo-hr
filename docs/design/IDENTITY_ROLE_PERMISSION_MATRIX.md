# Matrice Identité × Rôle × Permission

> **Source de vérité** : ce document est **dérivé du code**, pas inventé. Chaque cellule
> provient de `PlatformRole::permissions()` (`api/app/Core/Tenant/Domain/Enums/PlatformRole.php:60-140`)
> ou de `Employee::hasManagerRole()` (`api/app/Core/Auth/Domain/Models/Employee.php:246-254`).
> **HEAD** : `31ef78e04` · vérifié le 31 octobre 2026.

---

## 1. Les quatre populations et leurs guards

| Population | Modèle | Guard | Provider | Table | Schéma |
|---|---|---|---|---|---|
| Collaborateur interne Leopardo | `SuperAdmin` | `super_admin_api` | `super_admins` | `super_admins` | `public` |
| Employé / admin d'entreprise | `Employee` | `sanctum` (défaut) | `employees` | `employees` | **par tenant** |
| Compte public générique | `User` | `user_api` | `users` | `users` | `public` |
| Client grand public travel | `TravelCustomerAccount` | `travel_customer` | `travel_customers` | `travel_customer_accounts` | `public` |

**Preuve** : `api/config/auth.php:40-118`.

> ⚠️ Les quatre guards sont **disjoints** : un token émis par l'un n'ouvre aucune route
> d'un autre. C'est le principal rempart anti-escalade inter-populations, et il est
> **structurel** (pas une vérification applicative qu'on pourrait oublier).

---

## 2. Rôles de plateforme × permissions (31 permissions)

Légende : ✅ accordée · — refusée

| Permission | super_admin | admin | support | finance | ops | marketing |
|---|:--:|:--:|:--:|:--:|:--:|:--:|
| `companies.view` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `companies.manage` | ✅ | ✅ | — | — | — | — |
| `companies.provision` | ✅ | ✅ | — | — | — | — |
| `billing.view` | ✅ | ✅ | — | ✅ | — | — |
| `billing.manage` | ✅ | ✅ | — | ✅ | — | — |
| `plans.view` | ✅ | ✅ | — | ✅ | — | — |
| `plans.manage` | ✅ | ✅ | — | ✅ | — | — |
| `users.view` | ✅ | ✅ | ✅ | — | — | — |
| `users.manage` | ✅ | ✅ | ✅ | — | — | — |
| `impersonate` | ✅ | ✅ | ✅ | — | — | — |
| `killswitch.manage` | ✅ | ✅ | — | — | ✅ | — |
| `observability.view` | ✅ | ✅ | ✅ | — | ✅ | — |
| `metrics.view` | ✅ | ✅ | ✅ | ✅ | ✅ | — |
| `support.manage` | ✅ | ✅ | ✅ | — | — | — |
| `announcements.manage` | ✅ | ✅ | — | — | — | ✅ |
| `crm.view` | ✅ | ✅ | — | — | — | ✅ |
| `showcase.manage` | ✅ | ✅ | — | — | — | ✅ |
| `edge.manage` | ✅ | ✅ | — | — | ✅ | — |
| **`payments.view`** | ✅ | ✅ | **—** | ✅ | — | **—** |
| **`payments.manage`** | ✅ | ✅ | **—** | ✅ | — | **—** |
| `infra.manage` | ✅ | ✅ | — | — | ✅ | — |
| `webhooks.manage` | ✅ | ✅ | — | — | ✅ | — |
| `settings.manage` | ✅ | ✅ | — | — | — | — |
| `payroll.view` | ✅ | ✅ | — | ✅ | ✅ | — |
| **`team.manage`** | ✅ | **—** | — | — | — | — |
| `pages.view` | ✅ | ✅ | ✅ | — | ✅ | — |
| `pages.manage` | ✅ | ✅ | — | — | — | — |
| `marketing_os.view` | ✅ | ✅ | — | — | — | ✅ |
| `search_intel.view` | ✅ | ✅ | — | — | ✅ | ✅ |
| `search_intel.manage` | ✅ | ✅ | — | — | — | ✅ |


**Trois lignes sont les garde-fous les plus importants :**

1. **`team.manage`** — seul `super_admin` la porte (`PlatformRole.php:65` : `Admin` =
   `allExcept([TeamManage])`). Un `admin` ne peut donc **pas** distribuer les rôles, y
   compris le sien. C'est la protection anti-auto-promotion.
2. **`payments.*`** — ni `support` ni `marketing` (BC-38, #8741). C'est le scénario #1 de
   la matrice de tests, **vérifié par exécution** (PASS).
3. **`impersonate`** — `support` l'a (c'est son métier), mais **toujours** avec raison
   obligatoire, expiration et révocation de token (§2.6 du rapport d'audit).

**Tests exécutés** : `test_support_role_cannot_manage_the_platform_team`,
`test_marketing_role_has_no_payment_administration_access` → **PASS**.

---

## 3. Rôles de tenant

Deux colonnes sur `employees` — il n'y a **pas** de table de rôles.

### 3.1 `role` (niveau)

| Valeur | Sens | `isManager()` | Portée |
|---|---|---|---|
| `manager` | Responsable d'entreprise | ✅ | Dépend de `manager_role` |
| `employee` | Collaborateur en self-service | — | Ses propres données |
| `ordinary` | Compte sans entreprise | — | Self-service seul (`TenantMiddleware.php:60`) |

### 3.2 `manager_role` (sous-rôle, uniquement si `role = manager`)

| Valeur | Périmètre réel | Preuve |
|---|---|---|
| `principal` | **Tout**, implicitement — y compris tous les modules sans grant | `Employee::hasModuleGrant()` : `isPrincipal()` → `true` (`:571-575`) |
| `rh` | Lecture sur toutes les ressources sans assignation | `hasResourceAccess()` : `isHr() && view` → `true` (`:640-643`) |
| `comptable` | Company-wide (aucun scope department) | `isDepartmentScoped()` = `false` |
| `marketing` | Company-wide | idem |
| `dept` | **Son département uniquement** — fail-closed si pas de département | `scopeVisibleToManager()` : `department_id ?? -1` (`:378-390`) |
| `superviseur` | **Ses subordonnés directs uniquement** (`manager_id`) + lui-même | `managesEmployeeDirectly()` (`:290-300`) |

**Tests exécutés (PASS)** :
- `test_department_manager_without_department_sees_nobody` — 0 visible sur 3, pas 3.
- `test_module_grant_lookup_is_fail_closed` — sans grant, aucun module.

### 3.3 Grants de modules (`employee_module_grants`)

Table `employee_module_grants` (`2026_09_19_001401_7761_create_employee_module_grants.php`).

- Le `principal` a **tout** sans ligne en base.
- Tout autre rôle n'a **que** ses grants explicites — **fail-closed**.
- Le middleware `api.manager:<roles>,module:<key>` accepte **rôle OU grant**
  (`EnsureApiManagerMiddleware`), ce qui permet à un `employee` non-manager de recevoir
  l'accès à un module sans changer son `role`.

> ⚠️ **Écart L2** : **aucune garde « on ne délègue pas plus qu'on ne possède »**. Un
> `principal` peut accorder n'importe quel module à n'importe qui, y compris à un
> `comptable` le module `marketing`. Ce n'est pas une escalade (le principal est
> propriétaire), mais c'est une **absence de moindre privilège dans la délégation**.

### 3.4 Affectations de ressources (`employee_resource_assignments`)

Niveaux : `view` < `manage` (?) — voir `EmployeeResourceAssignment::levelRank()`.

Règle de **progressivité** (`Employee.php:600-620`) :
- Avant la **première** affectation d'un type de ressource dans l'entreprise → comportement
  historique (tout le monde du tenant).
- Dès qu'**une** affectation existe pour ce type → **fail-closed** pour les non-affectés.

C'est un mécanisme élégant : il permet d'introduire le scoping sans casser l'existant.

---

## 4. Modèle cible : ce qui existe vs ce qu'il faudrait

Le modèle visé distingue **Identity / Membership / Role / Permission / Scope /
Entitlement / Audit**. Voici la correspondance honnête.

| Concept cible | Existe aujourd'hui ? | Où | Écart |
|---|---|---|---|
| **Identity** | ⚠️ **4 modèles au lieu de 2** | `users`, `employees`, `super_admins`, `travel_customer_accounts` | 3ᵉ identité publique à résorber |
| **Membership** | ✅ Table dédiée | `user_employee_links` (`unique(user_id, company_id)`) | **Non utilisée au login** (§1.2) |
| **Role** | ⚠️ Enums en code | `PlatformRole`, `employees.role`, `manager_role` | Aucune donnée → pas de rôle personnalisé |
| **Permission** | ✅ Enums + grants + policies | `PlatformPermission`, `employee_module_grants`, `Gate::define` | Pas de permission « contextuelle » par ressource |
| **Scope** | ✅ **Très solide** | Schéma PG + `BelongsToCompany` + `search_path` + fail-closed | — |
| **Entitlement** | ⚠️ Partiel | `companies.features` JSONB + `metadata.modules` | Pas d'entitlement **par personne** au-delà des grants |
| **Audit** | ✅ | `audit_logs` + `platform_impersonation_sessions` | — |

### Verdict

La séparation **Scope / Permission / Audit** est **déjà** conforme au modèle cible et
c'est la partie la plus difficile — elle est faite. Ce qui manque est **une couche
données** par-dessus les enums (Role, Entitlement) et **la consommation de Membership au
login**. Ce sont des ajouts, pas une refonte.

---

## 5. Constat sur « aucune troisième identité »

La décision du propriétaire est claire : `users` (public) et `employees` (tenant),
reliés par le téléphone, **aucune troisième identité**.

Le code contient **quatre** modèles d'identité. Il faut distinguer :

- `super_admins` — **n'est pas** une troisième identité au sens gênant : c'est une
  population **interne** (collaborateurs Leopardo), avec ses propres guards, et elle ne
  chevauche pas les deux autres. Elle est acceptable.
- `travel_customer_accounts` — **est** la troisième identité gênante : c'est une
  population **externe** (consommateurs), qui devrait être un `users`, avec une
  relationship vers les tenants via les réservations.

**C'est ce seul point que l'ADR doit trancher.** Voir
`docs/design/ADR-IDENTITE-CLIENTS-PUBLICS.md`.

---

## 6. Anti-escalade : ce qui est gardé, ce qui ne l'est pas — **tranché par le code**

> **Révision 3.** La révision 2 affirmait simultanément que les permissions sont
> « anti-escalade » et qu'il manque « une garde déléguer ≤ ses droits ». Ces deux
> affirmations ne peuvent pas être vraies ensemble. Voici ce que fait réellement le code.

### 6.1 La garde qui existe — `UpdateEmployeeRequest.php:122-140`

```php
// :122-130 — seul un `principal` peut changer role/manager_role
if (($this->has('role') || $this->has('manager_role')) &&
    $user?->isManager() && ! $user->hasManagerRole('principal')) {
    $validator->errors()->add('role', __('errors.EMPLOYEE_ROLE_CHANGE_MANAGER_ONLY'));
}

// :135-140 — personne ne peut promouvoir vers `principal`
if ($this->input('manager_role') === 'principal' && $user?->isManager()) {
    $validator->errors()->add('manager_role', __('errors.EMPLOYEE_PROMOTE_PRINCIPAL_FORBIDDEN'));
}
```

Complétée par `EmployeePolicy::update` (`:42-54`) : `company_id` identique obligatoire, puis
`hasManagerRole('principal', 'rh')`.

### 6.2 Ce que cela garantit — et ce que cela ne garantit pas

| Affirmation | Verdict | Preuve |
|---|---|---|
| Un `rh`/`comptable`/`dept`/`marketing` ne peut **pas** changer un rôle | ✅ **vrai** | garde `:122` exige `principal` |
| Un `principal` peut attribuer n'importe lequel des 11 autres rôles | ✅ **vrai** | aucune comparaison de capacités |
| Il ne peut attribuer **que** des rôles dont il détient les capacités | ❌ **faux** | **aucun plafond d'inclusion** dans le code |
| Un tenant peut nommer un **second** `principal` | ❌ **faux** | garde `:135` frappe aussi le `principal` |
| Le **dernier** `principal` est protégé | ❌ **faux côté tenant** | aucun compteur équivalent (`PlatformTeamController.php:104-110` existe **côté plateforme seulement**) |
| Le changement de `manager_role` est **tracé** | ❌ **faux** | `audit_logs` couvre les grants et `SuperAdmin`, pas `manager_role` |

### 6.3 Les trois écarts, énoncés précisément

**Écart A — pas de plafond de délégation.**
Un `principal` sans le module `accounting` peut nommer un `comptable`, qui l'aura. La règle
`capabilities(role) ⊆ capabilities(delegator)` n'est écrite nulle part.

**Écart B — aucun successeur possible.**
La garde `:135` vise `manager_role === 'principal'`, sans distinguer « je promeus quelqu'un »
de « je me promeus ». Résultat : un tenant à un seul `principal` ne peut **jamais** en avoir
deux. Le « transfert de responsabilité » demandé par le propriétaire est aujourd'hui
**impossible**, alors qu'il est **possible côté plateforme** (autoriser un second, puis
protéger le dernier).

**Écart C — aucun journal.**
`EmployeeModuleGrant` est `Auditable`. Le changement de `manager_role`, qui est **plus**
sensible, ne l'est pas.

### 6.4 Asymétrie plateforme / tenant

| Mécanisme | Plateforme | Tenant |
|---|---|---|
| Matrice de permissions fermée | ✅ 31 permissions | ❌ 12 `manager_role` figés |
| Plafond de délégation | ✅ `allExcept([TeamManage])` | ❌ absent |
| Protection du dernier détenteur | ✅ `LAST_SUPER_ADMIN_REQUIRED` | ❌ absent |
| Second détenteur nommable | ✅ | ❌ **interdit** |
| Journal du changement | ✅ `Auditable` | ❌ absent |

**Conclusion** : l'anti-escalade est **réelle côté plateforme**, **absente côté tenant**. Ce
n'est pas une faille exploitable de l'extérieur — un `principal` est déjà l'administrateur de
son entreprise — mais c'est une **contrainte de conception manquante** qui rend impossible la
délégation fine que la vision demande, et impossible le transfert de responsabilité.

### 6.5 Modèle cible — les règles à écrire

| Règle | Effet | Test obligatoire |
|---|---|---|
| `capabilities(target) ⊆ capabilities(actor)` | pas de délégation au-dessus de soi | `422 DELEGATION_EXCEEDS_ACTOR` |
| Plafond de **périmètre** | un `dept` ne délègue que dans son département | refus hors périmètre |
| `principal`/`super_admin` hors graphe de délégation | transfert explicite seulement (double confirmation + audit + notification) | `403` sur `PUT` simple |
| Catalogue **fermé** de permissions tenant | pas d'écriture libre | rôle avec permission inconnue ⇒ `422` |
| Journal obligatoire de tout changement de rôle | traçabilité | ligne `audit_logs` attendue |

Ces règles sont l'objet du lot **L5** (rôles personnalisés) du plan, et partiellement de
**L3** (successeur `principal`). Le scénario #11 de la matrice de sécurité — « rôle personnalisé
ne dépassant pas les droits de son créateur » — est le critère d'acceptation de L5.
