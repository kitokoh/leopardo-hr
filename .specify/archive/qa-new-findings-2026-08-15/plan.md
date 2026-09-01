# Feature Specification: QA complet plateforme 2026-08-15 — nouveaux constats (issues #2746–#2757)

**Feature Branch**: `fix/<issue>-<slug>` (une PR par issue, Closes #N)

**Created**: 2026-08-15

**Status**: Implementé (PRs ouvertes/mergées)

**Input**: Mission QA complète de la session 2026-08-15 (vitrine, web client, admin, mobile ×5, kiosk, edge, API, onboarding, cohérence). Les constats déjà couverts par la vague parallèle (#2600–#2665) sont exclus (anti-doublon §I constitution). Ce feature regroupe les **nouveaux** constats : 12 issues.

## User Stories

| US | Issue | Surface | Priorité |
|----|-------|---------|----------|
| US1 | #2746 | Web (e2e) | P1 — middleware dashboard casse 13 specs mockées (CI rouge) ; SW contourne les mocks |
| US2 | #2747 | Admin | P1 — enveloppe `{data:[...]}` non déballée → TypeError header alertes |
| US3 | #2748 | Mobile manager | P1 — navigation dossier Cabinet cassée + errorBuilder routeurs |
| US4 | #2749 | API payroll | P2 — lectures paie mobiles 403 pour rôle RH |
| US5 | #2750 | Kiosk | P1 — globals `__KIOSK_*` jamais injectées → fonctions cloud 404 |
| US6 | #2751 | Edge | P1 — docker-compose inutilisable + install.sh cassé |
| US7 | #2752 | Web (SEO) | P2 — OG images `/og/*.png` inexistantes |
| US8 | #2753 | Web (content) | P2 — essai « 14 jours » vs « 30 jours » |
| US9 | #2754 | Mobile (CI) | P2 — mobile-workflow-contracts.json périmé (garde rouge) |
| US10 | #2755 | i18n | P2 — 8 983 chaînes hardcodées (chantier) |
| US11 | #2756 | Web (PWA) | P3 — icônes PNG absentes |
| US12 | #2757 | Docs/kiosk | P3 — cohérence RBAC/CSRF/CI, clés tr/ar, CSS kiosk |

## Acceptance (global)
- Chaque issue fermée par sa PR (`Closes #N` dans le body), checks CI verts.
- Gardes : `npm run lint`/`build` (web+admin), tests e2e vitrine 21/21 (chromium), simulation contrat mobile 0 échec, `php -l` (routes/tests), `bash -n` + parse YAML (edge).
