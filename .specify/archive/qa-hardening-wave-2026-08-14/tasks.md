# Tasks: Vague QA & Durcissement Plateforme 2026-08-14

**Input**: spec.md + plan.md

**Prerequisites**: plan.md (required), spec.md (required)

## Phase 1 — Backend : suite verte & correctifs API (US1)

- [x] T001 [P] [US1] Exécuter la suite complète `php artisan test` (Unit + Feature) sur env CI-like (PHP 8.4, PG16, Redis) et recenser les échecs — *validation de référence*
- [x] T002 [US1] Corriger tous les échecs de la suite (5 unit payroll réalignés + Cabinet = collision DB des runs parallèles, pas un vrai échec) (régression fonctionnelle) à la source — *selon constat T001*
- [x] T003 [P] [US1] Écrire les tests Feature du module `user` (register/login/logout/me + employee-links) dans `api/tests/Feature/User/` — 0 test aujourd'hui
- [x] T004 [US1] `BankExportGenerator` : injecter IBAN/BIC tenant réels (config entreprise), refus explicite si absents — plus aucun `PLACEHOLDER_*` dans le XML
- [x] T005 [P] [US1] Consolider les routes notifications : `PUT /notifications/read-all` conservé + alias POST `mark-all-read` (compat) ; vérifier aucun client cassé
- [x] T006 [US1] PHPStan strict (level 8) vert + Pint — **0 erreur app/ (5 erreurs main rouge réparées)** + `pint --test` vert sur `api/`
- [x] T007 [US1] Smoke test API live — signup→verify→login→me→dashboard→simulate→employés→annonces→exports (le bug mot de passe employé a été DÉCOUVERT ici et corrigé) : health, auth login, un workflow métier (payroll simulate) — vérifier réponses réelles

## Phase 2 — Web App : boutons câblés (US2)

- [x] T008 [P] [US2] Dashboard `page.tsx` : câbler actions rapides (4), « Voir toute l'activité », cloche notifications, Leo IA (Oui/Plus tard), recherche
- [x] T009 [US2] Payroll `page.tsx` : œil « détail » → modal de détail du bulletin (champs payslip déjà chargés)
- [x] T010 [P] [US2] Careers : `onToggleDark` no-op → vraie bascule thème ou suppression du prop décoratif
- [x] T011 [US2] `npm run build` + `npm run lint` verts sur `front/web`

## Phase 3 — Admin Dashboard : boutons câblés (US3)

- [x] T012 [P] [US3] `AnalyticsView.vue` : écouter les 7 événements des widgets (churn/forecast/feature-adoption) et implémenter les handlers
- [x] T013 [P] [US3] `CompanyDetailView.vue` : « Accès Super-Console » → navigation réelle
- [x] T014 [P] [US3] `GrowthDashboardView.vue` : « Gérer » partenaire → navigation réelle
- [x] T015 [P] [US3] `EditUserModal.vue` : « Changer l'avatar » → sélecteur fichier + upload (ou désactivation explicite documentée)
- [x] T016 [US3] `npm run build` + `npm run lint` verts sur `front/admin-dashboard`

## Phase 4 — Mobile : patterns & compilation (US4)

- [x] T017 [P] [US4] Supprimer les 3 mutations `apiClient.dio.options.headers['Accept-Language']` (employee/manager/hr `user_auth_repository.dart:188`) — header par requête via `requestWithRetry`
- [x] T018 [P] [US4] `leopardo_marketing` : résoudre l'import `PrimaryButton` (→ `PulseButton` core ou vrai chemin) — l'app doit compiler
- [x] T019 [US4] Vérification statique finale : 0 occurrence pattern interdit restante, imports marketing résolus

## Phase 5 — Traçabilité & convergence (US5)

- [x] T020 [P] [US5] Ouvrir issues GitHub traçables — **fait le 2026-08-14** : SSO stub → #2251 (ref #1694) ; push FCM/APNs → #2252 ; magic link démo → #2253 ; drift OpenAPI → #2254 (bloqué PR #2147/#2156) ; fériés placeholder → #2255 (PA2-COUNTRY-012) ; mock admin Users/Analytics → #2256 ; provider email → #2257
- [x] T021 [US5] Entrées CHANGELOG.md pour chaque changement de comportement
- [x] T022 [US5] Mettre à jour `.specify/memory/project-state.md` + statut de la vague (spec.md Status → Implemented)
- [ ] T023 [US5] PR unique **#2306** (draft) — checks CI en cours ; merge + suppression branche après checks verts `qa-hardening-wave-2026-08-14` → main avec `Closes #2248` ; checks verts avant merge ; suppression branche après merge

## Constats non traités dans cette vague (rédigés, implémentation ultérieure)

- SSO SAML/OIDC complet (stub → 501, audit #1694) — issue T020
- Push mobile FCM/APNs (`NotificationDispatcher.php:29`) — issue T020
- Magic link démo (`ProvisionDemoTenantJob.php:35`) — issue T020
- Drift OpenAPI ↔ routes (168 routes non documentées, 15 chemins fantômes /exports* vs /export*, /partner* vs /growth/partner*, /i18n/{locale}, /bank-exports collection, /smart-attendance/sessions/{id}/validate) — issue T020 (bloqué par PR #2147/#2156 en cours)
- Calendriers fériés légaux officiels 9 pays (placeholder, PA2-COUNTRY-012) — issue T020
- Provider email implémentation réelle (`CommunicationService.php:404` audit-only) — issue T020
- Doublons verbes PUT/POST approve-reject absence/expense — conserver (compat documentée #1435), pas de changement
