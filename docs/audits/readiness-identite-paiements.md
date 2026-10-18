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

*Rapport d'audit — aucune modification de code applicatif. Preuves vérifiables par chemin de fichier, identifiant de run CI et numéro d'issue.*
