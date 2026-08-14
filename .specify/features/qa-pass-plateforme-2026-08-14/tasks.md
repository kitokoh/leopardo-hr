# Tasks: QA Pass Plateforme — 2026-08-14

**Input**: spec.md + plan.md

**Prerequisites**: plan.md (required), spec.md (required)

## Phase 1 — US1 Gates qualité backend verts (P1) — issue #2172

- [x] T001 [P1] [US1] Ré-aligner `tests/Unit/AbstractCountryRulesCapTest::test_ivory_coast_cnss_capped_at_1647315_xof` sur les plafonds #1913 (famille/AT 70 000 XOF, employer = 79 554,18) — aligner le commentaire légal sur `GoldenCiPayrollTest`
- [x] T002 [P1] [US1] PHPStan Strict app — `app/Listeners/NotifyTaxRateValidation.php` : typer `$model` (docblock `TaxSlab|SocialContribution`) pour `$model->company_id` (lignes 125/128/160)
- [x] T003 [P1] [US1] PHPStan Strict app — `app/Providers/EventServiceProvider.php` : PHPDoc `$listen` élargi à `array<int, class-string|string>` (listeners `Class@method`)
- [x] T004 [P1] [US1] PHPStan Strict tests — corriger les 36 erreurs dans les 13 fichiers tests (BfIutsBackfillTest, PayrollRunControllerTest ×7 dont dérive baseline 29→31, PayrollAuditTest, PublicHolidayIslamicSeederTest, MultiCountry/*, SocialContributionAdminControllerTest, TaxSlabAdminControllerTest, CountryRulesResolverTest, ComplianceConfidenceApiTest, BulletinDeclarationReconciliationTest, MigrationSchemaPlacementTest)
- [x] T005 [P1] [US1] Pint — reformater les fichiers `tests/Unit` en dette (AuthServiceTest, EdgeDaemonSyncClientTest, EstimationServiceTest, EnsureTenantContextTest, FeatureTest, FeatureUnitTest, NotificationTest, SenegalRulesUnitTest, PayrollCalculatorUnitTest, FeatureRegistryTest, PushNotificationServiceTest)
- [x] T006 [P1] [US1] Vérification : `php artisan test --testsuite=Unit` vert, `phpstan-strict.neon` 0 erreur, `pint --test` 0 fichier — dépendance merge documentée : dead catch #2162 (autre agent)
- [x] T007 [P1] [US1] Entrée `CHANGELOG.md` + mise à jour `AGENTS.md` si leçon opérationnelle

## Phase 2 — US2 Propreté UI mojibake (P2) — issue #2173

- [x] T008 [P2] [US2] Script de ré-encodage ciblé (table `Ã©`→`é`, `Ã‰`→`É`, `â€”`→`—`, `â€™`→`’`, `Ã `→`à`, arabe mojibake→Unicode) dans `dev-hub/tools/` + exécution sur `front/web/src` (25 fichiers, dont `checkout/page.tsx`, `pricing/page.tsx`, `mobile/page.tsx`, `download/page.tsx` ligne arabe)
- [x] T009 [P2] [US2] Exécution sur `front/admin-dashboard/src` (20 fichiers)
- [x] T010 [P2] [US2] Vérification diff manuelle (pas de faux positif), `rg` mojibake = 0, lint + build web/admin verts

## Phase 3 — US3 Admin SystemView actions (P3) — issue #2174

- [x] T011 [P3] [US3] Câbler `toggleTask/editTask/deleteTask/handleTaskCreated` aux contrôles du template `SystemView.vue` (liste tâches automatisées actionnable)
- [x] T012 [P3] [US3] Câbler `updateScalingConfig/manualScale/toggleLoadBalancerNode/drainNode` OU retirer le code mort — zéro warning `no-unused-vars` sur SystemView
- [x] T013 [P3] [US3] Vérification : lint admin 0 erreur/0 warning SystemView, build admin vert

## Dependencies & Execution Order

- **Phase 1** bloque les PRs backend ; **Phase 2** frontend indépendante ; **Phase 3** dépend de rien (fichier différent).
- PRs : `fix/qa-<n>-main-vert` (Phase 1), `fix/qa-<n>-ui-mojibake` (Phase 2), `fix/qa-<n>-systemview-actions` (Phase 3) — chacune avec `Closes #<issue>`.
- Ne pas toucher au dead catch `PayrollSimulationController` (issue #2162, autre agent).


## Phase 4 — Bug runtime API (issue #2317, PR #2319) — 500 réels découverts par le smoke API

- [x] T014 [P1] [US1] `GET /approvals/pending` → 500 « Call to undefined method Builder::pending() » — `scopePending` ajouté sur `ApprovalRequest` + `ApprovalControllerTest` (pending uniquement, isolation tenant, transitions approve/reject)
- [x] T015 [P1] [US1] `GET /fleet/live-map` (+ `/vehicles/{id}/position|trips`) → 500 TypeError « Cannot assign null to property TraccarService::$token of type string » quand `TRACCAR_API_TOKEN` non configuré — token/URL castés + gardes fail-open (données vides) + test de régression `FleetControllerTest`
