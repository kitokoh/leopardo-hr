# Tasks: Session QA Expert 9 2026-08-15

**Spec**: `spec.md` (même dossier) | **Date**: 2026-08-15

## Phase A — Consolidation (en cours)

- [x] A1. Vérifier main rouge PHPStan (#3453) → rebase + merge #3455 (canonical fix)
- [x] A2. Fermer doublons PRs : #3451 (dup #3459), #3353 (dup #3407), #3371 (dup #3440),
      #3119/#3207 (obsolètes #3118), #3218/#3343 (#3056 convergé), #3460 (supersédé #3524)
- [x] A3. Fermer issues résolues avec preuve : #3056, #3226, #3401
- [ ] A4. Rebase + merge des PRs conflictuelles résiduelles (pipeline union CHANGELOG)
- [ ] A5. Clôturer les PRs main-green obsolètes une fois PHPStan main prouvé vert en CI

## Phase B — Nouveaux constats (issues à créer)

- [ ] B1. E9-01 — issue P1 ops : queue driver sync en prod
- [ ] B2. E9-02 — issue P3 api : fallbacks env() morts EdgeSyncDaemonCommand
- [ ] B3. E9-03 — issue P3 mobile : print() dans sync_models_example.dart

## Phase C — Implémentation

- [ ] C1. E9-02 + E9-03 (hygiène, périmètre net, faible risque)
- [ ] C2. Reprendre les issues P1/P2 non couvertes après drain de la file
