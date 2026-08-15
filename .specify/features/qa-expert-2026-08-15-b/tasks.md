# Tasks — QA Expert 2026-08-15 (session B) — cohérence prix/essai, OpenAPI, CI main

> Format strict : `- [ ] [TaskID] [P?] [Story?] Description avec chemin de fichier`.
> Chaque user story forme un incrément testable indépendamment.
> Statut : les tâches marquées ✅ sont livrées par les PRs référencées.

## Phase 1 — Setup

- [x] T001 Créer la branche de session, les artefacts Spec Kit (spec.md, tasks.md) et ouvrir les PRs de correctifs.
- [x] T002 Triage des 14 branches distantes : 11 supprimées (contenu déjà sur main ou périmé), PR #2306 fermée, branches utiles fédérées (PRs #2982/#3115/#3121/#3133).

## Phase 2 — US1 : cohérence durée d'essai + metas pricing (P0)

- [x] T003 [US1] Aligner `front/web/src/modules/vitrine/lib/seo-metadata.ts` (landing + pricing) sur 14 jours + vrais plans (Free/Pilot 29€/Operations 99€/Enterprise devis) — PR #2982.
- [x] T004 [US1] Aligner `seo.ts` landing, `content.ts` (4 sous-titres modules), pages about/case-studies/faq/pricing/testimonials (FAQ essai FR/EN/TR/AR) — PR #2982.
- [x] T005 [US1] `manifest.json` (shortcut PWA) 14 jours + clé `signup.badge` (catalogues partagés + copies web, 4 locales) — PR #2982.
- [x] T006 [US1] Vérifier : `grep "30 jours\|30-day" front/web/src` → 0 (hors rétention post-essai), garde i18n diff exécutée, diff catalogues partagé/web.

## Phase 3 — US2 : OpenAPI + notifications (P0)

- [x] T007 [US2] Documenter 20 routes dans `api/openapi.yaml` (auth forgot/reset, departments hierarchy, admin impersonations/training/webhooks) — PR #3121.
- [x] T008 [US2] Passer `read-all` et `{id}/read` en PUT dans `api/routes/modules/rh.php` + supprimer le doublon `hierarchy` — PR #3121.
- [x] T009 [US2] Réaligner `expense-claims submit/reject` (POST→PUT dans openapi) — PR #3121.
- [x] T010 [US2] `check-openapi-route-coverage.py` → 0 drift nouveau / 0 drift inverse.

## Phase 4 — US3 : gardes CI main vertes (P0)

- [x] T011 [US3] Renumérotation migrations : `public/2026_08_15_000001_password_reset_tokens` → 000004, `tenant/2026_08_15_000001_add_company_id_to_calendar_tables` → 000005 — PR #3133.
- [x] T012 [US3] Merge des clés admin-only dans `shared/i18n/locales/{ar,en,fr,tr}.json` + sync complet (web/admin/mobile/backend/versions) committé — PR #3133.
- [x] T013 [US3] Exclusions LFS `.gitattributes` pour les 5 PNG vitrine (apple-touch-icon, icon-192/512, logo, og/default) — PR #3133.
- [x] T014 [US3] Clients notifications : `/notifications/mark-all-read` → `PUT /notifications/read-all` (mobile manager/hr + admin `realtime.js`) ; `PATCH→PUT` sur `/read` (mobile manager/hr + contract tests) — PR #3133.

## Phase 5 — US4 : arbitrage commercial (P1 — PRODUIT)

- [ ] T015 [US4] Ouvrir l'issue d'arbitrage : divergences `PlanSeeder` (Starter/Business/Enterprise 29/79/199€, 20/200/∞) vs vitrine (Free/Pilot/Operations/Enterprise 0/29/99€/devis, 5/30/250) — noms, prix Operations 99 vs Business 79, limites.
- [ ] T016 [US4] Ouvrir l'issue d'arbitrage : `trial_days` — `VerifyTrialSignup` fallback 30 vs `ProvisionGuidedTrial` fallback 14 vs `PlanSeeder` 14/14/30 ; recommander 14 partout.
- [ ] T017 [US4] Après arbitrage : appliquer la décision (seeders + migration de données si besoin + tests).

## Phase 6 — US5 : hygiène docs/routes (P3)

- [ ] T018 [US5] Consolider `CHANGELOG.md` `## [Unreleased]` : un header par catégorie canonique (Added/Changed/Fixed/Removed), fusion des sections intercalées (Fixed ×3, Added ×2, Changed ×2, Chore ×2).
- [ ] T019 [US5] Routes legacy notifications (`POST /notifications/mark-all-read`, `PATCH /notifications/{id}/read` dans dashboard.php) : retirer ou documenter explicitement (les tests les couvrent — adapter si retrait).

## Phase 7 — Issues ouvertes supplémentaires (mission « max d'issues »)

- [x] T020 Fermer 9 issues vérifiées corrigées sur main avec preuve code + commentaire (garde #2512) : #2608, #2695, #2696, #2720, #2783, #2784, #2792, #2612, #2700.
- [ ] T021 Suivre la fermeture automatique par PR : #2604/#2606 (PR #3115), #2909/#2721 (PR #2982), #2662 (PR #3121), #1962 (PR #3133).
- [ ] T022 Vérifier `#2726` (témoignages Amina Diallo/TechAfrika toujours présents) — non corrigé, à traiter séparément.
