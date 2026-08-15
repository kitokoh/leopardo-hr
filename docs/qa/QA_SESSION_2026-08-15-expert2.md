# QA Leopardo RH — Session expert #2 du 2026-08-15

Mission (propriétaire) : tester la plateforme dans tous les sens — vitrine, web app, admin,
mobiles, workflows, API, logiques, onboarding, cohérence — consigner chaque manquement selon la
méthode Spec Kit (issue + spec/plan/tasks), implémenter les manquements, puis traiter le backlog
d'issues ouvertes et merger le maximum de branches.

## Méthode
1. Revue statique experte par surface (4 agents parallèles) + cross-check automatisé
   (endpoints SPA vs routes Laravel, appels Dart vs routes, routes vs OpenAPI, clés i18n vs catalogues).
2. Builds/lints réels : `npm run lint` + `tsc` + `npm run build` (web vitrine ✅, admin ❌ — voir #3033).
3. Anti-doublon #2400 : chaque finding vérifié contre les issues ouvertes et les branches avant création.

## Findings NOUVEAUX (issues créées, label `qa-expert2-2026-08-15`)

| Surface | P1 | P2 | P3 | Issues |
|---|---|---|---|---|
| Vitrine/Web | 0 | 7 | 5 | #3021–#3032 |
| Admin | 2 | 3 | 7 | #3033,#3034,#3036–#3039,#3041–#3046 |
| Mobile | 0 | 2 | 6 | #3047–#3054 |
| API | 0 | 4 | 7 | #3055–#3065 |
| **Total** | **2** | **16** | **25** | **43** |

## P1 (bloquants)
- **#3033** Admin — build prod cassé : `DocumentReportIcon` inexistant dans @heroicons/vue → `vite build` échoue → deploy Cloudflare bloqué.
- **#3034** Admin — CompanyDetailView crashe au rendu (`health.adoption.kiosk.active` jamais renvoyé) → fiche entreprise blanche.

## P2 notables
- **#3021** og:image 404 sur ~20 pages (fix #2752 livré dans un fichier mort).
- **#3022** clés i18n brutes affichées dans le flux OTP signup.
- **#3026/#3027** stats fabriquées (OG image, carte Leo IA dashboard) — classe #2720/#2726.
- **#3047** mobile : notifications « marquer lu/lues » en PUT → 405 garanti (backend POST/PATCH).
- **#3055** API : `GET /employees/<built-in function id>/leave-balances` sans garde de rôle (tout employé lit les soldes d'un collègue).
- **#3056/#3057/#3058** trial : 14j vs 30j, OTP avalé, webhook email-bounce mort (secret jamais défini).

## Implémentation
Chaque issue → branche `fix/<issue>-<slug>` + PR `Closes #N` (Constitution §VII), CHANGELOG sous
`## [Unreleased]`. Spec Kit : `.specify/features/qa-expert2-{web,admin,mobile,api}-2026-08-15/`.

## Backlog & merges
- Issues ouvertes restantes hors vagues QA : triées par priorité (P1/P2/Agent-Ready d'abord).
- Branches à merger : 5 PRs ouvertes (toutes `dirty`) + branches sans PR — rebase sur main, checks verts, merge.
