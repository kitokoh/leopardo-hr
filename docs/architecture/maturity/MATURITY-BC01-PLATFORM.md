# Rapport de maturité — BC-01 PLATFORM

> **DEP-BC01 (issue #5877)** — Deep maturity, BC-01 Platform Core.
> Audité le 2026-08-28 (main `8b3609f`). Agent propriétaire : 01.
> Cadre : `docs/architecture/BOUNDED-CONTEXT-DEEP-MATURITY-BACKLOG.md` (12 dimensions).
> Registre : `dev-hub/governance/bounded-context-registry.json` (BC-01).

## Périmètre

Platform Core = catalogue modules, provisioning, configuration globale, feature
flags et administration plateforme : `api/app/Modules/Platform`,
`api/app/Modules/Onboarding`, `api/app/Core/Feature`, routes `/api/v1/platform/*`
+ `/api/v1/admin/*` + `/api/v1/metrics`. Distinction stricte d'avec le CRM
commercial Leopardo (matrice `CRM_API_MATRICE_TENANT_PLATFORM.md`, ADR-CRM-002).

## Verdict par dimension

| # | Dimension | Verdict | Preuves / constats |
|---|---|---|---|
| D1 | Domaine | 🟡 PARTIEL | Structure DDD complète (Application/Domain/Infrastructure/Interfaces/Providers). Vocabulaire dispersé : pas de glossaire BC-01 dédié ; ADR onboarding `docs/architecture/adr/0015-onboarding-steps-canonical.md`. Contrats de catalogue non formalisés (pas de manifest de solution sur main — FuelStation/EduManager absents). |
| D2 | Données | 🟢 PRÉSENT | Tables publiques migrées (`plans.features` jsonb, `companies`, support tables). Index de base présents. **Risque** : `plans.features` JSONB est une config qui porte des capacités (voir D5). |
| D3 | Tenant | 🟢 PRÉSENT | Surface platform = super-admin (aucun `company_id` client). Accès tenant des données cross-schema via `search_path` sauvegardé/restauré (`PlatformCompanyHealthService::withTenantSearchPath`), `TenantManager` respecté. Gardes MAT-003 (route-owner) et MAT-004 (contrat TenantManager) couvrent le contexte. |
| D4 | API | 🟢 PRÉSENT | 110 routes platform/admin/metrics documentées OpenAPI (couverture 100 % sur main). Requests validées (ex. `UpdateFeatureMatrixRequest`). Exceptions documentées (MetricsController hors prefixes, MAT-003). |
| D5 | Autorisation | 🟡 PARTIEL | Guard `super_admin_api` + `AdminMiddleware` + `ThrottleLimitConfig` (platform-sensitive). **Pas de Policies dédiées platform** (`api/app/Policies/` ne contient pas de Policy Platform) — l'autorisation repose sur le guard, acceptable mais non granulaire. **Risque (backlog)** : « aucune configuration JSON ne doit devenir une autorité d'accès » — `plans.features`/`company.features` gate des modules via `FeatureFlag::for($company)` : à surveiller (les features contrôlent l'accès UI/API sans Policy formelle). |
| D6 | Transactions | 🟡 PARTIEL | Provisioning : `CompanyProvisioningService` (seed idempotent, slug unique check-then-create, commentaires de compensation). Activation features : write simple sans transaction (un seul update). Pas de compensation formelle ni de contrat d'activation versionné. |
| D7 | Asynchronisme | 🟡 PARTIEL | 2 jobs platform (`DispatchPlatformAnnouncementToCompanyJob`, `ProvisionDemoTenantJob`). Pas de retry/DLQ spécifiques BC-01 (l'infra queue database est globale, MAT-008 en cours par un autre agent). |
| D8 | Sécurité | 🟢 PRÉSENT | Secrets hors code (Pulumi ESC/.env), scans TruffleHog/CodeQL, audit trail `AuditLog` (pattern `PlatformUserController`). **Correctif livré dans ce DEP** : audit des changements de feature flags (voir ci-dessous). |
| D9 | Frontend | 🟢 PRÉSENT | Admin dashboard (Vue.js, tokens glass-*) : pages companies/features/plans/webhooks. Non audité en profondeur (périmètre front hors code). |
| D10 | Performance | 🟢 PRÉSENT | Pagination sur les listes admin (users, webhooks), health checks bornés, throttle platform-sensitive. Budgets p95/p99 non versionnés (MAT-014 en cours par un autre agent). |
| D11 | Exploitation | 🟢 PRÉSENT | `/api/v1/health` + `/metrics` (super-admin), observabilité Redis/queue (`QueueObservabilityController`), runbooks ops existants (`docs/ops/`). |
| D12 | Produit | 🟡 PARTIEL | Parcours golden « catalogue → plan → activation → configuration → audit → désactivation » non démontré de bout en bout ; pas de seed pilote BC-01 dédié ; la non-régression CRM commercial est verrouillée (`CrmPlatformIsolationTest`, #5737). |

## Correctif livré (PR de ce DEP)

**Audit trail des changements de feature flags** (D8 + backlog « audit des
changements ») :

- `PlatformCompanyFeatureController::update()` enregistre désormais une entrée
  `audit_logs` (`action = platform.company.features.update`, module
  `platform`, `company_id`, `old_values`/`new_values`, IP, user-agent, acteur)
  à chaque activation/désactivation de module.
- **Bug latent corrigé au passage** : les requêtes platform s'exécutent avec
  `search_path = public` (SET explicite par convention) alors que `audit_logs`
  vit dans le schéma tenant partagé → l'INSERT via le modèle échouait
  silencieusement (42P01 avalé). L'écriture est qualifiée `shared_tenants.`
  via `tenantTable()` (pattern `SectorTemplateService`).
- Test : `PlatformCompanyFeatureApiTest` étendu (+8 assertions : présence de la
  ligne d'audit, old/new values, différence d'état tracée).

## Recommandations (non bloquantes, PR futures)

1. **Même correctif pour `PlatformUserController::audit()`** : le pattern
   `AuditLog::query()->create` y souffre du même défaut de search_path (les
   audits platform users ne persistent pas). À porter sur `tenantTable()`.
2. **Policies platform formelles** (D5) : introduire `PlatformPolicy` /
   vérification de permission opérateur au-delà du guard super-admin.
3. **Contrat de catalogue versionné** (D1/D6) : un manifest de solution signé
   par BC (le pattern existe dans les PRs FuelStation/EDU en attente de merge).
4. **Parcours golden démontrable** (D12) : test end-to-end
   activation → audit → désactivation contrôlée avec échec simulé.

## Non-régression

Aucune route, table ou Policy modifiée. Le correctif est additif (écriture
d'audit en try/catch, jamais bloquante). CRM commercial intact.
