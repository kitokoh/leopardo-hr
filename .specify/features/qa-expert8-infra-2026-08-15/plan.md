# Implementation Plan: Session QA Expert 8 2026-08-15 — audit infrastructure & périphérie

**Branch**: `docs/qa-expert8-infra-2026-08-15` | **Date**: 2026-08-15 | **Spec**: `.specify/features/qa-expert8-infra-2026-08-15/spec.md`

**Input**: 17 constats nouveaux (#3518-#3523, #3528-#3538) issus d'un audit ciblé sur les zones non couvertes par les sessions experts 1-7 (CI, edge/, dev-hub/, postman/, render.yaml, proxy web, middleware).

## Summary

Les vagues QA du 2026-08-15 ont massivement couvert vitrine/admin/api/mobile (200+ issues). Cette session complète par un audit « infrastructure & périphérie » : 39 workflows CI, scripts d'outillage, nœud edge on-prem, blueprint Render, proxy Next.js. Deux P1 (perte de données potentielle via `backup_drill.sh`, trou de déclenchement CI sur les chemins argent), des fuites de credentials historiques (repo public), et de la dette de gouvernance CI. Runtime smoke : API Render UP (4.23.5 — voir #3528), vitrine Vercel UP, leopardo-rh.com NXDOMAIN (confirme #3452, action DNS propriétaire requise).

## Technical Context

**Language/Version**: Bash (scripts outillage), YAML (CI/Render), TypeScript/Next.js 15 (proxy + middleware), PHP 8.4/Laravel 12 (commande edge daemon), JSON (Postman)

**Primary Dependencies**: `dev-hub/scripts/backup_drill.sh`, `.github/workflows/`, `postman/leopardo_hr.postman_collection.json`, `front/web/src/middleware.ts`, `front/web/src/app/api/v1/[...path]/route.ts`, `api/.env.example`, `edge/install.sh`, `edge/docker-entrypoint.edge.sh`, `render.yaml`, `CODEOWNERS`, `api/app/Console/Commands/EdgeSyncDaemonCommand.php`

**Storage**: n/a (artefacts repo + configuration)

**Testing**:
- Scripts bash : `bash -n` + shellcheck + tests d'échec explicites (refus sans confirmation)
- Workflows : actionlint (déjà requis) + preuve de déclenchement via `gh run list`
- Web : `npm run lint && npm run build` (front/web), test unitaire proxy (fetch rejeté → 502)
- API : PHPStan strict + pint (toute modif PHP)

**Target Platform**: CI GitHub Actions, Render, edge on-prem (Docker), vitrine Vercel

**Constraints**:
- Constitution §I : une PR par issue, anti-doublon vérifié (branches + PRs + issues).
- Constitution §V : zéro secret — les corrections postman/smoke ne doivent pas introduire de nouveaux placeholders commitables ressemblant à de vrais secrets.
- Constitution §VII : CHANGELOG.md sous `## [Unreleased]` dans chaque PR ; `Closes #N` dans le body.
- Pas de changement de comportement runtime hors scope des issues listées.

## Constitution Check

*GATE* :
- §I Spec-First : ✓ spec.md + plan.md + tasks.md rédigés avant toute modification.
- §IV Qualité : gates existantes suffisantes (actionlint, PHPStan, ESLint) ; tests d'échec explicites ajoutés pour les P1.
- §V Sécurité : cœur de la spec (credentials, supply-chain edge).
- §VII Gouvernance : 1 PR = 1 issue, `Closes #N`, CHANGELOG, branches `fix/<issue>-slug`.
- Anti-doublon : 205 issues ouvertes + 130 branches greppées par mot-clé (openapi, backup, postman, middleware, proxy, mdx, APP_VERSION, install.sh, entrypoint, render, CodeQL, CODEOWNERS, composer-lock, pre-commit, jobs-ci) — recouvrements écartés : #3233/#3061/#2675 (OpenAPI drift), #3334 (landing FR), #3366 (RateLimiter trial-status), #3413 (références PLAN_ACTION2), #3452 (DNS vitrine).

## Project Structure

### Documentation
```text
.specify/features/qa-expert8-infra-2026-08-15/
├── spec.md              # user stories + acceptance (issues #3518-#3523, #3528-#3538)
├── plan.md              # ce fichier
├── tasks.md             # tâches actionnables par story
└── findings-registry.md # registre complet de la session (y compris constats déjà couverts)
```

### Source changes (par PR, une par issue)
```text
dev-hub/scripts/backup_drill.sh                          # #3518 guard cible + confirmation
.github/workflows/backend-jobs-ci.yml                    # #3519 paths modulaires (+ garde paths)
postman/leopardo_hr.postman_collection.json              # #3520 credentials → variables
dev-hub/tools/staging-demo-auth-smoke.sh                 # #3521 pas de fallback password123
front/web/src/middleware.ts                              # #3522 gate documenté/durci + test
front/web/src/app/api/v1/[...path]/route.ts              # #3523 try/catch → 502 JSON
api/.env.example                                         # #3528 APP_VERSION 4.24.0 (+ garde optionnelle)
edge/install.sh                                          # #3529 checksum sha256 épinglé
edge/docker-entrypoint.edge.sh                           # #3530 échecs migrate/cache non masqués
render.yaml                                              # #3531 nom service aligné CORS/README
.github/workflows/{codeql,secret-scan,openapi-ci,...}.yml # #3532 cancel-in-progress conditionnel
CODEOWNERS (+ dev-hub/tools/branch-protection-canonical.json) # #3533 cohérence gouvernance
.github/workflows/fix-composer-lock.yml                  # #3534 PR au lieu de push direct
front/web/package.json                                   # #3535 retrait toolchain MDX morte
front/web/src/app/(landing)/integrations/layout.tsx      # #3536 metadata dédiées (+ guides copy)
api/app/Console/Commands/EdgeSyncDaemonCommand.php       # #3537 env() → config/edge.php
.github/workflows/tests.yml                              # #3538 stub retiré ou canonisé
```

## Implementation Steps

1. **P1 d'abord** : #3518 (guard backup_drill) et #3519 (paths CI) — risque données + trou de couverture argent.
2. **Sécurité P2** : #3520, #3521 (credentials), puis #3522/#3523 (robustesse web), puis #3528-#3531 (versioning/edge/Render).
3. **Gouvernance & dette** : #3532-#3534, #3538 (CI), #3535-#3537 (nettoyages).
4. Chaque PR : branche `fix/<issue>-<slug>`, CHANGELOG `[Unreleased]`, `Closes #N`, attendre les 5 checks requis, merge squash, supprimer la branche.

## Risks

- **Parallélisme agents** : marker branch immédiat par issue avant de coder (protocole #2400).
- **CI saturée** : les 5 checks requis peuvent mettre >30 min ; ne pas merger en aveugle, surveiller `gh pr checks`.
- **#3531 (render.yaml)** : renommer le service peut recréer une ressource Render — la PR doit documenter la marche à suivre (blueprint vs service existant) et rester docs/config, sans action infra directe.
