# Tasks: Vague QA Hardening 2026-08-14

**Input**: spec.md + plan.md

**Prerequisites**: plan.md (required), spec.md (required)

## Phase 1 — Backend endpoints mobiles (US1)

- [x] T001 [P1] US1 Ajouter `GET /api/v1/me/training-enrollments` (alias de `SelfServiceController@myTrainings`) + enrichir `TrainingEnrollmentResource` avec `course_title`, `session_date`, `progress` (additif) — l'écran Formation employee affiche les inscriptions. Tests `MeTrainingEnrollmentsTest` (shape + isolation tenant + `/me/trainings` intact), OpenAPI, matrice, `mobile-workflow-contracts.json`.
- [x] T002 [P1] US1 Ajouter `GET /api/v1/me/vehicles` (`VehicleController@myVehicles`) : véhicules `assigned_driver_id` = employé courant, position Traccar best-effort (null-safe), isolation tenant. Tests `MeVehiclesTest` (liste, empty `data=[]`, cross-tenant 404, position sans tracker), OpenAPI, matrice.

## Phase 2 — Cockpit : chemins corrigés + endpoints créés (US2)

- [x] T003 [P1] US2 Corriger `chat/ChatView.vue` : `/v1/ai/conversations` → `/admin/ai/conversations` (+ `/{id}/messages`).
- [x] T004 [P1] US2 Corriger `exports/ExportsView.vue` : `/v1/hr-reports` → `/admin/hr-reports` + feedback d'erreur (pas de catch silencieux).
- [x] T005 [P1] US2 Corriger `fleet/FleetView.vue` : `/v1/fleet/alerts` → `/admin/fleet/alerts`.
- [x] T006 [P1] US2 Corriger `marketing/MarketingOAuthView.vue` : PUT `/v1/platform/marketing/oauth-config` → `/admin/platform/marketing/oauth-config` + feedback.
- [x] T007 [P1] US2 Backend : ajouter `GET /api/v1/training/sessions` et `GET /api/v1/training/enrollments` (tenant, paginés) sur `TrainingController` + brancher `training/TrainingView.vue` (onglets Sessions/Inscriptions) — tests, OpenAPI, matrice.
- [x] T008 [P1] US2 Backend : ajouter `POST /api/v1/webhooks/{webhookEndpoint}/test` (dispatch `webhook.test` tracé dans `webhook_deliveries`, 422 si invalide) + brancher `webhooks/WebhooksView.vue` (bouton Tester) — tests, OpenAPI, matrice.

## Phase 3 — Suppression des mocks cockpit (US3)

- [x] T009 [P1] US3 Réécrire `users/UsersView.vue` sur données réelles : invitations (`GET /invitations`, `POST /invitations/{id}/resend`), impersonation réelle (`POST /platform/impersonations`) ; retirer `generateMockUsers`/`setTimeout`/modales simulées ; exporter les données chargées ; supprimer les actions sans backend au lieu de les simuler.
- [x] T010 [P2] US3 Réécrire `analytics/AnalyticsView.vue` : KPI `/admin/dashboard/stats`, activités `/admin/dashboard/activities`, alertes `/admin/dashboard/alerts` ; retirer cohortes/funnels/segmentation fabriqués (état vide documenté) ; supprimer l'export JSON fabriqué.
- [x] T011 [P2] US3 `system/SystemView.vue` : Health Check → `/health/live` + `/health/ready` ; maintenance/backups/config → état « non disponible » explicite (plus de simulation locale) ; conserver les cartes observability réelles.

## Phase 4 — Hygiène et petits défauts (US4)

- [x] T012 [P2] US4 `.env.example` : ajouter `BIOMETRIC_RETENTION_MONTHS` et `MAIL_URL` (parité `config/`, issue #1487) — `check-env-example-parity.sh` vert.
- [x] T013 [P2] US4 Boutons/liens morts + petits bugs : Growth « Gérer » (brancher fiche partenaire ou retirer), CompanyDetail « Accès Super-Console » (brancher ou retirer), Analytics « Voir détails » + select funnel (brancher/retirer), LoginView liens `#` (Mot de passe oublié/Sécurité/Support), `TaxRatesView` envoyer `legal_reference` dans le payload, `openRequest(id)` utiliser l'id du lead, `UserDetailView` sans fallback trompeur, classe `glass-bg0/50` → token réel (Training/Contracts/AuditLogs).

## Convergence

- [ ] T014 Mettre à jour `.specify/memory/project-state.md` (nouveaux endpoints), `CHANGELOG.md`, `AGENTS.md` (leçons QA), et cocher les tâches T001-T014 dans ce fichier après merge.
