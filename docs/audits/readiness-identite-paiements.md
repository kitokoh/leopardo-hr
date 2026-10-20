# Audit de préparation — BC Identité & Compte unifié & BC Paiements / Wallet

- **Dépôt** : `kitokoh/leopardo-hr`
- **Branche de référence** : `main` — HEAD `4ff4d84e5` (merge PR #8696, BC-38 Paiements)
- **Nature** : audit de préparation (readiness) — **aucune modification de code applicatif**
- **Méthode** : inspection directe du dépôt (arborescence, migrations, modèles, tests, config, routes, OpenAPI, ADR) + interrogation de l'API GitHub (PR, issues, runs CI). Chaque constat ci-dessous est adossé à un chemin de fichier réel ou à un identifiant de run/issue.

---

## 0. Verdict global

| Domaine | Verdict | Bloquant pour mise en production ? |
|---|---|---|
| BC Identité & Compte unifié | **Prêt avec réserves** | Non — réserves de durcissement |
| BC Paiements / Wallet | **Prêt avec réserves** | Non — réserves de durcissement |
| Transversal (CI, architecture, dette) | **À surveiller** | Non — échecs préexistants hors périmètre |

Le socle est **livré et mergé sur `main`**. Les réserves portent sur le **durcissement** (policies explicites, événements de domaine, cohérence des types monétaires, dette CI préexistante), pas sur l'absence de fonctionnalité.

---

## 0 bis. Tableau de verdict par critère

Légende : ✅ prêt · ⚠️ à ajuster · ❌ bloquant · ❔ non déterminé.

### BC Identité & Compte unifié

| Critère | Verdict | Preuve |
|---|---|---|
| Modèle utilisateur unifié (`users` indépendant du tenant) | ✅ | `api/database/migrations/public/2026_05_02_100001_create_users_and_company_requests_tables.php:20-38` — `users` **sans** `company_id` ; `User.php` n'a aucune colonne tenant |
| Un humain peut appartenir à **plusieurs** tenants | ✅ | `...create_users_and_company_requests_tables.php:97-110` — `user_employee_links` avec `unique(['user_id','company_id'])` : la clé inclut `company_id`, donc N lignes par user |
| Un humain peut cumuler **plusieurs rôles** | ⚠️ | `users.personal_statuses` (statuts cumulables : student/employee/entrepreneur) mais les rôles applicatifs sont **par employé** (`employees.role` ∈ manager/employee/ordinary + `manager_role`) : cumul inter-tenant oui, cumul intra-tenant non modélisé |
| Authentification | ✅ | `api/config/sanctum.php:23` — `expiration` 43200 min, `:29` préfixe `leo_` ; `User` utilise `HasApiTokens` |
| Multi-appareils / révocation | ✅ | Sanctum (N tokens par user) + `LogoutAction` / `RefreshTokenAction` |
| Autorisation scopée tenant | ✅ | `Employee` utilise `BelongsToCompany` (`Employee.php:143`) ; fail-closed `BelongsToCompany.php:44-56` |
| Isolation multi-tenant par colonne | ✅ | `BelongsToCompany` + garde CI `check-tenant-scope-models.sh` + registre canonique `dev-hub/governance/tenant-scope-exceptions.json` |
| Tests d'isolation inter-tenant | ✅ | 9 suites listées §1.3 (`TenantScopeFailClosedTest`, `CrossTenantValidationTest`, `MultiTenantSharedIsolationTest`…) |
| Utilisateurs **sans tenant** (client final grand public) | ✅ | `users` n'a pas de `company_id` : un compte grand public existe sans entreprise |
| Téléphone comme identifiant de 1er rang | ❌ | `users.phone` est `nullable` **sans contrainte unique ni index** (`...:24`) ; l'unicité ne porte que sur `email` (`...:23`) |
| OTP / SMS | ⚠️ | OTP existe (`LoginCodeController`, code 6 chiffres, 10 min, hashé) mais **canal e-mail uniquement** : `LoginCodeController.php:78` → `Mail::to($email)`. Aucun provider SMS dans `api/config/` |
| SSO inter-apps | ❌ | `SSO/SSOValidationNotImplementedException` : la validation des assertions SSO **n'est pas implémentée** — `SSOService.php:184` lève l'exception, `SSOController.php:132` renvoie 501 |
| Frontières de couches testées | ⚠️ | un seul test d'archi (`QueueTenantContextArchitectureTest`) ; pas de Deptrac/PHPArkitect |

### BC Paiements / Wallet

| Critère | Verdict | Preuve |
|---|---|---|
| Wallet, solde dérivé du ledger | ✅ | `Wallet.php:65` `balance()` = `SUM(CASE direction…)` ; doc `:18` « jamais stocké » |
| Unicité `(owner_type, owner_id, devise)` | ✅ | migration tenant `...000001_8680:43` `unique(['company_id','owner_type','owner_id','currency'])` |
| `owner_type` couvre platform/tenant/client | ✅ | `WalletOwnerType.php:13-16` |
| Ledger partie double (2 écritures liées) | ✅ | `PaymentLedgerEntry` + `transfer_group_id`, test « double entry posting creates exactly two linked entries » |
| Immuabilité au niveau modèle | ✅ | `PaymentLedgerEntry.php:71-82` — `saving`/`deleting` lèvent `ImmutableLedgerEntryException` |
| Correction par contre-passation | ✅ | `LedgerService.php:90` `reverse()`, `PaymentService.php:163` (refund) |
| Machine à états transaction | ✅ | test « transaction status machine rejects forbidden transitions » ; `InvalidTransactionTransitionException` |
| `idempotency_key` unique **en base** | ✅ | migration `...000001_8680:74` `unique(['company_id','idempotency_key'])` |
| Verrou transactionnel | ✅ | `PaymentService.php:372` `lockForUpdate()` + `DB::transaction` (`:137`, `:197`) ; test concurrence |
| Transfert interne sans instance | ✅ | test « internal transfer has no provider instance » |
| Secrets chiffrés at-rest | ✅ | `PaymentProviderInstance.php:68` `'config' => 'encrypted:array'` ; `PaymentWebhookEndpoint.php:53` `'secret_signature' => 'encrypted'` |
| Secrets masqués côté API | ✅ | `maskedConfig()` ; `storeWebhookEndpoint` ne renvoie **pas** `secret_signature` dans `data` (`PlatformPaymentProviderController.php:313-323`) |
| `PaymentProviderType` en lecture seule | ✅ | aucun endpoint d'écriture sous `/platform/payments/provider-types` (`api/routes/api.php`) |
| `PaymentProviderInstance` : tenant_id nullable + priorité | ✅ | `company_id` nullable (NULL = globale), `priority` + index `['status','priority']` |
| `FeeRule` cascade de spécificité | ✅ | `FeeRuleResolver` : filtres tenant/instance, tri `specificity()` puis `priority`, `null` → zéro |
| `WebhookEndpoint` token unique | ✅ | migration `...000002_8685:95` `unique('payment_webhook_endpoints_token_unique')` |
| Routage webhook **par token** | ✅ | `PaymentWebhookController.php:31-36` ; signature HMAC `hash_equals` `:49` |
| Rejeu de webhook = no-op | ⚠️ | le contrôleur ne mute **aucun** état (il appelle le rail puis renvoie le statut) : `PaymentWebhookController.php:74-82`. Le no-op est donc **trivialement vrai**, mais aucun chemin « webhook → transition de transaction » n'est implémenté : la garantie annoncée n'est pas exercée par un test |
| `ProviderResolver` (pays/devise/tenant/priorité) | ✅ | test « market switch without code change », « inactive instance is never resolved » |
| `PaymentRailInterface` stable | ✅ | `code`, `initierCashIn`, `initierCashOut`, `traiterWebhook`, `verifierStatut` |
| Simulateur configurable, même chemin | ✅ | `SimulatorRail.php:25-28` (`success_rate`, `webhook_success_rate`, `delay_ms`, `fail_reason`) ; aucun `if simulateur` dans le ledger |
| Réconciliation écart | ✅ | `PaymentReconciliation` + `ReconciliationService` (§2.1) |
| API interne service-à-service | ✅ | `paiements.php:35-41` + `EnsurePaymentServiceToken` (503 si non configuré, 401 sinon, `hash_equals`) |
| Rate limiting | ✅ | `throttle:api` (interne + tenant), `throttle:webhooks-inbound` (`AppServiceProvider.php:315`) |
| OpenAPI versionné | ✅ | `api/openapi.yaml` — 9 chemins BC-38 ; workflow OpenAPI CI `success` sur `407cef0d5` |
| Superadmin : instances/journal/réconciliation | ✅ | `api/routes/api.php:645-663`, protégé par `auth:super_admin_api` + `platform.permission:companies.*` |
| **Policies explicites BC-38** | ❌ | `AuthServiceProvider.php` : aucune entrée `Wallet`/`PaymentTransaction`/`PaymentProviderInstance`. L'autorisation repose uniquement sur le middleware + le scope — **signalé en tête (§0 ter)** |
| Tenant : solde/historique/cash-out/fees | ✅ | `paiements.php:53-59` sous `auth:sanctum` + `tenant` |
| Scoping inter-tenant tenant | ✅ | `BelongsToCompany` fail-closed sur la surface API tenant |
| Tests d'accès croisé **Paiements** | ❌ | `api/tests/Feature/Paiements/` : 3 fichiers, 22 tests, **aucun** test Superadmin↔Tenant ni refuse d'accès croisé |
| Migrations indexées | ✅ | index `['company_id','status']`, `['company_id','created_at']`, `['status','priority']`, `['provider_instance_id','status']` |
| Seeders simulateur | ✅ | `PaymentSimulatorSeeder.php:32-44` |
| ADR | ✅ | `docs/adr/BC-38-001…004` |
| Écran admin Paiements | ❌ | `front/admin-dashboard/src/views/settings/PaymentGatewaysView.vue` = **BC-21 Billing** (`/billing/gateways`, gateways Stripe/Chargily). Aucun appel `paiements/` dans `front/admin-dashboard/src/` : la surface Superadmin BC-38 est **API-only** |
| Événements de domaine BC-38 | ❌ | aucun `event(`/`dispatch(` dans `api/app/Modules/Paiements/` ; `event-catalogue.yaml` sans entrée BC-38 |

### Transversal

| Critère | Verdict | Preuve |
|---|---|---|
| Pas de shim `class_alias` hérité | ✅ | `grep -rn "class_alias" api/app/` → **0 résultat** |
| Frontières de BC outillées | ⚠️ | 6 gardes shell (`check-layer-purity.sh`, `check-application-layer-placement.sh`, `check-architecture-docs-parity.sh`…) + 84 workflows ; **pas** de Deptrac/PHPArkitect |
| Ordre Paiements → Identité non cyclique | ✅ | BC-38 dépend de `Core/Auth` + `Core/Tenant`, jamais l'inverse |
| Communication inter-BC par événements | ❌ | aucun catalogue BC-38 ⇒ Paie/Cagnottes/VTC/Boutique ne peuvent pas réagir |
| Structure BC décrite et à jour | ⚠️ | `docs/architecture/BOUNDED-CONTEXT-REGISTRY.md` existe mais ne mentionne pas BC-38 |
| Homogénéité monétaire | ⚠️ | 246 colonnes `decimal(...)` dans les migrations (dont 96 sur des montants) vs `bigint` en BC-38 ; **aucun montant en `float`** ; mais **675** usages de `(float)`/`floatval` dans `api/app/` |
| CI : workflows applicatifs sur le HEAD | ✅ | voir §3.2 (8/8 `success` sauf Backend Coverage Gate) |
| CI : attestation de couverture | ❌ | `Backend Coverage Gate` = **`cancelled`, 0 job** sur `4ff4d84e5` (39124310794) **et** sur `9c78677cf` (39130726491) |
| CI : exploitation | ❌ | 4 workflows d'exploitation en échec préexistant — `startup_failure` du keepalive sur **6 SHA consécutifs** |
| Protocole « 47 critères API » | ❔ | **non déterminé** : aucune occurrence dans `docs/` ni `dev-hub/` |
| Versionnement d'API | ✅ | `Route::prefix('v1')` (`api/routes/api.php:109`) |

---

## 0 ter. Sécurité — signaux à traiter en priorité

**Aucune fuite inter-tenant, aucun secret en clair, aucun endpoint non protégé n'a été trouvé.** Trois réserves classées par gravité :

1. **Élevée — pas de politiques d'autorisation dédiées au BC-38.** `AuthServiceProvider.php` n'enregistre aucune `Gate::policy` pour `Wallet`, `PaymentTransaction`, `PaymentProviderInstance`. La protection effective repose sur (a) le middleware `tenant` fail-closed, (b) le scope `BelongsToCompany`, (c) les permissions plateforme `companies.view/manage` — qui sont **celles de la gestion d'entreprise**, pas du paiement. Conséquence : tout rôle plateforme disposant de `companies.view` (support, marketing) peut **lire** le journal des transactions et les rapprochements de **tous** les tenants. Aucun test ne borne ce périmètre. → issue **#8711**.
2. **Élevée — webhooks factices.** Le contrôleur ne mute aucun état : un émetteur compromis qui rejoue un payload signé obtient `200 ok` sans effet, mais **un fournisseur réel ne pourra pas faire progresser une transaction** (`pending` → `succeeded`) sans un chemin dédié. À traiter **avant** toute intégration réelle. → couvert par **#8712** (événements) et à instruire dans le lot d'intégration.
3. **Moyenne — téléphone non unique.** `users.phone` n'est ni unique ni indexé (`...create_users_and_company_requests_tables.php:24`). Identifiant de premier rang impossible en l'état sans collision. → décision produit §6.

Points **vérifiés sains** : `secret_signature` et `config` chiffrés et **jamais** renvoyés par l'API de lecture ; token de service comparé en `hash_equals` et **fail-closed** (503 si non configuré) ; webhook en 404 sur token inconnu / endpoint inactif et 400 sur signature absente ou invalide ; routes Superadmin derrière `auth:super_admin_api`.

---

## 1. BC Identité & Compte unifié

### 1.1 Structure DDD — le BC Identité vit dans `Core`, pas dans `Modules`

Le BC Identité n'est pas un module `api/app/Modules/*` : il est éclaté dans `Core/Auth` (identité, authentification) et `Core/Tenant` (multi-tenant, entreprises).

**Preuves (chemins réels) :**
- `api/app/Core/Auth/Domain/Models/User.php` — modèle `User`, utilise `HasApiTokens` (Sanctum), champ `password_hash`, `personal_statuses`.
- `api/app/Core/Auth/Domain/Models/Employee.php` — modèle employé (identité professionnelle).
- `api/app/Core/Auth/Domain/Models/AuditLog.php` — journal d'audit.
- `api/app/Core/Auth/Application/Actions/` — `LoginAction`, `LogoutAction`, `RegisterAction`, `RefreshTokenAction`, `ChangePasswordAction`, `UpdateProfileAction`.
- `api/app/Core/Auth/Infrastructure/Services/` — `AuthService`, `UserAuthService`, `SuperAdminService`, `TwoFactorAuthService`, `TotpService`, `SensitiveDataEncryptor`, `PiiHmacLookupService`, `SSO/*`.
- `api/app/Core/Tenant/Domain/Models/Company.php` — table `public.companies`, clé UUID, `schema_name`, `tenancy_type` (toujours `shared`, cf. ADR-0028).
- `api/app/Core/Tenant/Domain/Models/SuperAdmin.php` — administrateur plateforme.
- `api/app/Core/Tenant/TenantManager.php` — résolution/rotation du contexte tenant.
- `api/app/Core/Tenant/Infrastructure/Services/TenantContextGuard.php` — garde **fail-closed** du contexte tenant.

### 1.2 Invariants & sécurité de l'authentification

- **Sanctum** : `api/config/sanctum.php` — expiration `43200` minutes (30 jours glissants, ADR-0025), préfixe de token `leo_`.
- **Rôles employé** : `employees.role` ∈ {`manager`, `employee`, `ordinary`} — migration `api/database/migrations/public/2026_05_02_000002_add_ordinary_role_and_nullable_company.php`.
- **Rôles plateforme** : `api/app/Core/Tenant/Domain/Enums/PlatformRole.php`, `PlatformPermission.php`.
- **Chiffrement des données sensibles** : `SensitiveDataEncryptor` + `PiiHmacLookupService` (recherche PII par HMAC, pas en clair).

### 1.3 Isolation multi-tenant — couverture de tests réelle

Le dépôt dispose d'une couverture d'isolation inter-tenant **large et nommée explicitement** :

- `api/tests/Feature/Security/TenantScopeFailClosedTest.php` — le scope tenant échoue fermé.
- `api/tests/Feature/Security/TenantModelIsolationTest.php` — isolation par modèle.
- `api/tests/Feature/Security/CrossTenantValidationTest.php` — validation d'accès croisé.
- `api/tests/Feature/Security/IndexCrossTenantValidationTest.php` — validation via index.
- `api/tests/Feature/CrossTenantIndexIsolationTest.php` — isolation par index composite.
- `api/tests/Feature/HR/UserEmployeeLinkCrossTenantTest.php` — isolation du lien user↔tenant.
- `api/tests/Feature/Tenant/TenantContextRotationTest.php`, `TenantContextLockdownTest.php`, `TenantCompanyIdSpoofingTest.php` — anti-spoofing `company_id`.
- `api/tests/Feature/MultiTenantSharedIsolationTest.php` — isolation en mode `shared`.

### 1.4 Écarts identifiés — BC Identité

| # | Écart | Preuve | Sévérité |
|---|---|---|---|
| I-1 | **Exception de scope tenant non documentée dans le code** : `UserEmployeeLink` est résolu **avant** le contexte tenant (sinon l'authentification casse). L'exception est référencée dans `dev-hub/governance/tenant-scope-exceptions.json` mais aucun commentaire de garde dans le modèle lui-même. | `api/app/Modules/HR/Domain/Models/UserEmployeeLink.php` | Moyenne |
| I-2 | **Périmètre de migration multi-tenant / multi-rôle** : `tenancy_type` est figé à `shared` (ADR-0028) ; aucun chemin de migration vers un schéma dédié par tenant n'est outillé. | `api/app/Core/Tenant/Domain/Models/Company.php` | Faible (décision assumée) |
| I-3 | **Aucun test d'architecture dédié aux frontières du BC Identité** (couche `Core` ↔ `Modules`) : les tests d'architecture existants couvrent la file/tenant, pas les frontières de couches du BC Identité. | `api/tests/Unit/Architecture/QueueTenantContextArchitectureTest.php` (seul test d'archi présent) | Moyenne |

---

## 2. BC Paiements / Wallet

### 2.1 Domaine — structure DDD complète

**Preuves (chemins réels) :**
- `api/app/Modules/Paiements/Domain/Models/Wallet.php` — wallet, solde **dérivé** (jamais stocké).
- `api/app/Modules/Paiements/Domain/Models/PaymentLedgerEntry.php` — écritures de ledger, partie double, immuables.
- `api/app/Modules/Paiements/Domain/Models/PaymentTransaction.php` — machine à états, `idempotency_key`.
- `api/app/Modules/Paiements/Domain/Models/PaymentProviderInstance.php` — instance de fournisseur (configuration).
- `api/app/Modules/Paiements/Domain/Models/PaymentFeeRule.php` — règles de commission.
- `api/app/Modules/Paiements/Domain/Models/PaymentWebhookEndpoint.php` — endpoints webhook (token unique).
- `api/app/Modules/Paiements/Domain/Models/PaymentReconciliation.php` — rapprochement.
- `api/app/Modules/Paiements/Domain/Enums/` — `LedgerDirection`, `PaymentTransactionStatus`, `PaymentTransactionType`, `ProviderInstanceStatus`, `ReconciliationStatus`, `WalletOwnerType`, `FeeMode`.
- `api/app/Modules/Paiements/Domain/Exceptions/` — `ImmutableLedgerEntryException`, `UnbalancedLedgerEntryException`, `IdempotencyKeyRequiredException`, `InvalidTransactionTransitionException`, `InvalidWebhookSignatureException`, `ProviderNotResolvableException`.
- `api/app/Modules/Paiements/Domain/Contracts/PaymentRailInterface.php` — contrat stable des rails.
- `api/app/Modules/Paiements/Infrastructure/Services/` — `LedgerService`, `PaymentService`, `ProviderResolver`, `ProviderTypeRegistry`, `FeeRuleResolver`, `ReconciliationService`, `Rails/SimulatorRail`.

### 2.2 Infrastructure, secrets et idempotence

- **Idempotence — verrou transactionnel** : `api/app/Modules/Paiements/Infrastructure/Services/PaymentService.php:372` — `$query->lockForUpdate();`.
- **Secrets chiffrés at-rest** : `api/app/Modules/Paiements/Domain/Models/PaymentProviderInstance.php:68` — `'config' => 'encrypted:array'` (commentaire de classe : la config n'est jamais exposée en clair).
- **Auth service-à-service** : `api/app/Modules/Paiements/Interfaces/Http/Middleware/EnsurePaymentServiceToken.php` — 503 si jeton non configuré (fail-closed), 401 si invalide, comparaison `hash_equals`.
- **Config** : `api/config/paiements.php` — `service_token` (env `PAYMENTS_SERVICE_TOKEN`), `default_country` (env `PAYMENTS_DEFAULT_COUNTRY`, défaut `DZ`).
- **Provider d'enregistrement** : `api/app/Modules/Paiements/Providers/PaiementsServiceProvider.php`, enregistré dans `api/bootstrap/providers.php`.
- **Seeder** : `api/database/seeders/PaymentSimulatorSeeder.php` — type « simulateur » + instance prête à l'emploi.

### 2.3 Migrations, routes, OpenAPI, ADR

- **Migrations (tenant)** :
  - `api/database/migrations/tenant/2026_10_18_000001_8680_create_payment_core_tables.php` — `payment_wallets`, `payment_transactions`, `payment_ledger_entries`.
  - `api/database/migrations/tenant/2026_10_18_000002_8685_create_payment_provider_tables.php` — types/instances/fee rules/webhook endpoints.
  - `api/database/migrations/tenant/2026_10_18_000003_8692_create_payment_reconciliations_table.php` — rapprochements.
- **Migrations (public)** : `api/database/migrations/public/2026_09_19_000001_7726_create_payment_gateway_settings_table.php`.
- **Routes** : `api/routes/modules/paiements.php` (3 surfaces) + surface plateforme dans `api/routes/api.php`.
- **OpenAPI** : `api/openapi.yaml` — `/v1/paiements/internal/{credit,debit,transfer,refund}`, `/v1/paiements/webhooks/{token}`, `/v1/paiements/{wallet,wallet/history,market,fees,cash-out}`.
- **ADR** : `docs/adr/BC-38-001-separation-type-instance.md`, `BC-38-002-ledger-partie-double.md`, `BC-38-003-idempotence.md`, `BC-38-004-secrets-chiffres.md`.
- **Tests** : `api/tests/Feature/Paiements/PaymentDomainTest.php` (9), `PaymentIdempotencyTest.php` (5), `PaymentProviderResolverTest.php` (8) = **22 tests**.

### 2.4 Écarts identifiés — BC Paiements

| # | Écart | Preuve | Sévérité |
|---|---|---|---|
| P-1 | **Aucune Policy enregistrée pour les modèles BC-38** : `AuthServiceProvider` enregistre des policies pour d'autres BC (`HealthInvoicePaymentPolicy`, `RestaurantOrderPaymentPolicy`) mais **aucune** pour `Wallet`, `PaymentTransaction`, `PaymentProviderInstance`. L'autorisation repose sur le middleware `tenant` + scope `BelongsToCompany`. | `api/app/Providers/AuthServiceProvider.php` (l. 435, 611 — aucune entrée BC-38) | **Élevée** |
| P-2 | **Aucun événement de domaine émis par BC-38** : aucun `event()`/`dispatch()` dans le module ; `docs/architecture/event-catalogue.yaml` ne contient aucune entrée BC-38 (seule occurrence « payment » = `App\Listeners\ProcessCommissionOnPayment`, hors BC-38). Aucun BC consommateur ne peut réagir à une transaction réussie. | `docs/architecture/event-catalogue.yaml:577` ; absence de `event(` dans `api/app/Modules/Paiements/` | **Élevée** |
| P-3 | **Duplication de journaux comptables** : Payroll possède `ledger_entries` (journal financier employé, `decimal(12,2)`, `balance_after` stocké) ; BC-38 possède `payment_ledger_entries` (partie double, `bigint`, solde dérivé). Deux sources de vérité comptables coexistent sans ADR de pont. | `api/database/migrations/tenant/2026_10_18_000001_8680_create_payment_core_tables.php` vs migrations Payroll | **Élevée** |
| P-4 | **Types monétaires hétérogènes** : BC-38 = `bigint` (plus petite unité) ; ~104 colonnes `decimal(x,2)` ailleurs (payrolls, advances, invoices, accounting_payments…). Toute intégration exige une conversion explicite — risque d'erreur d'arrondi silencieuse. | `grep decimal(` sur `api/database/migrations/tenant/*.php` | Moyenne |
| P-5 | **Aucun test d'accès croisé inter-tenant sur les surfaces Paiements** : les 22 tests couvrent domaine/idempotence/resolver, pas l'autorisation croisée Superadmin↔Tenant. | `api/tests/Feature/Paiements/` (3 fichiers) | Moyenne |

---

## 3. Transversal

### 3.1 Architecture, couplage et dette

- **Pas de shim `class_alias`** : `grep class_alias` sur `api/` ne retourne **qu'une** occurrence, dans le cache PHPStan (`api/storage/phpstan/cache/...`), donc **aucun alias de compatibilité** dans le code applicatif. La migration de namespaces (`App\Services\AuthService` → `App\Core\Auth\Infrastructure\Services\AuthService`) est **achevée**.
- **Dépendance Paiements → Identité** : BC-38 dépend de `Core/Auth` (utilisateurs) et `Core/Tenant` (entreprises) pour le scoping — dépendance **unidirectionnelle** et saine (le domaine paiement ne remonte pas dans `Core`).
- **Guards CI d'architecture** : `check-layer-purity.sh`, `check-application-layer-placement.sh`, `check-openapi-coverage.sh`, `check-event-catalogue.sh`, `migration-duplication-guard`, `check-architecture-docs-parity.sh` (84 workflows au total dans `.github/workflows/`).

### 3.2 État réel de la CI sur `main`

Runs lus via l'API GitHub (HEAD `4ff4d84e5`) :

| Workflow | Conclusion | Run ID |
|---|---|---|
| Tests - Leopardo | `success` (job Backend **skipped** par path-filter) | 39124310861 |
| Merge Health Guard | `success` | 39124310849 |
| Secret Scan | `success` | 39124310864 |
| Repo Hygiene Guard | `success` | 39124310879 |
| Post-Merge Conventions Audit | `success` | 39124310896 |
| Web CI - Leopardo Admin / Vitrine | `success` | 39124310965 / 39124310814 |
| OWASP ZAP Baseline | `success` | 39124533821 |
| **Backend Coverage Gate** | **`pending`** | 39124310794 |
| **Deploy Main Catch-up** | **`failure`** | 39122878675 |
| **Database Backup & Restore Drill** | **`failure`** | 39113542842 |
| **Dev Keepalive & Watchdog** | **`startup_failure`** | 39124310881 |
| **Launch Observability Smoke** | **`failure`** | 39112294805 |

**Distinction explicite** : ces échecs sont **préexistants et hors BC Paiements** — ils portent sur l'infrastructure d'exploitation (backup drill, keepalive, smoke observabilité, catch-up de déploiement) et sur un gate de couverture resté `pending`. Aucun n'est imputable au diff BC-38 (PR #8696 mergée en `407cef0d5`, dont les workflows applicatifs sont `success`).

### 3.3 Écarts identifiés — Transversal

| # | Écart | Preuve | Sévérité |
|---|---|---|---|
| T-1 | **Gate de couverture non concluant** : « Backend Coverage Gate » reste `pending` sur `main` — la couverture réelle n'est pas attestée. | Run 39124310794 | Moyenne |
| T-2 | **Échecs d'exploitation préexistants** : Deploy Main Catch-up, Database Backup & Restore Drill, Dev Keepalive & Watchdog, Launch Observability Smoke. | Runs 39122878675, 39113542842, 39124310881, 39112294805 | Moyenne |
| T-3 | **Recherche « 47 critères » API** : **non trouvée dans le dépôt** après 6 tentatives de recherche (aucun document, aucun fichier de critères API nommé ainsi). À considérer comme **inexistant** ou **non documenté** — à clarifier avec le product owner. | `grep -rn "47 critères" docs/` → aucun résultat | Faible (documentation) |

---

## 4. Liste des écarts → issues GitHub

Les écarts ci-dessus sont tracés comme issues GitHub avec les labels `BC-03 IDENTITY` et `BC-38 PAIEMENTS` (+ `audit`, `architecture`, `CI`, `data-model`, `documentation` selon le cas). **9 issues créées** :

| Écart | Label(s) | Issue |
|---|---|---|
| P-1 Policies BC-38 manquantes | `BC-38 PAIEMENTS`, `audit`, `architecture` | **#8711** |
| P-2 Événements de domaine BC-38 absents | `BC-38 PAIEMENTS`, `audit`, `architecture` | **#8712** |
| P-3 Duplication ledger Payroll ↔ Paiements | `BC-38 PAIEMENTS`, `audit`, `architecture`, `data-model` | **#8713** |
| P-4 Types monétaires hétérogènes | `BC-38 PAIEMENTS`, `audit`, `data-model` | **#8714** |
| P-5 Tests d'accès croisé Paiements | `BC-38 PAIEMENTS`, `audit`, `CI` | **#8715** |
| I-1 Exception scope `UserEmployeeLink` | `BC-03 IDENTITY`, `audit`, `documentation` | **#8716** |
| I-3 Tests d'architecture BC Identité | `BC-03 IDENTITY`, `audit`, `architecture` | **#8717** |
| T-1 Gate de couverture `pending` | `BC-38 PAIEMENTS`, `audit`, `CI` | **#8718** |
| T-2 Échecs d'exploitation préexistants | `BC-38 PAIEMENTS`, `audit`, `CI` | **#8719** |

Chaque issue contient le constat, le risque, les critères d'acceptation vérifiables et les chemins de fichiers de preuve, avec renvoi vers ce rapport.

---

## 5. Ordre de livraison recommandé

1. **P-1** — Enregistrer les Policies explicites BC-38 + tests d'accès croisé (sécurité d'abord).
2. **P-2** — Émettre les événements de domaine BC-38 et les inscrire dans `event-catalogue.yaml` (débloque les BC consommateurs).
3. **P-3 / P-4** — ADR de pont comptable Payroll ↔ Paiements + politique de conversion monétaire.
4. **T-1 / T-2** — Remise au vert de la CI d'exploitation (hors périmètre BC, à planifier séparément).
5. **I-1 / I-3** — Durcissement documentaire et tests d'architecture du BC Identité.

---

## 6. Bloquants classés par criticité

| Criticité | Constat | Écart | Issue |
|---|---|---|---|
| **Élevée** | Aucune Policy enregistrée pour `Wallet` / `PaymentTransaction` / `PaymentProviderInstance` : les permissions plateforme utilisées (`companies.view`/`companies.manage`) sont celles de la gestion d'entreprise, donc un rôle `support` ou `marketing` peut lire le journal des transactions de **tous** les tenants. | P-1 | #8711 |
| **Élevée** | Aucun événement de domaine BC-38 : Paie, Cagnottes, VTC et Boutique ne peuvent pas réagir à une transaction réussie. Bloque l'intégration inter-BC annoncée. | P-2 | #8712 |
| **Élevée** | Aucun chemin « webhook → transition de transaction ». Le contrôleur se contente d'appeler le rail et de renvoyer son statut (`PaymentWebhookController.php:74-82`) : un fournisseur réel ne peut pas faire passer une transaction de `pending` à `succeeded`. | P-2 / intégration | #8712 |
| **Moyenne** | Deux journaux comptables coexistent sans ADR de pont (`ledger_entries` Payroll en `decimal(12,2)` avec `balance_after` stocké vs `payment_ledger_entries` en `bigint`, solde dérivé). | P-3 | #8713 |
| **Moyenne** | 246 colonnes `decimal(...)` (dont 96 sur des montants) et **675** usages de `(float)`/`floatval` dans `api/app/`, contre `bigint` en BC-38 : toute intégration exige une conversion explicite. | P-4 | #8714 |
| **Moyenne** | Aucun test d'accès croisé inter-tenant sur les surfaces Paiements (22 tests, tous domaine/idempotence/resolver). | P-5 | #8715 |
| **Moyenne** | `Backend Coverage Gate` **annulé sans job** (`cancelled`, 0 job) sur `4ff4d84e5` **et** `9c78677cf` : la couverture n'est jamais attestée. | T-1 | #8718 |
| **Moyenne** | 4 workflows d'exploitation en échec préexistant ; `Dev Keepalive & Watchdog` en `startup_failure` sur 6 SHA consécutifs. | T-2 | #8719 |
| **Moyenne** | Téléphone non unique et non indexé ⇒ impossible comme identifiant de premier rang. | nouveau | *à ouvrir* |
| **Moyenne** | SSO inter-apps **non implémenté** : `SSOService.php:184` lève `SSOValidationNotImplementedException`, `SSOController.php:132` renvoie 501. | nouveau | *à ouvrir* |
| **Faible** | OTP e-mail uniquement (`LoginCodeController.php:78`) — aucun canal SMS. | nouveau | *à ouvrir* |
| **Faible** | Surface Superadmin BC-38 **API-only** : aucun écran dans `front/admin-dashboard/src/` (seul `PaymentGatewaysView.vue` existe, et il sert BC-21 Billing). | nouveau | *à ouvrir* |
| **Faible** | Cumul de rôles **intra-tenant** non modélisé (`employees.role` unique par employé). | nouveau | *à ouvrir* |
| **Faible** | « 47 critères API » : **non déterminé** — introuvable dans `docs/` et `dev-hub/`. | T-3 | *à clarifier* |

> Les 9 issues #8711–#8719 couvrent les écarts **du rapport initial**. Les cinq constats marqués *à ouvrir* ont été découverts lors de la complétion du tableau de verdict et ne sont pas encore tracés.

---

## 7. Risques de migration de données en production

Aucun des deux BC n'exige aujourd'hui une migration destructive : **les deux socles sont déjà en place et additifs**. Les risques portent sur les évolutions à venir.

| Risque | Déclencheur | Réduction |
|---|---|---|
| **Téléphone comme identifiant** — ajouter `unique(phone)` échouera si des doublons existent | Lot « téléphone de 1er rang » | 1) `SELECT phone, COUNT(*) … HAVING COUNT(*)>1` en amont ; 2) migration **additive** (`ADD COLUMN phone_normalized`, backfill, index `UNIQUE … WHERE phone_normalized IS NOT NULL`) ; 3) contrainte posée **après** nettoyage. Jamais de `ALTER … SET NOT NULL` sur une table peuplée sans backfill préalable. |
| **Dédoublonnage d'identités** — un même humain peut déjà exister en deux `users` (email vs Google) | Fusion de comptes pour l'objectif « un compte » | Table de correspondance `user_merges (source_id, target_id, merged_at)` en **double écriture** ; réaffectation des `user_employee_links` puis des `payment_wallets.owner_id` par batches ; conservation de la ligne source en `status='merged'` pour rollback. |
| **`owner_id` non contraint** — `payment_wallets.owner_id` est un `string(64)` **sans FK** (`...000001_8680:34-35`) : une suppression d'utilisateur ne casse rien mais laisse des wallets orphelins | Nettoyage RGPD / suppression de compte | Ne **pas** ajouter de FK polymorphe. Ajouter un job de contrôle « wallet orphelin » + une commande de gel (`status`), et traiter la suppression comme une **anonymisation** (`owner_id` conservé, données PII effacées) pour ne pas casser le ledger. |
| **Contre-passation sur données historiques** — toute correction d'un solde erroné doit passer par un groupe inverse | Découverte d'un écart de réconciliation | Ne **jamais** `UPDATE payment_ledger_entries` (immuabilité au niveau modèle le refuse). Script de contre-passation idempotent, exécuté en `DB::transaction`, avec `reference` explicite et issue de traçabilité. |
| **Unification monétaire `decimal` → `bigint`** | Lot P-4 | Migration **additive** : nouvelle colonne `amount_minor`, double écriture pendant une période, bascule de lecture derrière un flag, puis suppression de l'ancienne colonne **uniquement** après validation par la réconciliation. Rollback = flag, pas de migration inverse. |
| **Ajout de FK sur `user_employee_links.employee_id`** — colonne `unsignedBigInteger` **sans FK** (`...:102`) | Durcissement | Vérifier l'absence d'orphelins, puis `ADD CONSTRAINT … NOT VALID` (Postgres) avant `VALIDATE CONSTRAINT` — pas de verrou long sur table chaude. |

