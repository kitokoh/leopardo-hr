# Plan: QA Pass Plateforme — 2026-08-14

**Input**: spec.md (US1-US3) + Constitution + registre project-state

## Architecture / Décisions techniques

### US1 — Gates qualité backend (P1)
- **Test périmé** : `tests/Unit/AbstractCountryRulesCapTest::test_ivory_coast_cnss_capped_at_1647315_xof` — ré-aligner sur les plafonds #1913 (famille/AT plafonnées séparément à 70 000 XOF, cf. `GoldenCiPayrollTest` qui attend 79 554,18). Le CODE est la source de vérité (goldens + générateurs alignés #1913) ; seul le test unitaire n'a pas suivi (#1913/#573c1f05 l'ont oublié).
- **PHPStan Strict app** :
  - `app/Listeners/NotifyTaxRateValidation.php` — `$model->company_id` sur `Model` générique : restreindre le type (docblock union `TaxSlab|SocialContribution` ou `getAttribute()`) aux 3 usages.
  - `app/Providers/EventServiceProvider.php` — `$listen` déclaré `array<class-string, array<int, class-string>>` mais contient des listeners `Class@method` (string) : élargir le PHPDoc à `array<int, class-string|string>`.
- **PHPStan Strict tests** (36 erreurs) : corriger les types dans les 13 fichiers de test de la vague multi-pays (null-safe, offsets tableaux, `assertNotNull` redondants, types de tableaux itérables, `superAdmin()` inutilisé…) + régénérer/ajuster la dérive baseline `PayrollRunControllerTest` (29→31 occurrences) en réduisant les 2 nouvelles occurrences réelles plutôt que d'élargir le baseline.
- **Pint tests/Unit** : exécuter `vendor/bin/pint` sur les fichiers `tests/Unit` en dette.
- **Dépendance** : dead catch `PayrollSimulationController.php:243` → issue #2162 (autre agent). NE PAS dupliquer ; documenter en dépendance de merge.

### US2 — Mojibake UI (P2)
- Script Python de ré-encodage ciblé (table de remplacement des séquences double-encodées : `Ã©`→`é`, `Ã‰`→`É`, `â€”`→`—`, `â€™`→`’`, `Ã `(NBSP)→`à`, arabe `Ø§Ù†Ø¶Ù…`→`انضم`…) appliqué à `front/web/src` (25 fichiers) et `front/admin-dashboard/src` (15 fichiers), avec vérification diff manuelle (aucune chaîne corrompue, aucun faux positif).
- Garder les mêmes chaînes corrigées dans les 4 locales FR/EN/TR/AR.

### US3 — SystemView actions (P3)
- `front/admin-dashboard/src/views/system/SystemView.vue` : soit câbler les handlers existants (`toggleTask/editTask/deleteTask/handleTaskCreated`, scaling) aux contrôles du template (rendre la liste des tâches automatisées actionnable), soit supprimer le code mort si la surface n'est pas rendue. Décision : câbler — la vue affiche déjà `automatedTasks` (état chargé ligne 408) et un modal de création (`showCreateTaskModal`) : rendre ces contrôles fonctionnels.

## Phases

### Phase 1 — US1 backend main vert (P1)
- T001 Test périmé CI aligné sur #1913
- T002 PHPStan Strict app (NotifyTaxRateValidation + EventServiceProvider)
- T003 PHPStan Strict tests (13 fichiers) + dérive baseline PayrollRunControllerTest
- T004 Pint tests/Unit
- Checkpoint : `php artisan test --testsuite=Unit` vert + `phpstan-strict` 0 erreur + `pint --test` 0 fichier.

### Phase 2 — US2 mojibake (P2)
- T005 Script de ré-encodage + application web (25 fichiers)
- T006 Application admin-dashboard (15 fichiers)
- T007 Vérification diff + lint/build frontends verts

### Phase 3 — US3 SystemView (P3)
- T008 Câblage des actions tâches automatisées (toggle/edit/delete + modal création)
- T009 Câblage auto-scaling / load-balancer OU retrait du code mort
- T010 Vérification lint admin (0 warning `no-unused-vars` sur SystemView)
