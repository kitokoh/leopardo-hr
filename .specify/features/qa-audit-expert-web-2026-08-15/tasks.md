# Tasks: Audit Expert Web — Cohérence Vitrine & Admin Dashboard — 2026-08-15

**Input**: spec.md + plan.md

**Prerequisites**: plan.md (required), spec.md (required)

## Phase 1 — US1 Dashboard web (P1) — issues #2602-#2603

- [x] T001 [US1] Retirer la page edge-nodes du dashboard client : nav (`(dashboard)/layout.tsx` ou composant nav), route `(dashboard)/edge-nodes/`, entrée `sitemap.ts` — l'edge est une surface super-admin (`/platform/edge/nodes`, `api/routes/api.php:289-313`), pas de modèle company (`front/web/src/app/(dashboard)/edge-nodes/page.tsx` supprimée)
- [x] T002 [P] [US1] Corriger les liens d'action rapide `/dashboard/employees|absences|reports` → `/employees`, `/absences`, `/reports` (`(dashboard)/dashboard/page.tsx:611-614`)

## Phase 2 — US2 Qualité texte (P2) — issues #2604-#2606

- [x] T003 [US2] Script Python de correction d'accents (table mot→mot connue, FR uniquement) dans `dev-hub/tools/` + application sur `front/web/src` (data/blog.ts, data/faq.ts, data/testimonials.ts, legal-content.ts, seo.ts, lib/i18n.ts clés FR, pages dashboard settings/dashboard, SignupForm, pages landing) — jamais sur slugs, paths, clés EN/TR/AR
- [x] T004 [US2] i18n des pages /about, /careers, /contact, /faq : contenu déplacé en données par locale (`data/about.ts`, `data/careers.ts`, `data/contact.ts` + `data/faq.ts` existant) et rendu via `useVitrineLocale` ; libellés navbar FR accentués (`Navbar.tsx:79-104`)
- [x] T005 [P] [US2] Vérification : `npm run lint` + `npm run build` verts, `npm run check:mojibake` vert, diff manuel (0 faux positif)

## Phase 3 — US3 SEO & domaines (P2) — issues #2607-#2609

- [x] T006 [P] [US3] Centraliser `SITE_URL` (`lib/site.ts`, défaut `https://leopardo-rh.com`) + remplacer `gestionemployer-backend.vercel.app` (`sitemap.ts:11`, `robots.ts:4`) et canonicals durs + aligner proxy backend/CORS (`next.config.ts:45`, `api-client.ts:17`, `api/v1/[...path]/route.ts:105-106`) sur un défaut cohérent surchargeable
- [x] T007 [P] [US3] Supprimer `src/app/api/robots/route.ts` (legacy) ; ajouter `/blog`, `/signup`, `/checkout`, `/offline`, `/share` au sitemap ; `sameAs` JSON-LD → x.com/leopardo_hr + github org (`seo.ts:372-374`, `structured-data.ts`)
- [x] T008 [P] [US3] Supprimer le contenu mort (`src/content/blog/*.md` ×10, `content/blog/*.mdx` ×3 — vérifier 0 import avant) ; dates blog 2024 → 2026 (`data/blog.ts` tendances-rh-2024, automatiser-paie-2024) ; lier /about, /branding, /videos, /mobile dans footer/nav (`Footer.tsx:15-49`)

## Phase 4 — US4 Admin dashboard (P2) — issues #2610-#2613

- [x] T009 [P] [US4] `EditUserModal.vue` : retirer les boutons simulés `resetPassword`/`sendWelcomeEmail`/`forceLogout` (`:330-357`) et « Changer l'avatar » sans @click (`:35`) — aucun endpoint admin correspondant ; nettoyer les handlers morts
- [x] T010 [P] [US4] Header search : filtrage client de la navigation (remplace le stub `console.log`, `Header.vue:237-241`) + retrait des console.log (`stores/realtime.js:72,80`)
- [x] T011 [P] [US4] Supprimer les composants orphelins (`RevenueForecastWidget.vue`, `CreateTaskModal.vue`, `BackupManagement.vue`, `ApiTestingTools.vue`, `SecurityMonitoring.vue`, `SystemConfiguration.vue`, `ResourceUsageWidget.vue`, `RealTimeMetricsChart.vue`, `ImportConfigModal.vue` — 0 référence vérifiée par rg avant suppression)
- [x] T012 [P] [US4] Ajouter les clés i18n `users.errors.password_min` + `users.toast.bulkDone` aux 4 locales ; vérifier `UsersView.vue:435` ↔ route backend impersonation (dépendance T011 feature backend)

## Dependencies & Execution Order

- Phase 1 frontend indépendante ; Phase 2 indépendante ; Phase 3 indépendante ; Phase 4 T012 dépend du fix backend impersonation (feature backend T011).
- PR : `fix/qa-<n>-web-coherence` + `fix/qa-<n>-admin-actions` — chacune avec `Closes #<issue>`.
- Ne pas toucher aux 14 pages marketing restées en FR dur (documentées, tâches futures) ni aux 12 vues `requiresTenant` du router admin (choix d'architecture documenté).
