# Tasks: Session QA agent 2026-08-15

**Input**: spec.md + findings-registry.md (`.specify/features/qa-agent-session-2026-08-15/`)

**Anti-duplication (#2400)** : constats couverts par #2600–#2813 / missions 2026-08-14/15 exclus.

## Phase 1 — Correctifs web pricing/cohérence (US1/US2) — PR #2972

- [x] T001 [P1] US1 Checkout `PLAN_CONFIG` : clés canoniques `free/pilot/operations/enterprise`,
      alias doux `starter→pilot`, `business→operations`, `scale→enterprise`, fallback `operations`
      (régression #2944 — ré-applique #2908).
- [x] T002 [P3] US1 Retirer le BOM U+FEFF de `checkout/page.tsx`.
- [x] T003 [P1] US2 30 jours sur toutes les locales : `vitrine-locale.ts` (stats hero `value:14`→30,
      CTA fr/en/tr/ar), `pricing.ts` (en/tr/ar priceNote), `faq/page.tsx`, `checkout/success`,
      `about/case-studies/testimonials`, FAQ schema layouts, `OperationalProofSection` (fr/en/tr/ar),
      `seo.ts`/`seo-metadata.ts` (plans Pilot/Operations + 30j).
- [x] T004 [P2] US3 `billing/page.tsx` : PLAN_LABELS canoniques + alias.
- [x] T005 [P2] Validation : lint + mojibake + build + jest (15/16 — échecs SignupForm préexistants).
- [ ] T006 [P2] Merge de la PR #2972 (CI verte requise).

## Phase 2 — Issues nouveaux constats (US3/US4/US5)

- [ ] T010 [P1] US1 Issue régression : clés starter/business ré-mergées + fallback `business` (réf. #2908/#2780).
- [ ] T011 [P2] US5 Issue i18n : `seo.pricing.description` + clés `t()` de seo.ts absentes des catalogues (S3).
- [ ] T012 [P2] US3 Issue cohérence plans : `FeaturePlanMatrixSeeder` trial/starter/business vs
      free/pilot/operations frontend (S4) — décision clés canoniques + mapping.
- [ ] T013 [P3] US3 Issue branding : `branding/page.tsx` « Starter/Pro/Enterprise » (S5).
- [ ] T014 [P2] US4 Issue tests : `SignupForm.test.tsx` 5 échecs sur main + proposition d'ajouter jest
      à la CI vitrine ou documenter (S6).

## Phase 3 — Branches/issues ouvertes restantes (implémentation)

- [ ] T020 [P2] `fix/2604-2606-web-accents` : mise à jour + PR (Closes #2604, #2606).
- [ ] T021 [P2] `fix/2607-2608-web-seo-cleanup` : mise à jour + PR (Closes #2607, #2608 — robots.txt
      /blog /signup /checkout).
- [ ] T022 [P2] `fix/2789-admin-supported-countries` : mise à jour + PR (Closes #2788/#2789).
- [ ] T023 [P3] `qa-hardening-wave-2026-08-14` (#2306 draft) : réconcilier avec main ou fermer avec
      renvoi si contenu absorbé.
- [ ] T024 [P3] Nettoyage branches mergées : `fix/2631-mobile-onboarding-patch`, `fix/2650-agents-demo-users`,
      `fix/2680-api-trial-password-leak`, `fix/2719-web-ssr-lang-dir`, `fix/2882-2905-web-vitrine-links`
      (issues closes, contenu dans main) → supprimer ou documenter.

## Convergence

- [ ] T030 Mettre à jour CHANGELOG.md, `.specify/memory/project-state.md`, cocher les tâches après merge.
