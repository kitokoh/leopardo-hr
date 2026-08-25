# Changelog

Toutes les modifications notables de ce projet sont documentées ici.
Format basé sur [Keep a Changelog](https://keepachangelog.com/fr/1.0.0/).

## [Unreleased]

- **Attendance ADR-0016 Phase 5 (issue #5356) — module SmartAttendance supprimé, contrat unique `/api/v1/attendance/*`**:
  - Controllers + FormRequests géo déplacés de `SmartAttendance/Interfaces/Api/V1/` vers `Attendance/Interfaces/Api/V1/` (namespaces alignés) ; dossier `api/app/Modules/SmartAttendance/`, `SmartAttendanceServiceProvider` et routes alias `smart_attendance.php` supprimés ; binding `GeofenceValidatorInterface` transféré dans `AttendanceServiceProvider`.
  - Chemins `/api/v1/smart-attendance/*` purgés (OpenAPI + miroir + SDK régénérés, 788 opérations) ; tests géo migrés sur `/attendance/*` (liste sessions → `/geo-sessions`) avec verrou 404 sur les anciens alias (`GeoRoutesMigrationTest`).
  - Commande alias `smart-attendance:auto-close` supprimée — couverture `--sessions-only` portée sur `attendance:auto-close` (`AutoCloseSessionsOnlyCommandTest`).
  - Garde CI finale `check-smartattendance-purged.sh` (Architecture Quality) : aucun import `App\Modules\SmartAttendance\*`, aucun chemin `/smart-attendance/*`, module/provider absents.
  - Pages web dashboard `(dashboard)/smart-attendance/*` migrées sur `/attendance/*` ; contrat mobile vérifié `/attendance/*` uniquement.
- **feat(attendance): ADR-0016 Phase 4 — fusion modèles/services/commandes SmartAttendance dans le module Attendance (Closes #5355).** 4 modèles (`GeoAttendanceSession`, `EmployeeLocationEvent`, `AttendanceModeSettings`, `EmployeeAttendancePreference`), 2 services (`AttendanceModeResolver`, `GeoSessionManager`), 6 actions, 2 DTOs, 3 exceptions et le contrat `GeofenceValidatorInterface` déplacés dans `App\Modules\Attendance\*` avec classes de re-export `@deprecated` dans SmartAttendance (imports existants préservés). `AutoCloseGeoSessionsCommand` fusionnée dans `AutoCloseAttendanceCommand` (une seule fermeture automatique — pointages sans check-out + sessions GPS orphelines, options `--hours`/`--company`/`--logs-only`/`--sessions-only`) ; `smart-attendance:auto-close` conservée comme alias délégué `@deprecated` ; scheduler unifié sur `attendance:auto-close`. Tests : 6 fichiers `tests/Feature/SmartAttendance/*` migrés vers `tests/Feature/Attendance/Geo*` sans perte de scénarios. Garde géofence (`check-geofence-single-usage.sh`) mise à jour. Aucune route `/smart-attendance/*` cassée (surface conservée jusqu'à la Phase 5 #5356).

### Added
- **Accounting document workflow + concurrent-safe numbering (issue #5223)**:
  - `SequentialDocumentNumbering` implements `DocumentNumberingInterface`: per-type series from `AccountingSettings.number_series` (defaults FAC/PRF/DEV/AVR/BL/RCP), `{SERIE}-{ANNEE}-{NUMERO}` format, atomic `INSERT ... ON CONFLICT ... RETURNING` increment (no duplicates under concurrency)
  - `DocumentWorkflowService` state machine: draft → sent → partially_paid → paid | cancelled (+ computed overdue), business rules (no `paid` without full payment, partial payment for `partially_paid`, credit note linked to source invoice, delivery note with delivery date) + dedicated domain exceptions
  - Additive tenant migration: `accounting_number_counters` table + `accounting_documents.source_document_id` (self-FK for credit notes)
  - Provider binding; no API endpoints (exposed via #5226)
  - Tests: `DocumentWorkflowNumberingTest` (16) — sequences, custom series, upsert on pre-existing counter (race simulation, repo convention #4978), 100 unique numbers, full workflow, transition rules
- **Accounting perf/scale audit (issue #5275)**:
  - Composite indexes (additive migration `2026_08_23_000005`): `accounting_documents (company_id, status, due_date)`, `(company_id, issue_date)`, `accounting_payments (company_id, document_id, status)`
  - `accounting:benchmark` command (F-12 protocol): seeds 10k realistic documents (dedicated company, chunked inserts) + measures paginated eager list (N+1 barrier ≤ 5 queries), reminder query (J+7), monthly aggregation
  - Measured: 10k-document list 0.01 s (< 200 ms target), 1,763 reminder-eligible, 13 months
  - `AccountingPerformanceTest` (3 tests) + `docs/accounting/BENCHMARK.md` (protocol, targets, index map, reference results)
- **Accounting document email + secure client portal (issue #5225)**:
  - Tokenized per-document shares (`accounting_document_shares`: 64-char token, expiry, recipient email) — RGPD-limited access (CabinetShare #1817 pattern)
  - `DocumentShareService` (create/resolve + URLs); public endpoints `GET /api/v1/accounting/documents/shared/{token}` (metadata) and `/download` (PDF from private disk, throttled)
  - `DocumentShareMail` (PDF attachment + portal link, `emails.document-share` view); `SendDocumentEmail` action (ensures PDF via #5224 job, share, send, `sent_at` + `sent` status); artisan `accounting:send-document` command; i18n ×4 (email keys)
  - OpenAPI: 2 public routes documented + mirror regenerated
  - Tests: `DocumentShareEmailTest` (6) — send with attachment + link, public metadata, PDF download, expired/unknown token rejected, access limited to the shared document

### Added
- **Accounting document PDF rendering ×4 locales (issue #5224)** — implements `PdfRendererInterface`:
  - `DocumentPdfRenderer` (dompdf) renders all 6 `DocumentType` values (invoice, proforma, quote, credit_note, delivery_note, receipt) in fr/en/tr/ar (RTL via `I18nCatalog::isRtl`)
  - Company header, parties, line table (description/qty/unit price/discount/amount), totals (HT/TVA/TTC/paid/remaining), legal mentions (`AccountingSettings.legal_mentions` → `footer_mentions` fallback)
  - New `api/lang/{fr,en,ar,tr}/accounting.php` catalogs (33 keys, parity ×4)
  - `GenerateDocumentPdf` queued job archives to the private disk (`accounting/documents/{company}/{id}.pdf`) and sets `pdf_path`; idempotent; `TenantScopedJob` + `EnsureTenantContext`
  - Binding registered in `AccountingServiceProvider`
  - Tests: `DocumentPdfRendererTest` — 24 renders (6×4) without error, golden amounts (2×1000 − 100 discount → HT 1900 / tax 361 / TTC 2261), Arabic RTL flag, mentions priority, idempotent archiving
- **Accounting treasury Phase B — payments + reconciliation + reminders (issue #5229)**:
  - `PaymentRegistrationService`: register (never paid > total — 422 `PAYMENT_EXCEEDS_TOTAL`; issued documents only — 422 `PAYMENT_ON_UNSENT_DOCUMENT`), update `paid_amount` + document status (partially_paid/paid), idempotent reconcile (`matched` + `reconciled_at`)
  - `PaymentReminderService` + `accounting:send-payment-reminders` command: J+7/J+15/J+30 configurable (`accounting_settings.payment_reminder_days`), `accounting_payment_reminders` unique (document, stage) — zero duplicates, Notification to principal/comptable managers (template `accounting_payment_reminder`, i18n ×4)
  - API (RBAC principal/comptable): `GET /accounting/payments`, `POST /accounting/documents/{document}/payments`, `POST /accounting/payments/{payment}/reconcile`, `POST /accounting/reminders/run`
  - OpenAPI: 4 paths documented + mirror/SDK regenerated (750 ops, route coverage 754/754)
  - Tests: `AccountingPaymentTest` (14) — suite `tests/Feature/Accounting` 23/23
- **Accounting journal — debit/credit entries + period closure (issue #5234)**:
  - `accounting_journal_entries` (tenant): one row per account, exclusive debit XOR credit (CHECK), unique (company, source_type, source_id, account_code) for idempotent reposting; `accounting_closed_periods` for period closure
  - `JournalPostingService`: post document (invoice/credit_note only, status != draft/cancelled) + payment (non-pending), PCF/SYSCOHADA simplified chart (411/70/709/4457/512/53), balance invariant enforced (UnbalancedJournalEntryException), closed period -> 422 `PERIOD_CLOSED`
  - `JournalCsvExporter`: streamed CSV (UTF-8 BOM, `;`, formula-injection guard #4169, TOTAL row)
  - API (RBAC principal/comptable): `GET /accounting/journal`, `GET /accounting/journal/export.csv`, `POST /accounting/journal/periods/{period}/close`, `POST /accounting/documents/{document}/journal`
  - i18n: `errors.*` ×4 gains `PERIOD_CLOSED`
  - OpenAPI: 4 paths documented + mirror/SDK regenerated (750 ops, route coverage 751/751)
  - Tests: `AccountingJournalTest` (15) — suite `tests/Feature/Accounting` 24/24

### Added
- **Attendance reports by period (issue #5268)** — the monthly report endpoint `GET /attendance/monthly-report` is now a full report engine:
  - `period=day|week|month` (default `month`, backward compatible), anchors `date` (Y-m-d), `week` (Y-m-d — ISO week Monday→Sunday), `month` (Y-m)
  - Filters `department_id` (team) and `employee_id` (individual attendance sheet); manager scope (`visibleToManager`, PA2-SEC-002/003) always enforced — filters can only narrow, never widen visibility
  - CSV + PDF exports for every period (`attendance-report-<period>-<from>_<to>.<ext>`); payroll synthesis (hours, overtime, estimated gross) kept per period
  - `AttendanceMonthlyReportService`/`AttendanceMonthlyReportRequest` renamed to `AttendanceReportService`/`AttendanceReportRequest`; controller method `monthlyReport` → `report` (route URL unchanged)
  - Employee rows now include `department_id`/`department_name`; `data.period` adds `type` while keeping `month` for backward compatibility
  - Tests: `AttendanceReportTest` (9 scenarios: daily, weekly, monthly, defaults, filters, CSV/PDF exports, scoped RBAC, invalid period)
- **Audit sécurité OWASP + scan secrets consolidé (issue #5281).** Rapport `docs/security/AUDIT_SECURITE_2026-08-23.md` : checklist OWASP Top 10 par surface (API/web/admin/mobile) avec preuves (CI scans ZAP/TruffleHog/Semgrep/CodeQL/Dependabot verts sur main, suites de tests sécurité re-vérifiées) ; **0 vulnérabilité critique ouverte** (DoD) ; plan de remédiation chiffré P0/P1/P2 (P0 = #5171 Google signup, P1 = merge #5292 + rotations secrets, P2 = MASVS mobile + pen-test tiers). Index mis à jour dans `docs/security/README.md`.
- **Congés légaux par pays DZ/MA/TN/SN (issue #5289)** — registre de règles légales (`LegalLeaveRulesRegistry` : 30/24/30/26 j/an, acquisition mensuelle, report, monétisation, source légale, `confidenceLevel`), plancher légal appliqué par `leave:accrue` (l'acquisition mensuelle d'une politique de congés déductibles ne descend jamais sous le minimum légal du pays ; log `planning.legal_leave.floor_applied`), droit projeté par ancienneté (`LegalLeaveEntitlementService`), calendrier des fériés légaux en lecture seule de `public_holidays` (`LegalLeaveCalendarService`) ; spec `.specify/features/5289-legal-leaves-by-country/` + inventaire `docs/payroll/LEGAL_LEAVES.md` + tests golden par pays.
- **Perf/load — audit index + métriques de charge (issue #5284).** Migration `2026_08_23_000001_add_perf_audit_indexes` : 4 index manquants sur les tables volumineuses (`webhook_deliveries (webhook_endpoint_id, delivered_at)`, `audit_logs (employee_id)`, `leave_accruals (leave_policy_id)`, `approval_decisions (approval_request_id)`). Métriques publiées dans `docs/ops/PERF.md` : clôture 500 employés = 3,50 s (< 60 s, objectif #5284) via `payroll:benchmark` (8 requêtes/employé, aucun N+1) ; artefact queue `sync` documenté (PDF inline). Garde `PerfAuditIndexesTest`.
- **Corrections de pointage — workflow complet (issue #5267).** Justificatif optionnel (`proof` multipart ≤ 5 Mo, stockage privé scopé entreprise, téléchargement `GET /attendance/corrections/{correction}/proof` réservé propriétaire/manager) ; verrouillage de période (`attendance_period_closures` + commande `attendance:close-period` idempotente, tracée) → 422 `ATTENDANCE_PERIOD_CLOSED` (catalogue erreurs ×4) ; audit trail `AuditLog` sur request/approve/reject ; anti-fraude `anomaly` (conflit session géo validée vs horaires demandés, seuil 15 min) ; openapi.yaml 745/745 + dédoublonnage des chemins dupliqués pré-existants (parse redocly réparé).

### Fixed
- **Parallel PostgreSQL test database creation tolerates duplicate-database SQLSTATE.** `TestCase` now accepts SQLSTATE `42P04` directly when multiple workers race to create the same `{db}_test_{token}` database, preventing false backend-suite failures while still rethrowing unrelated database errors.
- **Payroll validation is atomic.** Run status, related pay-slip statuses, and the validation audit are now committed inside the same transaction; the controller no longer performs a second partial transition.
- **Trial signup slug-collision retries preserve PostgreSQL transactions.** Each bounded retry now uses an explicit savepoint when invoked inside an existing transaction, so an expected `23505` collision cannot poison subsequent queries or unrelated tests.
- **LeaveCarryForward processes all tenants explicitly.** The annual carry-forward command now disables tenant global scopes for policies, balances, accrual expiration queries, and write builders while retaining explicit company filters, so console execution cannot inherit a stale `current_company` or `search_path` context; per-employee failures remain isolated and logged without poisoning the command transaction.

- **Leave carry-forward assertions are tenant-independent.** The regression test reads generated accruals and balances without reapplying the current-company global scope after the global console command runs.
- **Leave pending reservation fixture aligned with working-day semantics.** The regression test now reserves two distinct one-day working-day absences against a one-day balance, verifying pending-day reservation without relying on the legacy calendar-day calculation.

### Security
- **Constant-time comparison and hashed storage for `edge_token`** — `EdgeNodeController` now hashes the Edge node authentication token at rest (`hash('sha256', $token)`) instead of storing it in cleartext, and verifies incoming tokens with `hash_equals()` instead of a plain string comparison
  - Removes a timing side-channel that could let an attacker recover a valid `edge_token` byte-by-byte via response-time measurements
  - Removes plaintext token exposure in the database (backups, dumps, read replicas) — only the SHA-256 digest is persisted
  - No behavior change for legitimate Edge nodes: token issuance/rotation and existing valid tokens continue to authenticate identically
  - Tests: `EdgeSyncTest`, `EdgeOfflineScenarioTest` updated and passing
- **Mass-assignment hardening on 13 Eloquent models** — replaced `protected $guarded = [];` with explicit `protected $fillable = [...]` allow-lists on `AttendanceLog`, `ApprovalRequest`, `ApprovalWorkflow`, `ApprovalDecision`, `KioskAnnouncement`, `AttendanceCorrectionRequest`, `AttendanceKiosk`, `BiometricEnrollmentRequest`, `ZktecoDevice`, `ZktecoSyncLog`, `CalendarEvent`, `CalendarConnection` (Attendance module) and `ScheduledTaskRun` (Platform module)
  - No known active exploit today (all write paths already used explicit field lists), but `$guarded = []` removed Laravel's mass-assignment safety net for any future `Model::create($request->all())`-style shortcut
  - Allow-lists built from each model's actual migration columns and real write call sites; no behavior change — existing test suite passes identically before/after

### Added
- **Employee-manager discussion threads (PA2-COMM-002)** : fil de discussion privé entre un employé et son manager
  - Modèles `ConversationThread` / `ConversationMessage` (scopés par tenant via `BelongsToCompany`)
  - Fil libre ou rattaché à un sujet existant (`salary_advance`, `attendance_correction`, `absence`) appartenant au même employé/entreprise
  - Un seul fil par binôme employé/manager pour un même sujet ; réutilisation automatique du fil existant
  - Pièce jointe unique par message (5 Mo max), téléchargement restreint aux participants du fil
  - Statut lu/non-lu par participant, notification in-app (`conversation_message_received`) à chaque nouveau message
  - RBAC : l'employé ne voit que ses propres fils, le manager ne voit que les fils de ses subordonnés directs
  - Endpoints : `GET/POST /api/v1/conversations`, `GET /api/v1/conversations/{thread}`, `POST /api/v1/conversations/{thread}/messages`, `GET /api/v1/conversations/{thread}/messages/{message}/attachment`
  - Tests Feature : `ConversationControllerTest` (isolation tenant, RBAC manager/employé, pièce jointe, notifications)
- **Audit trail pour la nomination/revocation de rôles RH (PA2-MOB-007)**
  - Nouvel événement `EmployeeRoleAssigned`, journalisé dans `audit_logs` (actions `role_assigned` / `role_revoked`) avec l'ancien et le nouveau `manager_role`, et l'identité du manager principal ayant fait le changement
  - Couvre les deux chemins existants : `POST /employees/{id}/assign-role` (dashboard web) **et** `PATCH /employees/{id}` (utilisé par l'app mobile manager `TeamScreen._toggleHrRole`), qui contournait auparavant tout audit
  - Tests Feature : `RoleAssignmentAuditTest` (6 tests couvrant nomination/revocation sur les deux endpoints, non-régression sur les champs non lies au rôle, et rejet d'un manager non-principal)

### Fixed
- **PostgreSQL : préserver la transaction après une candidature partenaire dupliquée (issue #4978).**
  - `PartnerService::apply()` exécute désormais la création dans une transaction imbriquée/savepoint ; une violation unique attendue est rollbackée localement avant d’être convertie en `ALREADY_EXISTS`.
  - Évite l’état `25P02 current transaction is aborted` qui contaminait les tests suivants après le scénario de course/idempotence.
- **Edge : restaurer la compatibilité de `edge:detect-silent-nodes` sans remplacer `edge:monitor`.**
  - La commande legacy accepte `--threshold` et `--dry-run`, détecte uniquement le schéma historique `node_id` et n’est pas planifiée ; le scheduler continue d’utiliser le monitor canonique UUID `edge:monitor`.
  - Lorsque le schéma legacy est absent, la commande se termine proprement avec une indication d’utiliser `edge:monitor`, évitant `CommandNotFoundException` en CI et dans les scripts historiques.
- **Tests webhook email : configurer explicitement le secret dans les scénarios de succès.**
  - `EmailBounceWebhookControllerTest` injecte désormais un secret de fixture pour tester les réponses 200 ; le scénario fail-closed sans secret reste couvert par `EmailBounceWebhookTest`.
- **Estimation salariale : restreindre les endpoints nominatifs aux managers.**
  - `quick-estimate` et `receipt` refusent désormais les employés non managers ; l’auto-service reste disponible via les endpoints `/me` dédiés.
  - Maintient le scope tenant et équipe de `EmployeePolicy::view` après le contrôle de rôle.
- **PostgreSQL : préserver l’exception racine lors de la restauration du tenant.**
  - `TenantManager::resetToPrevious()` ignore uniquement le `25P02` produit par le cleanup d’un transaction déjà abortée ; toute autre erreur de restauration continue d’être propagée.
  - Les erreurs SQL réelles de `ProvisionGuidedTrial` ne sont plus remplacées par `SET search_path`, ce qui rend le diagnostic et le retry fiables.
- **Provisioning demo : conserver `pending` pendant les retries du job.**
  - `ProvisionDemoTenantJob::handle()` ne marque plus prématurément la ligne `trial_provisionings` comme `failed` ; le statut final est écrit uniquement par `failed()` après épuisement des tentatives.
- **Provisioning demo : renseigner le `password_hash` avant l’INSERT du manager.**
  - `ProvisionGuidedTrial` prépare désormais le manager avec `forceFill()` puis l’insère en une seule opération ; cela respecte la contrainte PostgreSQL NOT NULL de `employees.password_hash`.
- **fix(api): checklist onboarding unifiée — plus de 403 employé, shape unique (Closes #3239).**
  - `GET /onboarding/checklist` (moteur calculé) ne requiert plus `viewAny` : tout utilisateur authentifié du tenant (employé non-manager inclus) peut lire sa checklist (données scopées à sa société par le middleware tenant) ; les écritures `complete`/`skip` gardent leur RBAC existant
  - Shape canonique unique documentée (moteur calculé en référence) : `data{ completed_steps, total_steps, progress_percent, progress (alias), go_live_ready, next_actions, steps }` — `GET /onboarding-setup/checklist` (moteur DB) wrappe désormais sa collection sous `data.steps` et `GET /onboarding-setup/progress` expose aussi `progress_percent`
  - Tests : `OnboardingChecklistTest` (employé → 200 + structure canonique) et `OnboardingStepControllerTest` (assertions alignées sur la shape unifiée)
- **test(f-13b): migration des tests Feature HR + Attendance + SmartAttendance vers les vraies migrations (issues #1593 #1606).**
  - Les tests des modules HR (`HrControllerTest`), Attendance (13 fichiers) et SmartAttendance (5 fichiers) abandonnent le trait manuel `CreatesMvpSchema`/`CreatesSmartAttendanceSchema` (schéma SQL figé de ~2150 lignes, en dérive) au profit du trait `RefreshTenantDatabase` (vraies migrations `public` + `tenant`), sur le même pattern que Payroll et Absences
  - Créations `Company`/`Employee` alignées sur les colonnes NOT NULL du vrai schéma (`plan_id`, `subscription_start`/`subscription_end`, `language`, `currency`, `first_name`/`last_name`)
  - Le test de sécurité `KioskCrossTenantIsolationTest` (surface kiosk du module Attendance) est migré lui aussi ; les appels aux tables SmartAttendance créées à la main sont supprimés (tables désormais créées par les migrations)

## [4.24.0] - 2026-08-11

> Première release publique. Notes complètes : [GitHub Release v4.24.0](https://github.com/kitokoh/leopardo-hr/releases/tag/v4.24.0) et `CHANGELOG_ARCHIVE.md`. Seules les entrées backend marquantes sont reprises ici.

### Security
- Purge complète de l'historique git (secrets) + plan de rotation des forks (#1723).
- Durcissement : SSO SAML/OIDC chiffre `certificate`/`client_secret` au repos, callbacks 501 explicites, uploads contraints (MIME allow-lists), `edge_token` haché au repos, `$guarded = []` remplacé par `$fillable` explicite sur 13 modèles.
- `SENTRY_TRACES_SAMPLE_RATE` 0.1 ; `DISABLE_DEMO_SEEDING=true` en production.

### Fixed
- `app/Services/` supprimé (fin du shim backward-compat DDD) (#1728).
- Tests + coverage : `BiometricPurgeExpiredTest` aligné, coverage par module dans le gate (#1726).

## [4.23.5] - 2026-07-19
### Added
- Audit vitrine acquisition/conversion `PA2-MKT-008..014` (docs).
### Changed
- Archivage de `docs/PLAN_ACTION/` → `docs/archive/PLAN_ACTION/`.

## [4.23.4] - 2026-07-19
### Fixed
- Compilation Dart : `main()` async dans les 3 apps mobiles.
### Changed
- CI : pinning SHA des actions tierces + actions composites `setup-backend-db` / `setup-flutter-android`.

## [4.23.3] - 2026-07-19
### Security
- Résolution des 34 alertes Dependabot (composer + npm : symfony/yaml, form-data, vite, next…).

## [4.23.2] - 2026-07-16
### Fixed
- CodeQL high sur `deploy-main.yml` (injection `head_branch`), permissions GITHUB_TOKEN explicites.
- `phpstan-modules.neon` : include Larastan manquant (cause des 36 erreurs « Call to an undefined method »).
### Added
- Module Marketing Phase 2 : policies, actions applicatives, client Ayrshare (21 tests).
- Fix contrainte CHECK Postgres `manager_role` incluant `marketing`.

## [4.23.1] - 2026-07-16
### Added
- Module Marketing Phase 1 : schéma `social_accounts`/`social_posts`, modèles Eloquent chiffrés.

## [4.23.0] - 2026-07-16
### Fixed
- `manager_role = 'marketing'` accepté par `StoreEmployeeRequest`/`UpdateEmployeeRequest`.

## [4.22.8] - 2026-07-12
### Added
- Drip email onboarding (J+1/J+3/J+7) via `SendTrialDripEmailJob` ; modèle `OnboardingProgress` ; wizard onboarding mobile manager (`PATCH /onboarding-setup/{stepKey}/complete|skip`) ; k6 corrigé.
### Fixed
- Imports `App\Models\*` invalides dans les jobs trial → FQCN DDD.

## [4.22.7] - 2026-07-05
### Fixed
- ParseError PHP (double backslash de namespace) sur 7 règles pays + 10 fichiers Core/Modules.
- Bug métier : `break_minutes` jamais déduit des heures travaillées (relation `schedule()` manquante sur `AttendanceLog`).
- Warning `Undefined array key "net_imposable"` dans `SocialDeclarationGenerator::generateDsnFr()`.

## [4.22.6] - 2026-07-05
### Security
- Fuite du token SSE en query parameter (admin) → jeton court `sse-token`.
- Mot de passe Upstash Redis exposé dans l'historique git (documenté, rotation requise).
### Added
- Vérification email OTP pour le signup trial : `POST /api/v1/trial/signup` (CompanyRequest pending + OTP 30 min) et `POST /api/v1/trial/verify` (provisionnement).
- `front/web/.env.local.example` documenté.
### Fixed
- PHPStan Modules : `AbsenceService::request()`/`LeavePolicyController::balances()` lisaient des colonnes inexistantes (`allocated`, `carried_over`) → `balance - used`.

## [4.22.5] - 2026-07-05
### Fixed
- Isolation multi-tenant des Jobs en file : interface `TenantScopedJob` + middleware `EnsureTenantContext` appliqué à tous les jobs tenant-scoped (paie, notifications, PDF, webhooks, EdgeSync).

## [4.22.4] - 2026-07-04
### Security
- Suppression du code mort `PaymentWebhookController::stripe()` (payload Stripe sans vérification de signature).
### Fixed
- 136 échecs CI résolus (AbsenceType fillable/company_id, casts AttendanceLog, DROP TABLE CASCADE Edge, mojibake PHPDoc, `FeatureManifestController::filterFeaturesByPermissions` lisait `required_permissions` au lieu de `permissions`…).
### Added
- `.env.example` : variables Google OAuth + Firebase documentées.

## [4.22.3] - 2026-07-04
### Fixed
- 160 échecs CI résolus (fillable `company_id` sur `Absence`/`ExpenseClaim`, colonne `rejection_reason`, shims `App\Models\*`).

## [4.22.2] - 2026-07-04
### Fixed
- 75 shims `app/Models/*.php` (class_alias absolu), `Factory::guessFactoryNamesUsing()`, migration `edge_nodes` down() sans CASCADE, 21 modèles sans `use`, `updated_at` manquants.

## [4.22.1] - 2026-07-02
### Fixed
- Nettoyage documentation : mojibake diagrammes UML, orphelins `assets/diagrams/`, doublon GOTO_MARKET, 9 liens Markdown cassés.

## [4.22.0] - 2026-07-01
### Changed
- Nettoyage architectural Phase 2 : 17 modèles, 13 services, 64 FormRequests migrés vers les modules DDD (shims backward-compat en place).

## [4.21.0] - 2026-07-01

### Changed
- **Refactor architecture DDD** : suppression des classes legacy doublonnées
  - 90 controllers supprimés dans `app/Http/Controllers/Api/V1/` (doublons des modules DDD)
  - 26 services supprimés dans `app/Services/` (51 consommateurs migrés vers les modules)
  - `app/DTOs/` racine supprimé (`CheckInDTO`, `CreateEmployeeDTO`, `UpdateEmployeeDTO` migrés)
  - Infrastructure créée pour `Growth`, `Platform`, `Onboarding`, `Training` (corrige 4 violations CI)
  - Conservés : `EdgeController`, `EdgeDownloadController`, `SSO/SSOController`
  - Surface API inchangée — aucune régression

## [4.18.0] - 2026-06-29

### Added
- **Module SmartAttendance** : pointage GPS automatique par géofencing
  - Phases 3/4/5 : Flutter geofencing (leopardo_employee), dashboard Manager/RH, tests Feature complets
  - API : endpoints `/api/v1/smart-attendance/*` (config, geo-events, sessions, validation, dashboard)
  - Modèles : `GeoAttendanceSession`, `EmployeeLocationEvent`, `AttendanceModeSettings`, `EmployeeAttendancePreferences`
  - Commande Artisan `smart-attendance:auto-close` — fermeture automatique des sessions GPS orphelines
  - Scheduler : `Schedule::command('smart-attendance:auto-close --hours=14')->hourly()`
  - Tests Feature : `AttendanceModeConfigTest`, `GeoEntryExitTest`, `GeoSessionDashboardTest`, `ManagerValidationTest`, `MultiTenantIsolationTest`
  - Trait de test `CreatesSmartAttendanceSchema` avec création/suppression des 4 tables
  - Flutter Employee : écran `SmartAttendanceScreen`, `GeofenceService` (Haversine), `BackgroundLocationService`
  - Flutter Manager/HR : feature `smart_attendance` avec écrans validation (liste pending + approve/reject), dashboard stats, navigation
  - Web dashboard : page `/smart-attendance` avec sessions listing, approbation et settings
  - Permissions Android : `ACCESS_BACKGROUND_LOCATION`, `FOREGROUND_SERVICE`, service `BackgroundWorker`
  - Permissions iOS : `NSLocationAlwaysAndWhenInUseUsageDescription`, `UIBackgroundModes` (fetch, processing)

## [4.17.0] - 2026-06-15

### Added
- Module IA : chat conversationnel et commandes vocales (léopardo_employee + leopardo_manager)
- Module Véhicules : suivi position GPS flotte (leopardo_manager)

### Fixed
- Correction timeout auth Sanctum sur reconnexion réseau mobile
