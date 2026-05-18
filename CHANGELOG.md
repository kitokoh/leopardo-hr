#  CHANGELOG - LEOPARDO RH 
# Format : Keep a Changelog (keepachangelog.com)
# Versioning : Semantic Versioning (semver.org)

## [4.16.79] - 2026-05-18

### Docs - Nettoyage depot distant

- Documentation : ajout dans `AGENTS.md` du retour d'experience sur le nettoyage des branches distantes Devin/GTM/mobile, la synchronisation des PR restantes apres chaque merge et le pruning des refs locales.

## [4.16.78] - 2026-05-18

### Fix — PR #495 GTM / vitrine

- Vitrine : compatibilite `CTASection` avec les contrats `title`/`description`/`primaryCta` utilises par les nouvelles pages GTM.
- Gouvernance : ajout d'une trace changelog pour les nouvelles surfaces GTM avant merge.
## [4.16.77] - 2026-05-17

### Feat — PR #488: API Integrations (G8, L6, L5, H1-H4)

**Push Notifications (G8):**
- New `PushNotificationService` with FCM HTTP v1 support, batch sending (500 tokens/chunk), automatic token invalidation
- New `DeviceTokenController`: register/unregister/list tokens, send test notifications (manager only)
- Migration: `device_tokens` table with employee_id, token, platform (ios/android/web)

**Calendar Sync (L6):**
- New `CalendarSyncService` with Google Calendar and Microsoft Outlook Graph API integration
- Syncs approved leaves and training sessions as calendar events
- New `CalendarSyncController`: connect/disconnect providers, trigger sync, list events
- Migrations: `calendar_connections` and `calendar_events` tables

**ZKTeco Integration (L5):**
- New `ZktecoIntegrationService`: device management, attendance sync (pull), user push
- New `ZktecoController`: full CRUD for devices, heartbeat endpoint, attendance sync, sync logs
- Attendance records mapped to `attendance_logs` table with punch type resolution
- Migrations: `zkteco_devices`, `zkteco_sync_logs` tables
- Device-to-server endpoints (heartbeat, sync) operate without Sanctum auth

**Kiosk Extensions (H1-H4):**
- H1: `employeeInfo` — post-punch employee info (name, department, position, today attendance, leave balances)
- H2: `announcements` — active company announcements with priority ordering
- H3: `leaveBalance` — employee leave balance lookup by identifier
- H4: `qrPunch` — QR code-based attendance punching (base64 JSON decode)
- Migration: `kiosk_announcements` table

**Infrastructure:**
- Firebase config added to `config/services.php`
- New route module `routes/modules/integrations.php`
- Updated `SCENARIOS_TEST_API_GITHUB_ACTIONS.md` with all new endpoints
- Maintenance: alignement Pint des nouvelles surfaces kiosk/ZKTeco avant merge de la PR.

## [4.16.76] - 2026-05-17

### Fix — PR #487 consolidation backend gates

