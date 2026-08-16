# Feature Specification: QA 360° Audit Expert 20 — 2026-08-16

**Feature Dir**: `.specify/features/qa-360-audit-expert20-2026-08-16`
**Created**: 2026-08-16 | **Status**: Draft → In progress
**Base**: `origin/main` @ ee458778 / d7bbf1dd

## Problème

Audit 360° (API, vitrine, admin, mobile, CI, edge, kiosk) : 2 régressions P0 sur main
(errors.php ×4 ParseError — merge #4275 ; EmployeeService perd role/company_id → 500 —
merges #4249/#4288) et 30 manquements nouveaux vérifiés (voir findings-registry.md),
dé-dupliqués contre les ~30 issues ouvertes. Chaque manquement → issue GitHub
`[QA][Px][surface]...` via le protocole Spec Kit, puis implémentation en PRs unitaires
`Closes #<issue>`.

## User Stories & Testing

### US1 — Stabiliser main (P0-1, P0-2)
**Acceptance Scenarios**:
1. Given `php -l` sur `api/lang/*/errors.php`, When exécution, Then zéro ParseError (les 4 locales).
2. Given POST /api/v1/employees (role=employee et role=manager+manager_role=marketing), When réponse, Then 201 avec `data.role`/`data.manager_role`/`data.company_id` persistés.
3. Given PATCH /api/v1/employees/{id} (revoke rh → employee), When réponse, Then rôle réellement persisté.
4. Given `EmployeesRbacTest`, When suite, Then 100 % vert.

### US2 — API : erreurs localisées et codes stables (A-01 → A-11)
**Acceptance Scenarios**:
1. Given une exception métier paie, When réponse PayrollRunController, Then `localized_message` = `__('errors.CODE')` (catalogue), jamais `$e->getMessage()`.
2. Given une validation attendance, When réponse, Then message via catalogue `attendance.*`/`errors.*`, plus de FR littéral.
3. Given une erreur ContractLifecycle, When réponse, Then code stable + localized_message (pas de phrasing interne).
4. Given `PATCH /notifications/{id}/read`, When route:list, Then une seule définition.
5. Given `GET /sso/providers`, When middleware, Then throttle appliqué.
6. Given `edge:detect-silent-nodes`, When audit, Then supprimée ou portée sur le modèle EdgeNode.
7. Given les exceptions paie, When grep, Then une seule classe par concept, throwers/catchers alignés.
8. Given config/edge.php, sentry.php, cors.php, When inspect, Then aucune URL prod en dur (env-driven).
9. Given ApiTokenController/GrowthAdminController, When tests, Then Feature tests RBAC + isolation ajoutés.

### US3 — Vitrine : UX et i18n (W-01 → W-07)
**Acceptance Scenarios**:
1. Given un item FAQ sans `id` sur /employes, When clic, Then la réponse s'ouvre et le chevron tourne (aria-expanded présent).
2. Given `/docs#intro`, When clic, Then ancre existante.
3. Given newsletter/search/OTP, When audit a11y, Then inputs avec nom accessible.
4. Given le dashboard social, When audit, Then aria-label localisés.
5. Given Textarea/Select, When audit, Then useId() (pas de Math.random), Select supprimé ou utilisé.
6. Given /contact et /case-studies, When audit, Then valeurs par locale.

### US4 — Admin : contrat API et i18n (AD-01 → AD-07)
**Acceptance Scenarios**:
1. Given /system, When chargement, Then GET /platform/metrics/overview (200), plus de 404/double toast.
2. Given les 5 vues FR, When audit, Then t() + catalogues fr/en/ar/tr (lots #4206 suivants).
3. Given les 10 clés t() manquantes, When sync, Then présentes dans les 4 catalogues.
4. Given MetricCard commun, When grep, Then supprimé.
5. Given un échec API (chat/webhooks/system), When chargement, Then état d'erreur visible + retry, pas de liste vide muette.
6. Given un vehicle tenant avec HTML dans plate_number, When popup Leaflet, Then texte échappé (pas d'injection).

### US5 — Mobile : i18n smart-attendance + hygiène (M-01 → M-05)
**Acceptance Scenarios**:
1. Given les écrans smart-attendance (employee/manager/hr), When audit, Then chaînes via AppLocalizations (ARB ×4 locales).
2. Given le marketing app, When démarrage, Then les 4 locales initialisées.
3. Given le screen smart-attendance employee, When format date, Then pattern locale-aware (plus de dd/MM/yyyy en dur).
4. Given SyncService, When stop(), Then subscription annulée.
5. Given un échec résumé journalier, When chargement, Then log + état dégradé.

### US6 — CI/Ops : queues, famine, coûts (C-01 → C-07)
**Acceptance Scenarios**:
1. Given render.yaml, When worker, Then `--queue` inclut webhooks et audit (conforme AGENTS.md:346).
2. Given un run parent annulé, When deploy-staging, Then échec explicite ou polling SHA (pas de skip silencieux).
3. Given un push docs/mobile, When coverage-gate, Then pas de re-run de la suite complète.
4. Given la matrice mobile hr, When workflow, Then FIREBASE_APP_ID_HR explicite.
5. Given CHANGELOG, When grep, Then zéro doublon exact.
6. Given CI_CD_SECRETS.md, When diff vs workflows, Then inventaire à jour.
7. Given un push frontend, When i18n-enterprise, Then un seul run raisonnable (chemins non redondants).

## Plan technique (résumé)
- P0 : fermeture `];` errors.php ×4 ; EmployeeService create/update → Arr::except + forceFill (pattern #3677/#4151).
- API : `__('errors.CODE')` pour PayrollRun/Attendance/Contract ; suppression route dupliquée ; throttle sso ; nettoyage commande/exceptions ; env-driven URLs ; tests manquants.
- Web : fix openId FAQSection (+aria) ; ancre docs ; a11y inputs ; aria localisés ; useId ; data par locale (contact, case-studies).
- Admin : SystemView → /platform/metrics/overview ; lots i18n (5 vues + 10 clés) ; suppression MetricCard ; états d'erreur ; échappement popup Fleet.
- Mobile : smart-attendance → ARB ; marketing 4 locales ; pattern date ; cancel subscription ; log dégradé.
- CI/Ops : render.yaml queues ; deploy-staging SHA polling ; coverage-gate paths ; FIREBASE_APP_ID_HR ; dedup CHANGELOG ; secrets doc ; chemins i18n.

## Dépendances
- P0-1 (errors.php) bloque tout le backend CI → PR #4295 d'abord.
- P0-2 (EmployeeService) dépend de P0-1 pour les tests → PR #4308 ensuite.
- T019 (SystemView 404) indépendant ; lots i18n admin en séquence #4206-compatible.
- Les autres tasks sont indépendantes (fichiers disjoints) → PRs unitaires parallèles.

## Critères de succès
- 100 % des T### couverts par une issue GitHub ; chaque fix en PR `Closes #<issue>` + entrée CHANGELOG ;
- CI verte sur les PRs backend (Backend Coverage, PHPStan, Module Structure, ESLint+TS, actionlint) ;
- `EmployeesRbacTest` et la suite attendance/paie locales vertes avant merge ;
- main redevient vert et le reste (zéro ParseError, zéro test rouge).
