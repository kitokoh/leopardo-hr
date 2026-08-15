# Tasks: Vague QA Web & OpenAPI 2026-08-14

**Input**: spec.md (US1-US5)

**Prerequisites**: spec.md (required)

> Cette feature ne touche pas `api/app/Modules/Payroll/` → pas de gate conformité paie (vérifié : US1-US4 = front/web, US5 = OpenAPI/EdgeSync).

## Phase 1 — Vitrine : liens et contenus (US1-US2)

- [x] T001 [P2] US1 `/docs` — ancres mortes corrigées par la **PR parallèle #2281** (vague QA hardening 2, spec `qa-hardening-wave-2-2026-08-14`) : sections réelles ajoutées (`#security`, `#rbac`...), ancres TOC réalignées. Retiré de cette branche pour éviter le conflit (règle anti-doublon).
- [x] T002 [P2] US2 `/videos` — lecteur réel (vidéo locale `public/videos/product-demo.mp4` + sous-titres) implémenté par la **PR parallèle #2281**. Retiré de cette branche (anti-doublon).

## Phase 2 — Vitrine : sitemap, PWA, accessibilité (US3-US4)

- [ ] T003 [P2] US3 `sitemap.ts` — générer les URLs `/blog/*` depuis `getBlogPosts()` de `src/modules/vitrine/data/blog.ts` (toutes locales) au lieu de `getAllPosts()` (mdx obsolètes). Vérif : aucun slug 404, les 10 articles réels présents, mdx retirés de la source sitemap (fichiers mdx conservés ou nettoyés selon usage).
- [ ] T004 [P2] US4 `share_target` PWA — soit créer `src/app/share/route.ts` (POST handler acceptant `title/text/url` multipart, redirection vers `/signup` ou page dédiée), soit retirer `share_target` du manifeste. Décision : route légère (le manifeste annonce la feature) ou retrait documenté dans CHANGELOG.
- [ ] T005 [P2] US4 skip-link — ajouter `id="main-content"` au `<main>` de toutes les pages (layouts) ou retirer le skip-link s'il ne peut pas être généralisé. Préférer : ajouter l'id dans le layout racine si structure le permet.

## Phase 3 — OpenAPI ↔ routes (US5)

- [ ] T006 [P2] US5 Mismatch verbes — aligner `api/openapi.yaml` (+ miroir `dev-hub/openapi/v1.yaml`) sur les routes réelles : `smart-attendance/config` (PUT→GET), `preferences` (GET→PUT), `cabinet/documents/{id}/move` (POST→PATCH), `expense-claims/{id}/approve` (POST→PUT), `loans/{id}/approve` + `loans/{id}/disburse` (POST→PUT). Sinon aligner les routes (décision au cas par cas, privilégier la spec=source de vérité documentée).
- [ ] T007 [P2] US5 Méthodes mortes — supprimer `EdgeController::{installScript, downloadDockerCompose, licensePublicKey}` (doublons d'`EdgeDownloadController`) OU les brancher sur les routes `/edge/install.sh`, `/edge/download/docker-compose.yml`, `/edge/license-public-key`. Décision : suppression si `EdgeDownloadController` couvre déjà (vérifier d'abord les tests).
- [ ] T008 [P3] US5 Vérifier les 16 opérations documentées sans route (`/bank-exports`, `/exports/*` pluriel, `/i18n/{locale}`, `/partner/*`, `/smart-attendance/sessions/{id}/validate`) — soit ajouter les routes, soit corriger la spec. Si volume trop important, documenter le drift dans une issue séparée (pas bloquant).

## Phase 4 — Convergence

- [ ] T009 Mettre à jour `CHANGELOG.md` (entrée `## [Unreleased]`), `.specify/memory/project-state.md` si pertinent, cocher les tâches après merge.
