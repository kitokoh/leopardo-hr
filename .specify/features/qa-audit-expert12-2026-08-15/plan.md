# Plan: Audit 360° expert 12 — 2026-08-15

**Input**: spec.md + tasks.md (`.specify/features/qa-audit-expert12-2026-08-15/`)

## Tech Stack & Structure

- **admin-dashboard** (Vue 3 + Vite) : suppression imports/helpers inutilisés — `front/admin-dashboard/src/`.
- **docs** : registre de session `docs/qa/QA_SESSION_2026-08-15-expert12.md` + artefacts Spec Kit.
- Aucune dépendance nouvelle ; aucun changement de comportement.

## Approche

1. **Docs d'abord** (Phase 1) : branche `docs/qa-audit-expert12-2026-08-15` → PR docs (la CI docs est légère).
2. **Lint admin** (Phase 2) : branche `fix/<issue>-admin-lint-warnings` → suppression des 9 warnings (5 fichiers Vue), PR avec `Closes #<issue>`.
3. **Validation** (Phase 3) : `npm run lint` admin → 0 warning ; `npm run build` admin → vert ; `tsc --noEmit` + `eslint --max-warnings 0` web → verts ; merge des PRs.

## Validation

```bash
cd front/admin-dashboard && npm run lint && npm run build   # 0 warning, build vert
cd front/web && npx tsc --noEmit && npx eslint src --ext .ts,.tsx --max-warnings 0  # verts
```

## Risks

- La famine CI (#3545) retarde les checks GitHub → validation locale systématique avant merge.
- Les fichiers Vue touchés sont aussi édités par d'autres agents (UsersView, SystemView…) → re-merge si conflit (union i18n si besoin).