- Fix : callbacks SSO publics compatibles UUID entreprise en supprimant la contrainte numerique de route.
- Fix : configuration SSO sans `COALESCE(created_at, NOW())` dans un `INSERT`, incompatible PostgreSQL.
- Fix : workflows IA paie/rapport hebdomadaire alignes avec le schema RH reel (`absence_type_id`, `salary_structure_id` optionnel).
- Fix : predictions IA et planning type-safe pour PHPStan (relations explicites, dates, ids, floats, listes de facteurs).
- Fix : routes planning exposees sur `/api/v1/planning/*` au lieu de `/api/v1/v1/planning/*`.
- Fix : predictions turnover compatibles avec les employes sans departement assigne et notifications proactives tolerantes aux variantes de colonne solde conges (`remaining`, `remaining_days`, `ba[...]
- Tests : fixture MVP ajustee pour `shared_tenants`, `contracts`, `contract_amendments` et `salary_structures`.

## [4.16.72] - 2026-05-17

### Feat — Iteration 12 : E1/E2/E10/E11 completion, C14 planning optimization, WCAG corrections

- Nouveau : onglet "Structures salariales" dans PayrollView (E1 complet — structures + runs + bulletins + export).
- Nouveau : `MetricCard.vue` — composant partage avec tendance, formatage devise/pourcentage (E10).
- Nouveau : `ReportsView.vue` — ecran rapports RH avec MetricCard KPIs et onglets (effectifs, absenteisme, turnover, heures supp., masse salariale) (E8).
- Nouveau : routes `/reports` et navigation sidebar pour rapports RH et journal d'audit.
- Nouveau : `PlanningOptimizer.php` — service IA optimisation planning hebdomadaire avec couverture departement, detection conflits, recommandations et score (C14).
- Nouveau : `PlanningController.php` — endpoints `GET /v1/planning/weekly-optimization` et `GET /v1/planning/shift-rebalancing`.
- Nouveau : `PlanningOptimizationTest.php` — tests Feature planning.
- WCAG : `role="alert"` sur notifications toast, `aria-sort` sur DataTable triable, `type="search"` + `aria-label` sur champ recherche, `caption` sr-only optionnel.
- Plan 15 : E1, E2, E10, E11, C14, F1-F6 passes en DONE.
- Sidebar admin : ajout liens rapports RH et journal d'audit.
## [4.16.75] - 2026-05-17

### Docs — Iteration FINALE : mise a jour documentation globale Plan 15

- Mise a jour : `AGENTS.md` — section "Iterations 7-11 Plan 15" avec 12 lecons operationnelles (predictions IA, SSO stub, WCAG, mobile existant, backlog).
- Mise a jour : `15_PLAN_EXECUTION_CONSOLIDE.md` — synthese globale mise a jour avec pourcentages et declaration de cloture etendue iterations 1-11.
- Mise a jour : date `AGENTS.md` → 2026-05-17.
- Bilan Plan 15 iterations 1-11 : 5 PRs (7-11), 15+ services/controllers, 30+ tests Feature, 3 audits (WCAG, RBAC, conformite), SSO stub, predictions IA, dashboard predictif.

## [4.16.73] - 2026-05-17

### Feat — Iteration 10 : Predictions IA, dashboard predictif, mobile enrichments

- Nouveau : `App\AI\Predictions\TurnoverPredictor` — prediction du turnover par departement et employe, scoring facteurs de risque (anciennete, absences frequentes, departement a fort turnover).
- Nouveau : `App\AI\Predictions\AbsenteeismPredictor` — prediction absenteisme avec saisonnalite, tendances departementales et recommandations IA.
- Nouveau : `App\AI\Predictions\ProactiveNotificationService` — notifications proactives IA (contrats expirants, periodes d'essai, anniversaires, approbations en retard, formations incompletes, [...]
- Nouveau : `PredictionController` — endpoints `/api/v1/predictions/turnover`, `/absenteeism`, `/notifications` avec controle RBAC manager principal/RH.
- Nouveau : `PredictionsView.vue` — dashboard predictif admin avec cartes turnover, absenteisme, notifications proactives, barres de risque departement.
- Route admin : `/predictions` ajoutee au router (lazy import).
- Mobile : enrichissement absences (provider `leaveBalancesProvider`, methode `getLeaveBalances` dans `AbsenceRepository`).
- Verification : E6 FleetView (197 lignes, DONE), E7 ChatView (191 lignes, DONE), G2-G7 mobile (DONE), G9 carte vehicule (DONE).
- Tests : `PredictionControllerTest` — 6 tests Feature (RBAC + structure reponse turnover/absenteisme/notifications).
- Plan 15 : C11, C12, C13, C15, E6, E7, G2-G7, G9 passes en DONE.
- REGISTRE scenarios test API mis a jour.
## [4.16.74] - 2026-05-17

### Feat — Iteration 11 : SSO SAML/OIDC stub + audit WCAG 2.1 AA

- Nouveau : `App\Services\SSO\SSOService` — service SSO multi-protocole (SAML 2.0, OpenID Connect) avec configuration par entreprise, activation/desactivation et callbacks stub.
- Nouveau : `App\Services\SSO\SSOProviderConfig` — DTO configuration SSO (entity_id, sso_url, slo_url, certificate, name_id_format).
- Nouveau : `SSOController` — 6 endpoints : `GET /sso/providers` (public), `GET /sso/status`, `POST /sso/configure`, `DELETE /sso/disable` (RBAC principal), `POST /sso/saml/{id}/callback`, `GET [...]
- Nouveau : migration `create_company_sso_configs_table` — table SSO config par entreprise (provider, config JSONB, is_active), idempotente.
- Nouveau : `routes/modules/sso.php` — routes SSO separees (callbacks publics + gestion authentifiee).
- Nouveau : `docs/security/WCAG_ACCESSIBILITY_AUDIT.md` — audit complet WCAG 2.1 AA (34 criteres, 23 conformes, 11 partiels, 0 non-conformes, score 68%).
- Fix : `DashboardLayout.vue` — ajout lien "Aller au contenu principal" (WCAG 2.4.1) + `id="main-content"` sur `<main>`.
- Fix : `web/src/app/layout.tsx` — ajout lien "Aller au contenu principal" (WCAG 2.4.1).
- Tests : `SSOControllerTest` — 8 tests Feature (providers publics, RBAC status/configure/disable, validation provider, callback SAML).
- Plan 15 : K2 (SSO stub) et K4 (WCAG audit) passes en DONE.

## [4.16.71] - 2026-05-17

### Feat — Iteration 9 : Audit UI, good first issues, release prep

- Nouveau : `AuditLogsView.vue` — journal d'audit admin avec filtres (action, type, recherche), export CSV, panneau detail slide-over avec diff avant/apres (old_values vs new_values).
- Nouveau : route `/audit` dans admin router (lazy import, code splitting conserve).
- Nouveau : `GOOD_FIRST_ISSUES.md` — 10 issues documentees pour contributeurs debutants (validation IBAN, i18n arabe, dark mode, export PDF, tests health, etc.).
- Nouveau : `RELEASE_v0.1.0.md` — notes de release pour la premiere version publique GitHub.
- Confirme : E4 (recrutement pipeline Kanban) est DONE — 308 lignes avec KanbanBoard, 6 stages pipeline, avancer/retourner candidats, creation poste inline.
- Plan 15 : E4, E9, I2, I5 passes en DONE.
- SCENARIOS_TEST_API et REGISTRE mis a jour.
