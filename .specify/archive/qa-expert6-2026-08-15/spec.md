# Feature Specification: Session QA Expert 6 — Audit ciblé, Merge Campaign & Cohérence (2026-08-15)

**Feature Branch**: `docs/qa-expert6-2026-08-15`

**Created**: 2026-08-15

**Status**: Draft

**Input**: Mission propriétaire (2026-08-15) — tester la plateforme dans tous les sens,
consigner chaque manquement selon la méthode Spec Kit (issue + spec/plan/tasks), implémenter
le max de manquements et d'issues ouvertes, merger le max de branches, main vert.
Contexte : swarm multi-agents (agent, expert #2, expert v3, expert #4, expert #5 #3300) —
protocole anti-doublon #2400 appliqué sur chaque constat (branches + PRs + issues vérifiés
avant création).

## User Stories & Testing

### US1 — L'admin lint est vert (Priority: P2)
`cd front/admin-dashboard && npm run lint` (eslint src --max-warnings 0) échouait avec 4
warnings no-unused-vars (EditUserModal ×3 `catch (error)`, UserDetailView `retry()` morte).

**Acceptance**:
1. **Given** `npm run lint` sur l'admin, **Then** 0 problème (exit 0).
2. **Given** `npm run build` admin, **Then** build OK.

### US2 — L'outil de détection des issues laissées ouvertes fonctionne (Priority: P3)
`check-issues-left-open-by-merged-prs.sh` (#2512) plantait (set non sérialisable + guillemets
simples dans `python3 -c`).

**Acceptance**:
1. **Given** le script exécuté avec `gh` authentifié, **Then** rapport `OPEN #N <- PR(s)` sans traceback.

### US3 — Les gardes de cohérence repo sont verts sur main (Priority: P2)
`check-migration-basename-collisions.sh` (#1962) signalait des préfixes dupliqués
2026_08_14_000019/000020 en tenant. **Constata** : déjà corrigé sur main par le commit
1e576375 (renommages 000023/000024) — constat fermé sans doublon (#3224 closed, preuve code).

### US4 — Les déploiements staging/prod sont à jour (Priority: P1 — ops, non fixable en code)
API Render/Vercel servent v4.23.5 alors que main est 4.24+ : `/api-explorer` 500,
`/api/v1/i18n/catalog/fr` 500, `/api/v1/supported-countries` 404, `/api/v1/demo-users` 404,
`/api/v1/employees` → 302 HTML (au lieu de 401 JSON). Constats existants #2627/#2632/#2646
(relance P1, pas de doublon). Nécessite une action ops (file Render/déploiement), hors
périmètre code.

## Constats

| ID | Surface | Sévérité | Constat | Issue | PR |
|---|---|---|---|---|---|
| F6-01 | Admin | P2 | ESLint 4 warnings → `npm run lint` rouge | #3220 | #3228 |
| F6-02 | API | P2 | Collisions migrations tenant (déjà corrigé sur main) | #3224 (clos, preuve) | — |
| F6-03 | Tooling | P3 | Script #2512 cassé (2 bugs) | #3225 | #3301 |
| F6-04 | Ops | P1 | Déploiements stale v4.23.5 (preuves live, déjà #2627/#2632) | réaffirmé | — |
| F6-05 | Mobile | P2 | Drift garde manifeste #2212 (déjà #3205, PR #3209) | vérifié, pas de doublon | #3209 |
| F6-06 | Cohérence | P3 | Issues #2597/#2605/#3111/#3158/#3163 référencées par PRs mergées mais NON résolues (vérifié code) — ne pas fermer | rapport | — |
