# Plan — Session QA Expert 7 (2026-08-15)

## Architecture / Stack
- Surfaces : `front/web` (Next.js), `front/admin-dashboard` (Vue 3),
  `api` (Laravel 12), `front/mobile_apps` (Flutter, core partagé).
- Validation locale : Node (tsc + eslint + jest + check-mojibake) pour web/admin ;
  changements PHP/Dart chirurgicaux vérifiés par CI (sandbox sans PHP/Dart).

## Phases
1. Recon : issues/PR/branches/CI (bilan 276 issues, 67 PRs ouvertes).
2. Implémentation web (7 issues) : #3328 #2984 #2985 #2987 #3266 #3264 #3435 #3254.
3. Implémentation admin (3 issues) : #3280 #3275 (+ #3437 abandonné, PR #3465 canonique).
4. Implémentation API (4 issues) : #3320 #3002 #3244 #3309.
5. Implémentation mobile (3 issues) : #3005 #3432 #3433.
6. Audit ciblé → nouveaux constats #3540 #3541 + implémentation.
7. Rebase des branches en conflit sur origin/main.
8. Doc session + artefacts spec-kit.

## Technical Constraints
- 1 branche/PR par issue ; `Closes #` dans le body.
- CHANGELOG entrée obligatoire par PR.
- Anti-dup : vérifier branches ET corps de PR avant claim.
