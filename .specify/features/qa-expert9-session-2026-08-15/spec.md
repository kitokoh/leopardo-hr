# Feature Specification: Session QA Expert 9 2026-08-15 (audit live + consolidation)

**Feature Branch**: `docs/qa-expert9-session-2026-08-15` + branches par issue (`fix/<issue>-*`)

**Created**: 2026-08-15 | **Status**: Draft → Implémentation en cours

**Input**: Mission propriétaire (session 2026-08-15) — audit 360° (vitrine, web, admin,
mobiles, workflows, APIs, logiques métier, onboarding, cohérence UI/UX), puis consolidation
(issues ouvertes + branches), puis implémentation des constats. `main` doit rester VERT
(plusieurs agents en parallèle).

## User Stories & Testing

### User Story 1 — Les constats live sont vérifiés et tracés (P1)

Chaque constat d'audit est vérifié contre `origin/main` ET/OU la production live
(`gestionemployerbackend.onrender.com`, `leo-admin.pages.dev`, `leopardo-rh.com`) avec
preuve reproductible avant création d'issue.

**Acceptance Scenarios**:
1. **Given** un endpoint public, **When** requête HTTP réelle, **Then** le statut/code est
   consigné (3 échantillons pour les endpoints flaky).
2. **Given** un correctif suspecté présent, **When** `git show origin/main:<fichier>`,
   **Then** la preuve code est citée (fichier:ligne) dans l'issue ou la fermeture.

### User Story 2 — La file de PRs est drainée sans casser main (P1)

Les PRs ouvertes sont triées (doublons / obsolètes / mergeables), les branches conflictuelles
sont rebasées (CHANGELOG en union), et les PRs propres sont mergées avec `Closes #N` dans
le body.

**Acceptance Scenarios**:
1. **Given** deux PRs sur la même issue, **When** tri, **Then** la PR canonique (superset
   vérifié) est conservée et l'autre fermée avec renvoi (protocole #2400).
2. **Given** une PR dont le correctif est déjà sur main, **When** vérification code,
   **Then** elle est fermée « obsolète » avec la preuve, et l'issue liée fermée si résolue.

### User Story 3 — Nouveaux constats inédits (P2)

Les constats non couverts par les vagues expert 1-7 deviennent des issues `[QA][P#][surface]`
avec référence à cette spec.

## Constats (registre E8)

| ID | Sévérité | Surface | Constat | Statut |
|----|----------|---------|---------|--------|
| E9-01 | P1 | ops/api | Prod : `queue.driver=sync` (3 échantillons `/api/v1/health`) malgré `QUEUE_CONNECTION=redis` dans render.yaml → jobs lourds (PDF paie, documents Plan 62) exécutés en requête web → timeouts (corrèle #3259) | issue à ouvrir |
| E9-02 | P3 | api | `EdgeSyncDaemonCommand` : 6 fallbacks `?? env(...)` morts après `config:cache` (la règle « pas d'env() hors config » est documentée dans HealthController mais violée ici) | issue à ouvrir |
| E9-03 | P3 | mobile | `leopardo_core/lib/models/sync_models_example.dart` : 12 `print()` dans le code `lib/` livré (fichier « example » dans le bundle de production) | issue à ouvrir |
| E9-04 | P1 | ops | `leopardo-rh.com` NXDOMAIN confirmé (curl 000 ×2) — déjà tracé #3452 (ops propriétaire) | référencé |
| E9-05 | P1 | ops | API prod v4.23.5 vs main 4.24.0+ ; `/api-explorer` 500 ; `/i18n/catalog/fr` 500 — déjà tracés #2627/#2632/#2812 | référencé |
| E9-06 | OK | api | Stripe webhook sans signature : main renvoie 400 JSON propre (parsing manuel sans SDK throw) ; le 500 HTML live = symptôme deploy stale | aucune action |

## Out of Scope

- Correction DNS/Vercel/Render (accès propriétaire requis).
- Refonte du pipeline CI (file saturée — sujet séparé).
