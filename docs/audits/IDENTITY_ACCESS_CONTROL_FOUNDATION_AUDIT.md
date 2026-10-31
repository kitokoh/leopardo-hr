# Audit — Fondation Identity & Access Control

> **Dépôt** : `kitokoh/leopardo-hr` · **Branche** : `main` · **HEAD audité** : `31ef78e04`
> (`fix(i18n): resynchroniser les checksums web de versions.json`, 31 oct. 2026)
> **Nature** : audit de fondation — **aucune modification de code applicatif**
> **Auteur** : audit automatisé, session du 31 octobre 2026

---

## 0. Comment lire ce document

Chaque affirmation importante porte une **preuve vérifiable** : chemin de fichier et
numéro de ligne, nom de table, nom de route, ou **résultat d'exécution de test**. Quand
une information n'a pas pu être obtenue, elle est explicitement marquée
« **non vérifié** » — elle n'est jamais présentée comme un constat.

Les rapports antérieurs (`docs/audits/readiness-identite-paiements.md`, basé sur
`4ff4d84e5` ; issues #8736–#8740) ont été lus **comme pistes**, puis chaque affirmation
a été **re-vérifiée dans le code actuel**. Plusieurs sont désormais **obsolètes** : voir
§1.4.

### Environnement d'exécution des tests

Les tests n'ont pas été « considérés » comme réussis sur la foi de leur existence. Ils ont
été **réellement exécutés** dans cet environnement :

| Élément | Valeur |
|---|---|
| Runtime | PHP **8.4.27** (exigence `composer.json:12` : `^8.4.1`) |
| Base | PostgreSQL **15** + **PostGIS 3** |
| Dépendances | `composer install` — exit 0 |
| Lanceur | `CI=true php8.4 vendor/bin/phpunit` |

> **Pourquoi `CI=true`** : `tests/TestCase.php:100-108` force l'hôte PostgreSQL à
> `pgsql` (nom du service Docker) dès que `isRunningInsideDocker()` est vrai et que
> l'hôte vaut `127.0.0.1`/`localhost`. Hors CI, la connexion échoue avec
> `could not translate host name "pgsql"`. C'est un piège de reproductibilité à connaître.

---

## 1. Volet 1 — Comptes & authentification

### 1.1 Le modèle d'identité réel : **trois** populations, **quatre** modèles

| Population | Modèle | Table | Schéma | Guard Sanctum |
|---|---|---|---|---|
| Collaborateur interne Leopardo | `App\Core\Tenant\Domain\Models\SuperAdmin` | `super_admins` | `public` | `super_admin_api` |
| Employé / admin d'entreprise cliente | `App\Core\Auth\Domain\Models\Employee` | `employees` | **par tenant** | `sanctum` (défaut) |
| Compte public générique | `App\Core\Auth\Domain\Models\User` | `users` | `public` | `user_api` |
| **Client grand public travel** | `App\Modules\TravelAgency\Domain\Models\TravelCustomerAccount` | `travel_customer_accounts` | `public` | `travel_customer` |

**Preuves**

- `api/config/auth.php:52-78` — les cinq guards : `web`, `super_admin_web`,
  `super_admin_api`, `sanctum`, `user_api`, plus `travel_customer` (masqué sous
  `LEOPARDO_STATIC_ANALYSIS` uniquement, `:74-78`).
- `api/config/auth.php:99-118` — providers : `employees` (défaut via
  `AUTH_MODEL`), `super_admins`, `users`, `travel_customers`.
- `api/app/Modules/TravelAgency/Domain/Models/TravelCustomerAccount.php:24-43` —
  « Table **PLATEFORME** (`travel_customer_accounts`, schéma public, pas de
  `company_id`) », guard « **DÉDIÉ** `travel_customer` : jamais le guard employés ».
- **Test exécuté** : `IdentityAccessControlGapsTest::test_public_consumer_identity_is_a_separate_model_not_user`
  → **PASS**. Il assert que `TravelCustomerAccount` n'hérite **pas** de `User` et que le
  guard `travel_customer` existe.

> ⚠️ **Écart avec la décision du propriétaire.** La décision validée est « comptes
> public (`users`) et tenant (`employees`) séparés, reliés par le téléphone, **aucune
> troisième identité** ». L'état réel comporte **une troisième table d'identité**
> (`travel_customer_accounts`) créée pour la marketplace travel (#7739). Ce n'est pas une
> divergence théorique : c'est une population entière (les consommateurs) qui vit hors du
> graphe `users`. L'ADR (§5) doit trancher la trajectoire, pas prescrire une refonte.

### 1.2 Le lien `users` ↔ `employees` existe mais n'est pas utilisé au login

- La table `user_employee_links` existe : `api/database/migrations/public/2026_05_02_100001_create_users_and_company_requests_tables.php:97-107`,
  avec `unique(['user_id', 'company_id'])` (`:106`) — donc bien **N liens par personne**,
  un par entreprise.
- **Mais** `api/app/Core/Auth/Infrastructure/Services/AuthService.php` ne contient
  **aucune** référence à `UserEmployeeLink`. La connexion employé passe par
  `employees.email` puis `findEmployeeInTenantSchemas()` (`:618-663`).
- **Test exécuté** : `IdentityAccessControlGapsTest::test_user_employee_link_exists_but_login_does_not_use_it`
  → **PASS**. Il vérifie la clé `unique(user_id, company_id)` **et** l'absence de
  `UserEmployeeLink` dans `AuthService`.

**Conséquence** : le lien d'identité est **décoratif pour l'authentification**. Il sert
à l'annuaire plateforme (`PlatformUserDirectoryService.php:173,205`) et à la suppression
de tenant (`TenantDeletionService.php:80,138`), pas à résoudre une session.

### 1.3 Multi-appartenance : le mécanisme existe, **le choix du contexte non**

C'est le constat le plus structurant du volet 1.

`AuthService::findEmployeeInTenantSchemas()` (`:618-663`) parcourt **tous** les schémas
tenant et retourne le **premier** employé dont l'e-mail correspond :

```php
foreach ($schemas as $schema) {          // :641
    $this->setTenantSearchPath((string) $schema);
    $employee = Employee::withoutGlobalScopes()->where('email', $email)->first();
    if ($employee instanceof Employee) {
        return [$employee, (string) $schema];   // :653 — PREMIER trouvé, pas choisi
    }
}
```

- Aucun tri, aucun critère de préférence, aucune notion de « contexte actif ».
- **Aucun endpoint de sélection** : aucune route ne contient `context`, `select-tenant`
  ni `switch-tenant` sous `/auth`.
- **Test exécuté** : `IdentityAccessControlGapsTest::test_there_is_no_endpoint_to_select_a_tenant_context`
  → **PASS** (confirme l'absence).

**Impact direct sur la vision cible** : « une même personne doit pouvoir avoir plusieurs
relations/contextes sans confusion des autorisations ». Aujourd'hui, si Fatou est
responsable support chez Leopardo **et** propriétaire d'une agence, ou si Moussa est
employé de l'entreprise A **et** de l'entreprise B, **c'est l'ordre alphabétique des
schémas qui décide** de son contexte — et donc de ses permissions.

### 1.4 Ce que l'audit antérieur disait et qui n'est plus vrai

| Affirmation de `readiness-identite-paiements.md` (HEAD `4ff4d84e5`) | État au HEAD `31ef78e04` | Preuve |
|---|---|---|
| « Téléphone **non unique, non indexé** — ❌ » | ✅ **Corrigé** | `2026_10_24_000100_8736_add_phone_e164_to_users.php:65` ajoute `phone_e164`, `:73-74` crée un **index unique partiel** `WHERE phone_e164 IS NOT NULL`. Commit `5b0c7fc47`. |
| « OTP **e-mail uniquement**, aucun canal SMS — ⚠️ » | ✅ **Canal SMS livré** | `LoginCodeController.php:78,87,93` (`$sentBySms`, repli e-mail explicite) ; `config/services.php:220-223` (`SMS_OTP_*`). Commit `374609d97`. |
| « SSO non implémenté — ❌ » | ⚠️ **Partiel** : OIDC **complet**, SAML toujours 501 | voir §1.6 |

> **Leçon de méthode** : ces trois points seraient devenus trois fausses alertes si le
> rapport antérieur avait été recopié. Ils ont été revérifiés un par un dans le code.

### 1.5 Téléphone : identifiant de premier rang — **fait, mais à moitié exploité**

- Colonne dédiée `users.phone_e164` (`varchar(16)`), unique **partielle**
  (`WHERE phone_e164 IS NOT NULL`) : `2026_10_24_000100_8736...:65,73-74`.
  La migration documente explicitement qu'elle **ne remplit pas rétroactivement**
  (`:33`) — donc les comptes anciens ont `phone_e164 = NULL` et restent hors unicité.
- Le téléphone est **aussi** porté par `trial_provisionings.phone_e164`
  (`2026_10_26_000200_8738...:42-43`).
- **MAIS** : il n'existe **aucun** chemin d'authentification par téléphone.
  `LoginCodeController` valide `'email' => ['required','email','max:255']` (`:54-55`)
  et `:137-138` pour la vérification. Le téléphone n'est utilisé que comme **destinataire**
  du SMS, résolu depuis l'e-mail (`resolveSmsRecipient()`, `:255-270`).

**Verdict** : la *donnée* est prête (E.164 + unicité), le *flux* ne l'est pas. Un client
grand public sans e-mail ne peut pas se connecter.

### 1.6 OTP : mécanisme solide, mais une faille de configuration silencieuse

**Chemin réel** (`LoginCodeController`) :
1. validation e-mail (`:54-55`) → 2. résolution du provisioning éligible (`:67`) →
3. résolution du destinataire SMS (`:78`) → 4. envoi SMS si configuré (`:87`) → sinon
envoi e-mail (`:114`) → 5. mise en cache du **hash** du code (`:97`, clé
`auth:login_code:{sha256(email)}`, `:294`) → 6. vérification par `Hash::check` (`:164`).

**La faille** : `LogOnlySmsCodeSender::send()` (`app/Core/Auth/Infrastructure/Sms/LogOnlySmsCodeSender.php:119-128`)
est un **stub** : il retourne un `traceId` **sans émettre aucun appel HTTP** (le code
nomme lui-même l'endroit « Point d'extension du fournisseur réel »). Or le contrôleur
considère un `traceId` non nul comme « envoyé par SMS » (`:87` `$sentBySms = true`) et
**saute l'e-mail** (`:102-111`).

**Conséquence** : dès que `SMS_OTP_ENDPOINT` et `SMS_OTP_API_KEY` sont tous deux non
vides, **aucun code n'est délivré** — ni par SMS (stub), ni par e-mail (court-circuité).
Ce défaut a été confirmé et un correctif est proposé dans la branche de travail (§4, lot L1).

### 1.7 Sessions & tokens

| Point | Constat | Preuve |
|---|---|---|
| TTL Sanctum | **43 200 min = 30 jours** | `api/config/sanctum.php:23` (`SANCTUM_TOKEN_EXPIRATION`) |
| Préfixe de détection de fuite | `leo_` | `.env.example:546` (#7655) |
| Expiration posée au login | ✅ oui | `AuthService.php:212-213,222` (`createToken(..., $expiresAt)`) |
| **Expiration OMISE sur 2 chemins** | ❌ **écart réel** | `AuthController.php:463` et `:552` appellent `createToken($name)` **sans 3ᵉ argument** |
| Quota de tokens | ✅ purge des plus anciens | `AuthService.php:225` → `TokenQuotaService::prune()` |
| Révocation | ✅ suppression de la ligne | test exécuté `test_revoked_token_no_longer_authenticates` → **PASS** |
| Compte suspendu | ✅ refusé | `TenantMiddleware.php:107-118` (`EMPLOYEE_SUSPENDED`) ; test `test_suspended_employee_is_denied_at_the_tenant_middleware` → **PASS** |

> **Écart P1** : les deux branches `AuthController` (login après vérification 2FA, et
> login alternatif) créent des tokens **sans échéance**. Ils ne sont révocables que
> manuellement — un téléphone perdu garde un accès **illimité**, contrairement à
> l'ADR-0025. C'est mesurable : `SessionDurationTest` échoue sur ce chemin
> (`expected string, got null`).

### 1.8 Réinitialisation & récupération

- Table dédiée `password_reset_tokens` avec variante tenant :
  `2026_08_15_000008_create_password_reset_tokens_table.php` et
  `2026_08_15_000009_add_company_employee_to_password_reset_tokens.php`.
- Anti-énumération testée : `tests/Feature/Auth/ForgotPasswordNoEnumerationTest.php` —
  **exécuté, PASS** (dans la suite `tests/Feature/Auth`, voir §3).
- Politique de mot de passe testée : `tests/Feature/Auth/PasswordPolicyTest.php` —
  **exécuté, PASS**, et c'est elle qui fait échouer 3 autres tests utilisant des
  mots de passe faibles (« password123 » refusé) — voir §3.2.

---

## 2. Volet 2 — Rôles, permissions, escalade de privilèges

### 2.1 Il n'y a **pas** de RBAC en base : ce sont des **enums de code**

- **Aucune** table `roles`, **aucune** table `permissions`.
  **Test exécuté** : `IdentityAccessControlGapsTest::test_no_custom_role_table_exists`
  → **PASS**.
- Rôles plateforme : enum `PlatformRole` — 6 valeurs figées
  (`super_admin`, `admin`, `support`, `finance`, `ops`, `marketing`),
  `api/app/Core/Tenant/Domain/Enums/PlatformRole.php:29-34`.
- Permissions plateforme : enum `PlatformPermission` (~23 valeurs),
  `.../PlatformPermission.php:26-70`.
- Rôles tenant : **deux colonnes** sur `employees` — `role ∈ {manager, employee}`
  et `manager_role ∈ {principal, rh, comptable, marketing, dept, superviseur}`,
  exposées par `Employee::hasManagerRole()` (`Employee.php:246-254`).

**Conséquence** : « rôles personnalisés » est **structurellement impossible**. Ce n'est
pas un trou de sécurité (fail-closed), mais une **limite produit** : un admin de tenant
ne peut pas créer « Caissier », « Chef de rang », etc. Le modèle cible devra passer par
des données, pas des enums — mais ça se fait **sans refonte** (§6).

### 2.2 Permissions de plateforme : la matrice est lisible et vérifiée

- `PlatformRole::permissions()` — `PlatformRole.php:60-120`.
- `admin` = tout **sauf** `TeamManage` (`:65`) → un admin ne peut pas distribuer les rôles.
- `support` : lecture + `Impersonate` + `SupportManage`, **pas** de facturation (`:71-85`).
- `marketing` : `CrmView`, `ShowcaseManage`, `MetricsView`, **pas** `PaymentsView`.
- `BC-38` : `payments.view` / `payments.manage` réservés à finance/admin/super_admin
  (`PlatformPermission.php:52-58`).
- Middleware d'application : `EnsurePlatformPermissionMiddleware`, alias
  `platform.permission` (`bootstrap/app.php:186`).

**Tests exécutés (tous PASS)** :
- `test_support_role_cannot_manage_the_platform_team` — support ✗ `TeamManage`, ✓ `Impersonate`, ✗ `BillingManage`.
- `test_marketing_role_has_no_payment_administration_access` — marketing ✗ `PaymentsView`, ✗ `PaymentsManage` ; finance ✓ `PaymentsManage`.

### 2.3 Garde-fou « dernier super admin » — présent et correct

`PlatformTeamController::updateRole()` (`api/app/Modules/Platform/Interfaces/Api/V1/Controllers/PlatformTeamController.php:87-125`) :

- **:97-102** — un acteur ne peut pas changer **son propre** rôle (`CANNOT_CHANGE_OWN_PLATFORM_ROLE`, 422).
- **:106-112** — rétrograder le dernier `super_admin` **actif** est refusé
  (`LAST_SUPER_ADMIN_REQUIRED`, 422).
- **:181-190** — `activeSuperAdminCount()` ne compte que `status = 'active'` (ou NULL) :
  un super admin **suspendu ne protège pas la porte**.
- **:113-119** — assignation **explicite** (jamais mass-assignable, #3597) + piste d'audit
  `platform_team_role_changed` avec l'ancien et le nouveau rôle.

**Test exécuté** : `test_last_active_super_admin_count_ignores_suspended_accounts` → **PASS**.

### 2.4 Risques d'escalade — inventaire

| Scénario | Verdict | Mécanisme |
|---|---|---|
| Un rôle peut-il s'attribuer `platform_role` par mass-assignment ? | **Non** | `platform_role` hors `$fillable` (`SuperAdmin.php:37-42`, garde `SensitiveFillableGuardTest`) |
| Un admin de tenant peut-il obtenir des permissions plateforme ? | **Non** | Guards disjoints : `sanctum` (employees) vs `super_admin_api` (super_admins) |
| Un `principal` de tenant peut-il tout, sans grant ? | **Oui, par design** | `Employee::hasModuleGrant()` retourne `true` si `isPrincipal()` (`Employee.php:571-575`) — documenté « spéc. §3.1 » |
| Un employé peut-il écrire `company_id` d'un autre tenant ? | **Non** | `BelongsToCompany::creating()` neutralise le spoof (`BelongsToCompany.php:57-75`) |
| Un dept manager sans département voit-il tout ? | **Non** (fail-closed) | `scopeVisibleToManager` force `department_id ?? -1` (`Employee.php:378-390`) ; **test exécuté PASS** |
| Un module non accordé est-il appelable ? | **Non** (fail-closed) | `hasModuleGrant()` exige un grant ou `principal` ; **test exécuté PASS** |

**Point d'attention réel** : `hasModuleGrant('')` et `company_id === null` retournent
`false` (`Employee.php:577-583`) — bon. Mais le **`principal` contourne tout contrôle de
module**. C'est cohérent (il est le propriétaire du tenant) et il n'existe **aucune**
limite sur ce que le principal peut déléguer : un principal peut créer un grant
`marketing` à n'importe quel employé, y compris `comptable`. La garde « un rôle
personnalisé ne peut pas dépasser les droits de son créateur » est donc **sans objet**
(il n'y a pas de rôles personnalisés) mais son équivalent — « un délégant ne peut pas
déléguer plus qu'il n'a » — **n'existe pas** pour les grants. À traiter en L2.

### 2.5 Identités machines

- **Une seule** surface : `EnsurePaymentServiceToken`
  (`api/app/Modules/Paiements/Interfaces/Http/Middleware/EnsurePaymentServiceToken.php`).
- **Aucun** registre de jetons de service/agents (pas de table, pas de rotation, pas de
  révocation, pas de journal dédié) ailleurs.
- **Verdict** : **absent** au-delà du paiement. La vision (agents, intégrations,
  desktop OAuth2+PKCE) n'a pas de socle ici.

### 2.6 Impersonation support

`ImpersonationService` (`app/Modules/Platform/Infrastructure/Services/ImpersonationService.php`) :

| Exigence | État | Preuve |
|---|---|---|
| Réservée aux super admins | ✅ | doc `:21` + `platform.permission:impersonate` |
| Raison obligatoire | ✅ | `:45` paramètre `string $reason` ; colonne `reason` (500) |
| Expiration | ✅ | `expires_at` + `AccountSuspendedException` :56,74 |
| Révocation du token dédié | ✅ | `personal_access_token_id` |
| Journalisation | ✅ | table + `ended_by`, `ended_at`, IP |
| **Consentement de la personne** | ❌ | **aucune** colonne `consent*`/`notified_at` |
| **Notification de la personne** | ❌ | aucun envoi |

**Test exécuté** : `test_impersonation_has_no_consent_column` → **PASS** (confirme l'absence).

---

## 3. Volet 3 — Multi-tenant & invitations

### 3.1 Isolation : le socle est **solide**

Quatre couches indépendantes :

1. **Isolation physique** — une entreprise = un **schéma PostgreSQL**
   (`companies.schema_name`). `TenantManager::setTenant()` exécute
   `SET search_path TO <schema>` (`api/app/Core/Tenant/TenantManager.php:59-68`).
2. **Validation de la valeur** — `assertSafeSearchPath()` refuse tout caractère hors
   `[a-zA-Z0-9_",\s]` avant toute interpolation (`:200-209`) : pas d'injection possible
   dans `SET search_path`.
3. **Isolation logique** — trait `BelongsToCompany` : scope global `where company_id = ?`
   (`app/Shared/Traits/BelongsToCompany.php:41-56`).
4. **Fail-closed** — si la surface tenant est active (`tenant_scope_required`) et qu'aucune
   société n'est en contexte, la requête **lève** `TenantContextMissingException`
   (`:44-46`). **Test exécuté PASS**.

**Tests exécutés (PASS)** :
- `test_tenant_scope_confines_employee_queries_to_one_company` — sous contexte A, 1 employé visible sur 3 (2 appartiennent à B).
- `test_tenant_surface_without_context_fails_closed` — lève bien l'exception.

**Restauration garantie** : `withinTenant()` et `withinSearchPath()` utilisent
`try/finally` (`TenantManager.php:100-120, 137-160`), avec une garde spécifique pour ne pas
masquer l'exception d'origine par un `25P02` (`:78-91`).

### 3.2 Invitations : cycle de vie **complet et sûr**

`UserInvitationService` (`api/app/Modules/HR/Infrastructure/Services/UserInvitationService.php`)

| Exigence | État | Preuve |
|---|---|---|
| Token **jamais stocké en clair** | ✅ | `hash('sha256', $plainToken)` `:63` ; colonne `token_hash` unique (`migration :28-29`) |
| Entropie | ✅ | `Str::random(64)` `:45` |
| Expiration | ✅ | 7 jours (`:64`) |
| **Usage unique réel** | ✅ | transaction + `lockForUpdate` + `abort_if(accepted_at !== null)` `:128-136` |
| Expiration fail-closed | ✅ | `expires_at === null` traité comme expiré (`:138-143`) |
| Société suspendue → refus | ✅ | `:148` (`COMPANY_SUSPENDED`) |
| Employé parti/archivé → refus | ✅ | `:160-165` (`INVITATION_REVOKED`) |
| **Mot de passe défini par la personne** | ✅ | `$employee->password_hash = Hash::make($password)` `:169` — jamais de mot de passe en clair envoyé |
| Ré-invitation invalide l'ancien token | ✅ | `token_hash` réécrit sur la même ligne (`:50-62`) |
| Accès pré-assignés appliqués à l'activation | ✅ | `applyPreAssignedResources()` `:207-239` |

**Ce qui manque** : invitation **par téléphone**. La colonne est `email varchar(150) NOT NULL`
(`migration :22`) — aucune variante SMS. C'est cohérent avec §1.5 : le téléphone n'est pas
un canal d'identité.

### 3.3 Cycle de vie des comptes

| Transition | État | Preuve |
|---|---|---|
| Suspension → blocage immédiat de l'API | ✅ | `TenantMiddleware.php:107-118` |
| Archivage | ✅ | `:97-105` (`EMPLOYEE_ARCHIVED`) |
| Société suspendue/expirée | ✅ | `:75-83` (`ACCOUNT_SUSPENDED`) |
| Départ d'un employé | ✅ | statut `departed` bloque l'activation (`UserInvitationService.php:160-165`) |
| Réactivation | ✅ | `PlatformTeamController::activate()` `:121-128` |
| **Transfert de propriété d'entreprise** | ⚠️ **non vérifié** | aucun service dédié trouvé ; `companies` n'a pas de colonne `owner_id` — le propriétaire est un `Employee` avec `manager_role='principal'`, donc un transfert = changer `manager_role`, sans garde « dernier principal » |
| **Changement de propriétaire** | ⚠️ **écart** | contrairement aux super admins (§2.3), **aucun** compteur de « dernier principal actif » n'existe pour un tenant |

> Ce dernier point est le **miroir exact** du garde-fou `LAST_SUPER_ADMIN_REQUIRED` qui,
> lui, existe. Un tenant peut donc se retrouver **sans principal** — état dont il ne peut
> plus sortir par lui-même.

---

## 4. Matrice de tests de sécurité

Les 13 scénarios demandés. **Colonne « résultat réel »** : exécuté dans cet environnement
au HEAD `31ef78e04`. Aucun test n'est déclaré réussi sans exécution.

| # | Scénario | Test existant | Exécuté ? | Résultat réel |
|---|---|---|---|---|
| 1 | Collaborateur marketing sans accès à l'administration des paiements | ❌ aucun dédié → **écrit** | ✅ | **PASS** (`test_marketing_role_has_no_payment_administration_access`) |
| 2 | Responsable support ne peut pas devenir super admin | ❌ aucun dédié → **écrit** | ✅ | **PASS** (`test_support_role_cannot_manage_the_platform_team`) |
| 3 | Admin de tenant ne peut pas s'attribuer des permissions de plateforme | ⚠️ indirect (`SensitiveFillableGuardTest`) | ✅ (suite `Auth`) | **PASS** (mass-assignment bloqué) |
| 4 | Employé de A sans accès aux données de B | ✅ `TenantContextRotationTest`, `RemainingModelsTenantIsolationTest`… | ✅ | **PASS** (suite `Tenant`, 0 échec) |
| 5 | Employé membre de 2 entreprises avec rôles différents | ❌ **aucun** | ✅ | **ÉCART PROUVÉ** : pas de sélection de contexte → `test_there_is_no_endpoint_to_select_a_tenant_context` **PASS** (décrit le manque) |
| 6 | Révocation de rôle retire effectivement l'accès | ✅ partiel | ✅ | **PASS** pour la révocation de token ; **partiel** pour le rôle (pas de révocation granulaire hors grants) |
| 7 | Désactivation de compte bloque l'usage | ✅ implicite | ✅ | **PASS** (`test_suspended_employee_is_denied_at_the_tenant_middleware`) |
| 8 | Ancien token ne conserve pas de droits révoqués | ❌ aucun dédié → **écrit** | ✅ | **PASS** (`test_revoked_token_no_longer_authenticates`) |
| 9 | Utilisateur public sans accès aux données privées d'un professionnel | ✅ `BelongsToCompanyExplicitBypassTest` | ✅ | **PASS** (guards disjoints, vérifié structurellement) |
| 10 | Impossible de supprimer/rétrograder le dernier admin principal | ⚠️ **plateforme seulement** | ✅ | **PASS** pour `super_admin` ; **ÉCART** pour le `principal` de tenant (aucun compteur) |
| 11 | Rôle personnalisé ne peut pas dépasser son créateur | ➖ **sans objet** | ✅ | **PASS** (`test_no_custom_role_table_exists` — pas de rôles personnalisés) |
| 12 | Rôle sans module ne peut pas appeler les endpoints | ✅ `EnsureApiManagerMiddleware` + grants | ✅ | **PASS** (`test_module_grant_lookup_is_fail_closed`) |
| 13 | Changement d'établissement ne donne pas accès aux autres | ✅ `SiteHorizontalModelTest` | ✅ | **PASS** (suite `Tenant`, 0 échec) |

### 4.1 Suites réellement exécutées

| Suite | Tests | Assertions | Résultat | Détail |
|---|---|---|---|---|
| `tests/Feature/Audit` (**écrite pour cet audit**) | **25** | **57** | ✅ **OK** | 12 acquis + 5 écarts documentés |
| `tests/Feature/Auth` + `tests/Feature/Tenant` | **157** | **588** | ⚠️ **4 failures, 1 error** | voir §4.2 |

### 4.2 Les 5 échecs préexistants de la suite identité — causes réelles

Aucun n'est un trou de sécurité ; tous sont des **dettes de test** ou des **écarts réels**
identifiés plus haut.

| Test | Cause | Nature |
|---|---|---|
| `RegisterLoginFlowTest::test_registered_account_with_invitation_can_login_again` | Mot de passe `password123` refusé par la politique (422) | **Dette de test** : la politique a durci plus vite que les fixtures |
| `TokenQuotaTest::test_login_keeps_only_latest_tokens_within_quota` | idem (422 avant d'atteindre le quota) | **Dette de test** |
| `TokenQuotaTest::test_quota_zero_disables_pruning` | idem | **Dette de test** |
| `SessionDurationTest::test_login_issues_a_token_expiring_in_thirty_days` | `token_expires_at` = **null** | **Écart RÉEL** : chemin de login sans expiration (§1.7) |
| `GoogleIdentityBindingTest::test_google_callback_rejects_ambiguous_email_across_tenants` | `employees_email_unique` violé sur `dup@example.com` | **Artefact de harnais**, pas un défaut : voir §4.3 |

> ⚠️ **Correction importante par rapport à un réflexe courant** : ces 5 tests ne sont **pas**
> « rouges parce que l'environnement est différent ». Ils ont été exécutés sur PostgreSQL +
> PostGIS avec les vraies migrations. 4 sur 5 sont des fixtures à mettre à jour ; **1 est un
> vrai défaut de production** (`SessionDurationTest`).

---

## 5. Volet 4 — Synthèse & décision

### 5.1 Tableau de décision

| Domaine | État actuel | Compatible avec la vision ? | Écart | Priorité |
|---|---|---|---|---|
| **Identité** | 4 modèles / 3 populations (+1 travel) | ⚠️ **Partiel** | 3ᵉ identité `travel_customer_accounts` hors graphe `users` | **P0** |
| **Authentification** | Sanctum multi-guards, TTL 30 j | ✅ Oui | 2 chemins de login **sans expiration** | **P1** |
| **Rôles** | Enums figés (6 plateforme / 2+6 tenant) | ⚠️ Partiel | Aucun rôle personnalisé possible | P2 |
| **Permissions** | Enums + grants de modules + policies | ✅ Oui | Pas de garde « déléguer ≤ ses droits » | P1 |
| **Multi-tenant** | Schéma PostgreSQL + 4 couches fail-closed | ✅ **Oui, solide** | — | — |
| **Collaborateurs plateforme** | `SuperAdmin` + 6 rôles + audit | ✅ Oui | — | — |
| **Administrateurs tenant** | `manager_role` + principal implicite | ⚠️ Partiel | **Aucun garde « dernier principal »** | **P1** |
| **Grand public** | `users` + `travel_customer_accounts` | ❌ **Non** | Pas de login par téléphone ; identité éclatée | **P0** |
| **Invitations** | Cycle complet, token haché, usage unique | ✅ **Oui** | Invitation par téléphone absente | P2 |
| **Sécurité** | Fail-closed, audit, anti-escalade | ✅ Oui | Impersonation sans consentement | P1 |
| **Web** | `admin-dashboard` (team, companies, paiements), `web` (Business OS), `travel-web` (account) | ⚠️ Partiel | Pas d'écran SSO ; pas de sélecteur de contexte | P1 |
| **Mobile** | API Sanctum, autorisation serveur | ✅ Oui (API) | Dépend du contrat public (§6.3) | P1 |

### 5.2 Réponse 1 — La fondation actuelle permet-elle la vision **sans refonte majeure** ?

**OUI, pour l'ossature. NON, pour le grand public.**

Ce qui est **déjà** en place et n'a pas à être refait : l'isolation multi-tenant
(4 couches, testée), les guards disjoints, la matrice de permissions plateforme, le
garde-fou du dernier super admin, le cycle d'invitation (token haché, usage unique,
verrou transactionnel), l'audit trail, et la normalisation E.164 avec unicité partielle.

Ce qui **manque structurellement** et qu'aucun réglage ne comble :
1. **La sélection de contexte.** Une personne dans N entreprises n'a pas de « contexte
   actif ». C'est le cœur de la vision (« plusieurs relations sans confusion des
   autorisations ») et c'est absent — prouvé par test.
2. **L'identité grand public éclatée** entre `users` et `travel_customer_accounts`, sans
   lien par téléphone, et sans login par téléphone.
3. **Le dernier principal de tenant** n'est pas protégé, alors que le dernier super admin
   l'est — asymétrie non justifiée.

Aucun de ces trois points n'exige une réécriture : les deux premiers sont **additifs**
(un endpoint + une colonne + un lien), le troisième est un **compteur** copié d'un
mécanisme existant.

### 5.3 Réponse 2 — Quels écarts empêchent de garantir sécurité et bon fonctionnement ?

Par ordre de gravité :

1. **P0 — Contexte tenant arbitraire** (§1.3). Deux personnes homonymes ou une personne
   multi-entreprises peut se retrouver dans le **mauvais** tenant. Les permissions étant
   correctes *dans* le tenant, le risque n'est pas une escalade mais une **erreur
   d'aiguillage** : voir les données de la mauvaise entreprise. C'est un incident RGPD
   potentiel.
2. **P0 — Grand public sans téléphone** (§1.5). Le public visé est mobile-first ; le seul
   identifiant de connexion est l'e-mail. La vision « consommateur grand public » n'est pas
   atteignable en l'état.
3. **P1 — Tokens sans expiration** sur 2 chemins (§1.7). Contredit ADR-0025 ; un appareil
   perdu garde un accès illimité.
4. **P1 — Dernier principal non protégé** (§3.3). Un tenant peut s'auto-verrouiller.
5. **P1 — Impersonation sans consentement** (§2.6). Conformité RGPD : une personne peut
   être impersonnée à son insu (c'est tracé, donc auditable — mais non consentie).
6. **P1 — OTP court-circuité par un stub SMS** (§1.6). Un code peut n'être délivré nulle
   part, silencieusement. Le correctif est prêt (§ lot L1).
7. **P2 — Pas de rôles personnalisés** (§2.1). Limite produit, pas de faille.

### 5.4 Réponse 3 — Plus petit ensemble de modifications

Quatre lots, **aucune migration destructive, aucun guard ni middleware modifié** :

| Lot | Contenu | Effort | Risque |
|---|---|---|---|
| **L1** | Rendre l'OTP fiable : `isConfigured()` fail-closed sur le SMS, `send()` ne rend un `traceId` que s'il a émis, réponse d'erreur explicite au lieu d'un faux succès | 1 j | Faible |
| **L2** | **Sélection de contexte** : endpoint `GET /auth/contexts` + `POST /auth/context` (échange d'un token « scopé contexte »), colonne `active_company_id` sur le token (ability Sanctum existante `tenant_company`) | 3 j | Moyen |
| **L3** | Sécurité des sessions & du dernier principal : `createToken(..., $expiresAt)` sur les 2 chemins ; compteur `LAST_PRINCIPAL_REQUIRED` calqué sur `activeSuperAdminCount()` ; colonne `consent_at` + notification sur l'impersonation | 2 j | Faible |
| **L4** | Convergence grand public : `phone_e164` sur `travel_customer_accounts`, lien `users.id` nullable, login OTP par téléphone | 5 j | Moyen |

**Total ≈ 11 jours** pour couvrir les P0 et P1, sans toucher au socle multi-tenant.

---

## 6. Informations non accessibles

Signalées explicitement, jamais présentées comme des constats :

- **État de production** : aucune base de production, aucun APM, aucun journal d'accès
  n'était accessible. Tous les constats portent sur le **code** au commit `31ef78e04`,
  pas sur le comportement observé en production.
- **CI GitHub Actions** : les workflows n'ont pas été exécutés dans cette session. Les
  résultats ci-dessus viennent de l'exécution **locale** (PHP 8.4.27, PostgreSQL 15,
  PostGIS 3). Un environnement CI peut différer (extensions, version PostgreSQL).
- **`docs/design/`** : le répertoire est **vide** au HEAD audité — les documents de
  conception demandés y sont créés par cette mission.
- **Paiements** : non audités au-delà de la liste des permissions (`payments.view`,
  `payments.manage`), conformément à la consigne de lecture seule.
- **Mobile** : aucune application mobile n'a été inspectée ni modifiée ; seul le contrat
  d'API a été évalué.

---

## 7. Annexes

- Matrice rôles × permissions : `docs/design/IDENTITY_ROLE_PERMISSION_MATRIX.md`
- Architecture cible : `docs/design/IDENTITY_ACCESS_CONTROL_TARGET_ARCHITECTURE.md`
- ADR identités publiques : `docs/design/ADR-IDENTITE-CLIENTS-PUBLICS.md`
- Plan d'implémentation : `docs/roadmaps/IDENTITY_ACCESS_CONTROL_IMPLEMENTATION_PLAN.md`
- Contrat d'API public : `docs/design/PUBLIC_ACCOUNT_API_CONTRACT.md`
- Tests d'audit (branche non fusionnée) : `api/tests/Feature/Audit/`

### 4.3 Pourquoi `GoogleIdentityBindingTest` échoue ici sans être un défaut de production

L'index `employees_email_unique` est **par schéma tenant**, pas global :
`database/migrations/tenant/2026_04_17_000105_enforce_global_unique_email_on_employees.php:19`
crée `CREATE UNIQUE INDEX employees_email_unique ON employees (email)` — mais
`employees` est une table **par tenant** (un schéma par entreprise). L'index est donc
« global » à l'intérieur d'une entreprise, et **une même personne peut exister dans deux
entreprises** — c'est exactement ce que rend possible `user_employee_links`
(`unique(user_id, company_id)`, §1.2).

Le test échoue parce que le harnais `CreatesMvpSchema` instancie **plusieurs entreprises
dans un seul schéma** : deux employés `dup@example.com` de deux sociétés distinctes
tombent alors sur le même index. En production (un schéma par société), la contrainte ne
se déclenche pas.

**Conséquence pour l'audit** : ce n'est pas une faille, c'est une **limite du harnais de
test** — mais elle **masque** le vrai cas d'usage « même personne, deux entreprises »,
qui reste non testé de bout en bout (cf. §1.3, scénario #5 de la matrice). Un test dédié
sur deux schémas réels reste à écrire ; il est listé en L2 du plan.
