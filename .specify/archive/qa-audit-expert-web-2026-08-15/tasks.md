# Tasks: Audit expert Web & Vitrine — 2026-08-15

**Input**: spec.md + plan.md

**Prerequisites**: plan.md (required), spec.md (required)

> Conversion en issues GitHub : label `qa-audit-2026-08-15`, méthode Spec Kit taskstoissues.
> **Sessions précédentes (canoniques, à ne pas dupliquer)** : série web #2602-#2613 — T001 edge-nodes page, T002 quick-action links, T003 script accents, T004 i18n pages (about/careers/contact/faq), T005 lint/build, T006 SITE_URL (#2607), T007 robots (#2643, PR #2664), T008 blog mort (#2609). Checkout sandbox = #2628 (PR #2665). Mes T058/T059/T072 (doublons) fermés ; T065/T070 rescopés.

## Phase 1 — P1 (US C1)

- [x] T058 [P1] C1 Checkout sandbox — **doublon fermé** : canonique #2628 (PR #2665 en cours). (issue #2717)

## Phase 2 — P2 (US C2, C3, C4)

- [x] T059 [P2] C2 SITE_URL — **doublon fermé** : canonique #2607 (T006 web). (issue #2718)
- [x] T060 [P2] C2 `lang`/`dir` par requête au SSR (fini `lang="fr"` codé en dur). (issue #2719)
- [x] T061 [P2] C2 Retirer le stat « Live: 18 » codé en dur du dashboard. (issue #2720)
- [x] T062 [P2] C2 Meta description pricing alignée sur les plans réels (Free/Pilot/Operations/Enterprise, essai 30 j). (issue #2721)
- [x] T063 [P2] C3 OG images : route générée `/opengraph-image` (fini les 404 `/og/*.png`). (issue #2722)
- [x] T064 [P2] C3 `sw.js` : précache = routes réelles (`/`, `/offline`, …) — installation PWA réparée. (issue #2723)
- [x] T065 [P2] C3 Manifest : essai 30 jours + icône existante (robots traités dans #2643/PR #2664). (issue #2724)
- [x] T066 [P2] C4 Checkout : bouton Google OAuth → proxy same-origin `/api/v1/auth/google` (pattern login, fix QA #2277). (issue #2725)
- [x] T067 [P2] C2 Témoignages : marquer démo ou retirer (plus de citations fabriquées). (issue #2726)

## Phase 3 — P3 (US C5, C6)

- [x] T068 [P3] C5 `SignupForm` : wizard localisé (labels, rôles, OTP, succès). (issue #2727)
- [x] T069 [P3] C5 Checkout/logout/login bannière : textes via catalogue i18n + `locale` réel dans le POST d'inscription. (issue #2728)
- [x] T070 [P3] C5 Typos arabes dans les chaînes livrées (« مساد المطور », « الردود الويب », « إلعاء », « الجمارافي ») → relecture (i18n pages traité dans #2605). (issue #2729)
- [x] T071 [P3] C6 Modale démo login : rendue uniquement si `/demo-users` répond (fini le fallback `password123`). (issue #2730)
- [x] T072 [P3] C6 Blog périmé — **doublon fermé** : canonique #2609 (T008 web). (issue #2731)
- [x] T073 [P3] C6 `vercel.json` : redirect mort supprimé + CSP en un seul endroit. (issue #2732)
- [x] T074 [P3] C6 Section apps mobiles : liens store réels ou retrait (fini « Bientôt disponible » + fallback signup). (issue #2733)

## Convergence

- [ ] T075 Mettre à jour `.specify/memory/project-state.md`, `CHANGELOG.md`, `AGENTS.md`, cocher les tâches après merge.
