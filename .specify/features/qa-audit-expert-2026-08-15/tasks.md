# Tasks — QA Audit Expert 2026-08-15 (manquements nouveaux)

> Format strict : `- [ ] [TaskID] [P?] [Story?] Description avec chemin de fichier`.
> Chaque user story forme un incrément testable indépendamment.

## Phase 1 — Setup (aucun prérequis hors branche)

- [ ] T001 Créer la branche `docs/qa-audit-expert-2026-08-15` depuis `origin/main` avec les artefacts Spec Kit (spec.md, findings-registry.md, tasks.md, checklists/requirements.md) et ouvrir la PR docs (Closes issue registre).

## Phase 2 — Fondations

- [ ] T002 [P] Vérifier l'absence de branche existante pour #2652 et les nouvelles issues (`gh api repos/kitokoh/leopardo-hr/branches | grep <issue>`), puis self-assign + branche `fix/<issue>-<slug>` avec commit vide de claim (protocole anti-doublon #2400).

## Phase 3 — US1 : login API sans 500 (P0)

- [ ] T003 [US1] Ajouter la garde `tenantEmployeesTableExists($schema)` avant la requête `Employee` du premier chemin de lookup dans `api/app/Core/Auth/Infrastructure/Services/AuthService.php` (schéma absent → pas d'employé → `InvalidCredentialsException`).
- [ ] T004 [US1] Envelopper la résolution d'employé (lookup + fallback) d'un catch `QueryException` ciblé (`42P01`/table inconnue) → traiter comme « aucun employé » et logger en warning structuré, jamais 500.
- [ ] T005 [US1] Ajouter le test de régression `api/tests/Feature/Auth/DemoLoginDefensiveTest.php` (ou étendre `DemoUserControllerTest`) : lookup → schéma inexistant → 401 JSON (pas 500) ; lookup valide → 200 (inchangé).
- [ ] T006 [US1] Vérifier `vendor/bin/pint --test`, `phpstan` diff, et `php artisan test --filter=Demo` vert ; PR `fix/2652-...` avec `Closes #2652` + entrée CHANGELOG.

## Phase 4 — US2 : CHANGELOG gouvernance (P1)

- [ ] T007 [US2] Restructurer `CHANGELOG.md` sous `## [Unreleased]` : une seule occurrence de chaque header (`### Added` / `### Changed` / `### Fixed` / `### Removed`), entrées regroupées sans perte de contenu.
- [ ] T008 [US2] Vérifier la garde maison (scan des headers `### ` dupliqués, cf. `dev-hub/tools/check-governance.ps1` ou équivalent grep) passe ; PR `fix/<issue>-changelog-governance` avec `Closes #<issue>`.

## Phase 5 — US3 : alertes plateforme lisibles (P2)

- [ ] T009 [US3] Dans `front/admin-dashboard/src/views/analytics/AnalyticsView.vue:124-125`, mapper `alert.message` (fallback `alert.title`/`alert.description`) au lieu de `title`/`description` seuls.
- [ ] T010 [US3] `npm run lint` + build admin verts ; PR `fix/<issue>-analytics-alerts` avec `Closes #<issue>` + CHANGELOG.

## Phase 6 — US4 : attribution source vitrine (P2)

- [ ] T011 [US4] Dans `front/web/src/modules/vitrine/components/forms/SignupForm.tsx:187`, lire `source` depuis `useSearchParams()` (défaut `'signup_form'`) et l'envoyer dans le payload signup.
- [ ] T012 [US4] Test composant : `/signup?source=download_employee_android` → payload `source=download_employee_android` ; sans paramètre → `signup_form`. Lint + tsc + jest verts ; PR `fix/<issue>-signup-source` avec `Closes #<issue>` + CHANGELOG.

## Phase 7 — US5 : assainissements P3

- [ ] T013 [P] [US5] Ajouter `zod` aux dépendances de `front/web/package.json` (version alignée sur l'usage) ; `npm install` + build vert.
- [ ] T014 [P] [US5] Brancher `front/web/src/app/api/forms/verify/route.ts` sur `areFormsEnabled()` (même gate que les autres routes forms).
- [ ] T015 [P] [US5] `front/admin-dashboard/src/views/companies/CompanyDetailView.vue` : ajouter `toast.error(...)` (message localisé si dispo) dans les 4 catch (détail, tickets, abonnement, modules).
- [ ] T016 [P] [US5] `front/mobile_apps/leopardo_hr/lib/main.dart` : initialiser `SentryFlutter.init` (même pattern que `leopardo_employee`, dsn via `--dart-define`).
- [ ] T017 [P] [US5] Réaligner `front/web/e2e/conversion-funnel.spec.ts` et `front/web/e2e/forms-and-submissions.spec.ts` sur le formulaire sans mot de passe (champ `email` + bouton, pas de `password`).
- [ ] T018 [US5] Vérifications finales : builds vitrine/admin verts, `npm ls zod` OK, lint e2e, CHANGELOG entries ; PRs par issue avec `Closes #<issue>`.

## Polish & cross-cutting

- [ ] T019 [P] Mettre à jour `AGENTS.md`/CHANGELOG si une leçon opérationnelle émerge (ex. garde lookup défensif).
- [ ] T020 [P] Vérifier les checks GitHub Actions des PRs, merger les PR vertes (`gh pr merge --merge --delete-branch`), supprimer les branches.

## Dépendances

- US1 (T003-T006) indépendante → priorité absolue (P0).
- US2 (T007-T008) indépendante → P1.
- US3 (T009-T010), US4 (T011-T012), US5 (T013-T018) indépendantes → P2/P3.

## Exécution parallèle

- T003+T007+T009+T011+T013 peuvent partir simultanément (fichiers disjoints).
- Stratégie MVP : US1 d'abord (bloqueur prod), puis US2, puis P2/P3 par PR unitaire.
