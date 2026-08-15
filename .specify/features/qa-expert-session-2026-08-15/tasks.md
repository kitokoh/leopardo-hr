# Tasks: Session QA Expert 2026-08-15 — manquements résiduels

**Input**: spec.md + plan.md (`.specify/features/qa-expert-session-2026-08-15/`)

**Prerequisites**: plan.md (required), spec.md (required)

**Tests**: validation manuelle + lint/build pour le frontend.

## Format: `[ID] [P?] [Story] Description`

- **[P]** = parallélisable
- **[Story]** = US1…US4

---

## Phase 1: US1 — Collection Postman (P1)

- [ ] T001 [US1] Analyser la collection existante `postman/leopardo_hr.postman_collection.json` (2 requêtes) et les générateurs disponibles (`dev-hub/tools/`).
- [ ] T002 [US1] Construire la collection étendue : variables (`baseUrl` = `https://gestionemployerbackend.onrender.com/api/v1`), auth Bearer, endpoints publics + auth (login/register/platform login/demo-users/i18n catalog/onboarding) + un CRUD représentatif par module tenant.
- [ ] T003 [US1] Valider chaque chemin contre `api/routes/` (aucun 404 de route) ; `jq '.item | length'` ≥ 50.

## Phase 2: US2 — api/CHANGELOG.md (P2)

- [ ] T004 [US2] Comparer `api/CHANGELOG.md` (max 4.21.0) avec `CHANGELOG.md` racine (4.24.0).
- [ ] T005 [US2] Ajouter les sections 4.22.0, 4.23.0, 4.24.0 (correctifs backend condensés depuis le CHANGELOG racine).
- [ ] T006 [US2] Ajouter une note « tenir api/CHANGELOG.md à jour dans chaque PR backend » en tête du fichier.

## Phase 3: US3 — .env.example (P3)

- [ ] T007 [US3] Localiser les 2 occurrences de `BIOMETRIC_RETENTION_MONTHS` (`api/.env.example`).
- [ ] T008 [US3] Supprimer le doublon (conserver l'occurrence avec le commentaire le plus riche) ; vérifier `grep -c '^BIOMETRIC_RETENTION_MONTHS='` = 1 et aucun autre doublon (`sort | uniq -d`).

## Phase 4: US4 — Lien X/Twitter mort (P2)

- [ ] T009 [US4] Vérifier `x.com/leopardo_hr` (404 constaté) ; inspecter `Footer.tsx:8` + `seo.ts` / `structured-data.ts` (`sameAs`).
- [ ] T010 [US4] Remplacer le lien mort par le lien GitHub `https://github.com/kitokoh/leopardo-hr` (ou retirer l'icône) ; s'assurer que `sameAs` n'inclut pas l'URL morte.
- [ ] T011 [US4] `npm run lint` + `npm run build` dans `front/web` (vert).

## Phase 5: Polish & Livraison

- [ ] T012 [P] CHANGELOG racine : entrée sous `## [Unreleased]` (docs) listant les 4 correctifs.
- [ ] T013 [P] Issues GitHub créées (4) : Postman, api/CHANGELOG, .env.example, lien X — labels QA/docs/web.
- [ ] T014 Commit + PR `fix/qa-expert-session-2026-08-15` avec `Closes #A #B #C #D` dans le body, CI verte, merge.

## Dependencies & Execution Order

- US1, US2, US3, US4 indépendants → parallélisables ([P]).
- Phase 5 dépend des phases 1-4.
- Validation finale : CI verte (PHPStan niveau 8, lint web, build web) + re-vérification manuelle des 4 artefacts.

## Notes

- Anti-doublon respecté : les 4 manquements ne figurent dans aucune des 184 issues ouvertes (vérifié le 2026-08-15).
- Coordination : #2608 (wave) prévoit d'ajouter `x.com/leopardo_hr` au `sameAs` — la T010 doit être faite avant pour éviter de propager l'URL morte ; mentionner #2608 dans la PR.
