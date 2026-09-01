# Tasks: Session QA Expert 8 — 2026-08-15

**Input**: spec.md (findings F1-F16, issues #3485-#3500)

## Phase 1 — Web : cohérence commerciale (US1)

- [ ] T001 [P2] [F1/#3485] SSO SAML/OIDC — retirer des features vendues (data/pricing.ts ×4 locales, checkout/page.tsx:104, pricing/page.tsx:144) ou marquer « bientôt disponible »
- [ ] T002 [P3] [F2/#3486] sitemap.ts — retirer /signup et /checkout (noindex)
- [ ] T003 [P2] [F3/#3487] seo.ts:161 — passer la locale réelle à t() (metadata par locale)
- [ ] T004 [P2] [F4/#3488] MiniCaseStudies — retirer les chiffres non sourcés (4 locales) ou marquer « démo »
- [ ] T005 [P3] [F5/#3489] Pages /branding /careers /mobile /testimonials — router via useVitrineLocale (pattern #3248)
- [ ] T006 Vérification : `npm run build` + `npm run lint` vitrine verts

## Phase 2 — Admin : états honnêtes (US2)

- [ ] T007 [P3] [F6/#3490] EditUserModal.vue supprimé (0 import) + emit 'edit' retiré de UserTable + clés avatar i18n ajoutées ou retirées
- [ ] T008 [P3] [F7/#3491] TrainingView/PredictionsView/ReportsView/TaxRatesView/CompaniesView — états d'erreur explicites (pattern #2992)
- [ ] T009 [P3] [F8/#3492] LeavesView approve/reject + PayrollView calculate/validate/summary/PDF — toasts succès/erreur
- [ ] T010 [P3] [F9/#3493] GrowthDashboardView — confirm() → dialog i18n + garde null NaN
- [ ] T011 [P3] [F10/#3494] WebhooksView confirm() → dialog + ReportsView charge overtime/payroll KPIs
- [ ] T012 [P3] [F11/#3495] UsersView summary — interpoler :active/:newToday + modale impersonation glass-*
- [ ] T013 Vérification : `npm run build` + `npm run lint` admin verts

## Phase 3 — API : bornes et honnêteté (US3)

- [ ] T014 [P3] [F12/#3496] PlanSeeder:72 — trial_days Enterprise 30 → 14
- [ ] T015 [P2] [F13/#3497] routes/modules/sso.php — throttle sur callbacks SAML/OIDC publics + test 429
- [ ] T016 Vérification : phpstan strict + pint + tests ciblés verts

## Phase 4 — Mobile : propreté (US4)

- [ ] T017 [P3] [F14/#3498] employee app.dart — retirer /contracts /training /expenses /ai-voice (routes mortes)
- [ ] T018 [P3] [F15/#3499] hr + manager main.dart — tracesSampleRate 1.0 → 0.2
- [ ] T019 [P3] [F16/#3500] marketing main.dart — déplacer initializeDateFormatting dans StartupGate ; smart_attendance extractDataList ; supprimer code mort core
- [ ] T020 Vérification : flutter analyze (si dispo) ou revue statique

## Finalisation

- [ ] T021 CHANGELOG.md — entrées ### Fixed par PR
- [ ] T022 PRs avec `Closes #N` dans le body + anti-doublon vérifié avant push
- [ ] T023 Merge campaign — merger le max de PRs en vol quand la file CI se libère
- [ ] T024 Rapport de session docs/qa/QA_SESSION_2026-08-15-expert8.md
