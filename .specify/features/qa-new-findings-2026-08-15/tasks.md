# Tasks: QA complet plateforme 2026-08-15 — nouveaux constats

**Input**: spec.md (`.specify/features/qa-new-findings-2026-08-15/`)

**Tests**: gardes par surface (voir plan.md « Validation ») + CI PR

## Phase 1 — Correctifs P1 (code)

- [x] T001 US1 #2746 — e2e vitrine : cookie `leopardo_token` dans les specs mockées + `serviceWorkers: 'block'` + mock demo-users (PR #2878)
- [x] T002 US2 #2747 — admin `stores/dashboard.js` : déballage `data?.data ?? []` + gardes `Array.isArray` + `$subscribe` (PR #2790, **mergée**)
- [x] T003 US3 #2748 — route `/cabinet/folder/:folderId` manager + `errorBuilder` ×3 routeurs (PR #2815, **mergée**)
- [x] T004 US5 #2750 — bridge kiosk : injection globals + bloc edge mort retiré + test python (PR #2873)
- [x] T005 US6 #2751 — edge compose/install.sh : chemins, `/api/v1`, chmod, domaine (PR #2877)

## Phase 2 — Correctifs P2/P3

- [x] T006 US4 #2749 — RBAC payroll : lectures mobiles `principal,comptable,rh` + tests (PR #2834)
- [x] T007 US7 #2752 — OG images réelles + fallback (PR #2889)
- [x] T008 US8 #2753 — durée d'essai unifiée 30 jours (PR #2888)
- [x] T009 US9 #2754 — mobile-workflow-contracts.json aligné (PR #2890)
- [x] T010 US11 #2756 — icônes PNG PWA (PR #2892)
- [x] T011 US12 #2757 — docs/cohérence + clés tr/ar + CSS kiosk (PR #2898)

## Phase 3 — Backlog

- [ ] T012 US10 #2755 — chantier i18n 8 983 chaînes (lots par app, voir issue)

## Dependencies & Execution Order
- T001–T011 indépendants ; T012 suit la décision de lots de l'issue #2755.
