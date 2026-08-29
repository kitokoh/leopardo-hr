# DEP-BC01 — Rapport de maturité BC-01 PLATFORM

> **Issue :** [DEP-BC01 #5877](https://github.com/kitokoh/leopardo-hr/issues/5877)
> **Contexte :** BC-01 — Platform Core (catalogue, provisioning, feature flags, administration plateforme)
> **Date :** 2026-08-28
> **Statut :** **Rapport phase 1 livré** — les corrections listées en §6 font l'objet de PRs courtes de suivi (issues dédiées), conformément au livrable « rapport de maturité puis corrections ».

## 1. Cartographie de l'existant

| Composant | Emplacement | Volume |
|---|---|---|
| Module Platform (DDD 5 couches) | `api/app/Modules/Platform` | 53 fichiers PHP |
| Module Onboarding | `api/app/Modules/Onboarding` | 12 fichiers PHP |
| Feature flags | `api/config/` + `company.features` + `FeatureFlagPolicy` + `PlatformCompanyFeatureController` | mécanisme existant |
| Provisioning | `CompanyProvisioningService` + `ActivateCompany` (Application/Actions) | services dédiés |
| Admin plateforme (API) | `api/routes/api.php` § `prefix('platform')` / `prefix('admin')` (auth `super_admin_api`) | ~90 routes |
| Admin plateforme (Web) | `api/routes/web.php` § `platform/*` (auth `super_admin_web`) + `front/admin-dashboard` | SPA dédiée |
| Tests | `api/tests/Feature/Platform/*` (10+), `Onboarding/*` (3+), `CrmPlatformIsolationTest` | couverture existante |

## 2. Scorecard des 12 dimensions (définition du backlog de profondeur)

| # | Dimension | Statut | Constat |
|---|---|---|---|
| 1 | Domaine | ✅ Présent | Vocabulaire Platform/Onboarding/Billing documenté (registre BC #5900, ADR-CRM-DUAL-CONTEXTS) ; owner @kitokoh |
| 2 | Données | 🟡 Partiel | Migrations public/tenant cohérentes (`leopardo:migrate`) ; registre de maturité à compléter sur les index volumétriques |
| 3 | Tenant | ✅ Présent | `TenantManager`/`current_company`/`search_path` ; isolation démontrée (`CrmPlatformIsolationTest`, `TenantPlatformRouteIsolationTest` #5933) |
| 4 | API | ✅ Présent | Routes versionnées `/api/v1` ; guard de séparation platform/tenant (MAT-003 #5918) ; OpenAPI maintenu |
| 5 | Autorisation | ✅ Présent | `super_admin_api`/`super_admin_web` + Policies (FeatureFlagPolicy…) ; RBAC matrice documentée |
| 6 | Transactions | 🟡 Partiel | Activation tenant transactionnelle ; à auditer sur les chemins multi-étapes (provisioning) |
| 7 | Asynchronisme | 🟡 Partiel | Jobs plateforme (DispatchWebhook, PlatformAnnouncement…) idempotents (garde backend-jobs-ci) ; outbox CRM à généraliser |
| 8 | Sécurité | ✅ Présent | Gardes secret-scan/TruffleHog, threat models (MAT-017 #5933), revue 2026-08-23 |
| 9 | Frontend | ✅ Présent | `front/admin-dashboard` (Vue, tokens glass-*) + vitrine ; guards web-ci |
| 10 | Performance | 🟡 Partiel | Budgets définis (MAT-014 #5932) ; mesures k6 à systématiser sur l'admin |
| 11 | Exploitation | ✅ Présent | Runbooks (DEPLOY, ROLLBACK, OPERATIONS, BACKUP_RESTORE) + registre MAT-015 #5930 |
| 12 | Produit | ✅ Présent | CRM commercial Leopardo isolé du CRM client (ADR-CRM-002) ; pilotage par issues/Projects |

**Bilan : 8/12 pleinement présents, 4 partiels (données, transactions, asynchronisme, performance).**

## 3. Risques identifiés

1. **Index volumétriques** (dimension 2) : les tables plateforme à fort volume
   (`platform_announcements`, `webhook_deliveries`, `audit_logs`) n'ont pas
   d'index de croissance vérifiés → création d'index à planifier (signalé par
   MAT-014, 42 signalements).
2. **Provisioning multi-étapes** (dimension 6) : l'activation tenant enchaîne
   création société → seed steps → feature flags ; un audit de rollback
   transactionnel complet est à faire.
3. **Outbox généralisée** (dimension 7) : l'outbox existe pour le CRM ; les
   événements plateforme (`CompanyCreated`, `SubscriptionPaid`) gagneraient la
   même garantie (MAT-008).
4. **Mesures de performance** (dimension 10) : pas de benchmark k6 dédié au
   cockpit admin.

## 4. Contrats de dépendance (extraits du registre BC)

- `allowed_dependencies` BC-01 : BC-02 (TENANT), BC-03 (IDENTITY) — respecté
  par la garde MAT-002 (#5912).
- Interdits : imports Payroll/CRM client depuis Platform hors contrats
  (dette gelée dans `bc-dependencies-allowlist.txt`).

## 5. Definition of Done commune — état

| Exigence | État |
|---|---|
| Cartographie | ✅ (ce rapport) |
| Migrations fresh/rerun/rollback | ✅ (runner `leopardo:migrate` + MAT-005 #5917) |
| Tests domaine/API/cross-tenant | ✅ (suite Platform + isolation) |
| Gardes architecture | ✅ (MAT-001..003) |
| Runbook + rollback | ✅ (MAT-015) |
| Observabilité | 🟡 (MAT-009 hors périmètre de cette passe) |

## 6. Plan de corrections (PRs courtes de suivi)

| Priorité | Correction | Issue de suivi proposée |
|---|---|---|
| P1 | Index de croissance `platform_announcements` / `webhook_deliveries` / `audit_logs` | DEP-BC01-followup-1 |
| P1 | Audit rollback transactionnel du provisioning (tests) | DEP-BC01-followup-2 |
| P2 | Outbox événements plateforme (CompanyCreated, SubscriptionPaid) | DEP-BC01-followup-3 (dépend MAT-008) |
| P2 | Benchmark k6 cockpit admin | DEP-BC01-followup-4 |

## 7. Preuves

- Gardes verts sur main : `check-tenant-platform-separation.sh` (862 routes),
  `check-bc-dependencies.sh` (58 paires gelées), `check-public-routes.sh` (38
  routes), runbooks (19 BC), golden journeys (7), budgets (6 endpoints).
- Tests d'isolation : `CrmPlatformIsolationTest`, `TenantPlatformRouteIsolationTest`.