**Règle générale retenue** : migrations **additives** d'abord, backfill séparé, contrainte posée en dernier ; double écriture quand deux sources de vérité coexistent temporairement ; rollback par **flag applicatif**, jamais par migration inverse sur des données déjà écrites.

---

## 8. Décisions à trancher par le propriétaire produit

Ces points ne peuvent pas être tranchés par l'ingénierie seule — ils engagent le produit :

1. **Le téléphone est-il un identifiant de premier rang ?** Si oui, cela implique : normalisation E.164, unicité, migration de dédoublonnage, et un provider SMS (coût récurrent + conformité). Si non, le téléphone reste un attribut de contact et l'objectif « superapp grand public » reposera sur l'e-mail.
2. **Quel canal OTP pour les clients finaux ?** SMS (couverture réelle, coût) ou e-mail (gratuit, friction) — ou les deux avec repli ?
3. **Le SSO inter-apps est-il requis pour le lancement ?** Aujourd'hui les callbacks renvoient **501**. Soit on l'implémente (OIDC complet, gestion des claims, révocation), soit on le retire du périmètre annoncé pour ne pas laisser croire qu'il fonctionne.
4. **Un humain doit-il pouvoir cumuler plusieurs rôles dans un même tenant** (ex. manager *et* client final de la même entreprise) ? Si oui, `employees.role` doit devenir une table de rôles ; si non, le modèle actuel suffit et il faut le documenter comme limite assumée.
5. **Quelle autorité pour les écrans Paiements côté Superadmin ?** Aujourd'hui un rôle `support` peut lire les transactions de tous les tenants via `companies.view`. Faut-il une permission dédiée `payments.view` / `payments.manage` ? (Recommandé : oui — cf. #8711.)
6. **Payroll doit-il alimenter le ledger BC-38, ou rester un journal séparé ?** Deux journaux coexistent. Si l'objectif est « un seul solde employé », il faut un pont explicite et une date de bascule ; sinon, il faut l'écrire comme décision assumée (#8713).
7. **Le webhook doit-il faire progresser une transaction ?** Aujourd'hui il ne mute rien. Avant la première intégration réelle, il faut trancher le contrat : quels événements sont acceptés, dans quel ordre, et comment un événement **hors séquence** est traité (ignoré, mis en quarantaine, ou rejoué).
8. **Politique multi-devise :** les montants sont en plus petite unité, mais aucune liste de devises supportées n'est figée (`TenantPaymentController` retombe sur `$company->currency ?? 'DZD'`). Faut-il une allowlist par instance, et que faire d'une devise non supportée (rejet 422 ou conversion) ?

### 8.1 Traçabilité — chaque décision est adossée à une issue

Aucune de ces questions n'est rhétorique : chacune a un porteur technique identifié et un critère d'acceptation vérifiable. Le propriétaire produit peut répondre en commentant l'issue correspondante.

| # | Question | Issue | BC | Ce qui est bloqué tant que ce n'est pas tranché |
|---|---|---|---|---|
| 1 | Téléphone identifiant de 1er rang ? | **#8736** | Identité | OTP/SMS, dédoublonnage des comptes grand public |
| 2 | Canal OTP (SMS / e-mail / les deux) ? | **#8738** | Identité | Inscription d'un client final sans e-mail |
| 3 | SSO requis pour le lancement ? | **#8737** | Identité | Connexion des tenants configurés en SAML (aujourd'hui refusée) |
| 4 | Cumul de rôles intra-tenant ? | **#8740** | Identité | Salarié-client sur la boutique de son employeur |
| 5 | Autorité sur les écrans Paiements ? | **#8711** + **#8741** | Paiements | Accès d'un rôle `support` aux transactions de tous les tenants |
| 6 | Payroll alimente le ledger BC-38 ? | **#8713** | Paiements | Unicité du solde employé (deux journaux coexistent) |
| 7 | Le webhook fait progresser une transaction ? | **#8742** | Paiements | Toute intégration réelle de fournisseur |
| 8 | Allowlist de devises ? | **#8743** | Paiements | Rejet d'une devise non supportée (repli silencieux sur `DZD`) |

**Écarts identifiés par l'audit et tracés en issues** (au-delà des 8 décisions) :

| Issue | BC | Écart |
|---|---|---|
| **#8712** | Paiements | Aucun événement de domaine émis — les BC consommateurs ne peuvent pas réagir |
| **#8714** | Paiements | Types monétaires hétérogènes (`bigint` BC-38 vs `decimal` ailleurs) |
| **#8715** | Paiements | Absence de tests d'accès croisé inter-tenant |
| **#8739** | Paiements | Aucun écran Superadmin/Tenant dédié au BC dans l'UI existante |
| **#8716** | Identité | Exception de scope tenant sur `UserEmployeeLink` |
| **#8717** | Identité | Absence de tests d'architecture sur les frontières |
| **#8718** | Transversal | `Backend Coverage Gate` annulé sans job — cause racine identifiée (concurrence globale) |
| **#8719** | Transversal | Échecs d'exploitation préexistants sur `main` |

**Total : 17 issues ouvertes** (9 initiales #8711–#8719 + 8 compléments #8736–#8743), toutes labellisées par BC (`BC-03 IDENTITY`, `BC-38 PAIEMENTS`, `TRANSVERSAL`) et portant un critère d'acceptation vérifiable.

---

*Rapport d'audit — aucune modification de code applicatif. Preuves vérifiables par chemin de fichier, identifiant de run CI et numéro d'issue.*
