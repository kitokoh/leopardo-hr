# Feature Specification: QA Pass Plateforme — 2026-08-14

**Feature Branch**: `qa-pass-plateforme-2026-08-14`

**Created**: 2026-08-14

**Status**: In progress

**Input**: Constitution `.specify/constitution.md` + AGENTS.md + audit fonctionnel local (tests backend, PHPStan strict/modules, Pint, smoke API réel, lint/build frontends, scan propreté UI).

## Contexte

Audit de la plateforme demandé par le propriétaire : tester les workflows API, les vues, les boutons et les logiques ; tout manquement constaté doit être rédigé en tâches (technique Spec Kit) puis implémenté. Constat de départ : `main` est **rouge / non vérifié** — la file GitHub Actions est saturée (#2131, PR #2159 en cours par un autre agent) et plusieurs gates qualité locales échouent sur le HEAD actuel (`0feb18ad`).

## User Scenarios & Testing

### User Story 1 — Gates qualité backend verts sur main (Priority: P1)

Un contributeur ou agent qui pousse sur `main` obtient des checks CI verts : suite Unit et Feature verte, PHPStan Strict (level 8) sans nouvelle erreur, Pint propre.

**Independent Test**: `php artisan test --testsuite=Unit`, `php artisan test --testsuite=Feature`, `vendor/bin/phpstan analyse --configuration=phpstan-strict.neon`, `vendor/bin/pint --test` — tous verts sur une branche issue de `origin/main`.

**Acceptance Scenarios**:

1. **Given** le HEAD `0feb18ad`, **When** on lance la suite Unit, **Then** tous les tests passent — aujourd'hui `AbstractCountryRulesCapTest::test_ivory_coast_cnss_capped_at_1647315_xof` échoue (attend 228 849,79, code+goldens #1913 → 79 554,18 : test périmé).
2. **Given** le HEAD `0feb18ad`, **When** on lance PHPStan Strict level 8, **Then** zéro erreur — aujourd'hui 43 erreurs (7 code applicatif, 36 tests, + dérive baseline PayrollRunControllerTest 29→31).
3. **Given** le HEAD `0feb18ad`, **When** on lance `pint --test`, **Then** zéro fichier à reformater — aujourd'hui la dette de formatage `tests/Unit` fait échouer le check.
4. **Given** un PR qui ne touche pas le dead catch `PayrollSimulationController` (issue #2162, autre agent), **Then** le check « PHPStan — Modules Architecture » reste dépendant du merge de #2162 (dépendance documentée, pas dupliquée).

### User Story 2 — Propreté UI : zéro mojibake visible (Priority: P2)

Un utilisateur qui navigue la vitrine (`front/web`) ou l'admin dashboard (`front/admin-dashboard`) ne voit aucun caractère double-encodé (`Ã©`, `â€”`, arabe mojibake `Ø§Ù†Ø¶Ù…`…).

**Independent Test**: `rg -n 'Ã‰|Ã¨|Ã©|Ãª|Ã  |Ã§|â€|Â°|Ø§Ù†' front/web/src front/admin-dashboard/src` → 0 occurrence. Les chaînes corrigées restent exactes (ex. « Économisez 20% », « Données hébergées en Europe — conforme RGPD »).

**Acceptance Scenarios**:

1. **Given** `/checkout` (web), **When** on affiche la page, **Then** les em-dashes et accents sont corrects (`—`, `é`), pas `â€”`/`Ã‰`.
2. **Given** `/download` (web), **When** on affiche le label fallback arabe, **Then** l'arabe est en Unicode lisible (`انضم إلى قائمة الاختبار`), pas en mojibake.
3. **Given** l'admin dashboard, **When** on consulte Dashboard, System, Support, Users, **Then** « à », « Édition », « Étape », « Équipe » s'affichent correctement.

### User Story 3 — Admin SystemView : actions UI réellement fonctionnelles (Priority: P3)

Le super-admin qui ouvre la vue Système peut activer/désactiver une tâche automatisée, en éditer/supprimer, et piloter la configuration d'auto-scaling via des boutons — ou, si ces surfaces sont hors périmètre produit, le code mort est retiré (pas de fonctions fantômes ni de boutons sans effet).

**Independent Test**: lint admin-dashboard sans warning `no-unused-vars` pour `SystemView.vue` ; les handlers de tâches/scaling sont référencés par des `@click` ou supprimés.

**Acceptance Scenarios**:

1. **Given** la vue Système, **When** on interagit avec la liste des tâches automatisées, **Then** toggle/édition/suppression ont un effet visible (toast + état) — aujourd'hui `toggleTask/editTask/deleteTask/handleTaskCreated` sont définis mais jamais appelés.
2. **Given** la section auto-scaling, **When** on clique les contrôles, **Then** `updateScalingConfig/manualScale/toggleLoadBalancerNode/drainNode` sont câblés ou retirés.
