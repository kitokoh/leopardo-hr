# Feature Specification: deploy-staging — pattern anti-famine #3545 (Closes #4341)

**Issue**: #4341 (P2, CI, ops) — deploy-staging.yml lisait `workflow_run.conclusion` : un run
annulé en pending (famine de file) → gate vert + skip silencieux (`core.warning` noyé).

## Fix (miroir deploy-main #3545/#4359)
- Déclencheur `push: main` (source de vérité unique) + `workflow_dispatch` ; suppression de
  `workflow_run`.
- Gate : polling borné des runs « Tests - Leopardo RH » du SHA (30 min, 60 s) au lieu de la
  conclusion d'un parent ; skip explicite `::warning::` + step summary ; garde anti-stale
  (main head == SHA avant déploiement).
- Concurrency group par SHA (`deploy-staging-${{ github.sha }}`).

## Tests
- actionlint + shellcheck verts (checks requis).
- Vérification manuelle : run de déploiement avec CI passée → deploy ; CI annulée → skip
  explicite visible dans le step summary.
