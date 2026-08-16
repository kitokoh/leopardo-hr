# Session QA — Moclaw (2026-08-16) : consolidation main, audit 360°, implémentations

**Agent**: qa-expert-moclaw — session multi-agents concurrente (kitokoh/leopardo-hr)
**Périmètre**: Phase 0 (merges → main vert), Phase 1 (audit 360°), Phase 2 (dette), Phase 3 (implémentation).

## Phase 0 — Consolidation & main vert

### PRs fusionnées (13 au total, dont 5 reprises + 8 du swarm vertes)
- **Reprises** : #4275 (fix/3237, résolution conflits i18n — clés domaine `auth.*`/`employees.*`/`payroll.*` de main conservées, 10 clés `errors.*` gardées), #4276, #4277, #4279 (docs — conflit add/add QA_SESSION expert14 résolu en fusion des 2 rapports), #4280.
- **Swarm vertes (checks requis OK) fusionnées** : #4348 (FAQ accordéon), #4349 (worker queues), #4371 (i18n batch3), #4374 (admin i18n keys), #4375 (config hygiene), #4376 (lang lint guard), #4377 (tests contrôleurs), #4366 (mobile hygiene).

### Nettoyage
- **60 branches** mergées/superseded supprimées (dont mes 3 merges de main devenus obsolètes sur #4191×2/#4197).
- **Résolution conflits** : errors.php ×4 locales (union de clés #4310 + vague expert20) sur fix/4310 ; CHANGELOG absorbés sur fix/4343-4345.
- **CI** : 6 runs main obsolètes annulés (famine #3545) ; vérification des runs actifs ; main HEAD vert sur les checks requis visibles.

## Phase 1 — Constats d'audit (vérifiés sur le code)

| Issue | Constat | Devenir |
|---|---|---|
| #4379 | (P1 suspecté) drift prix annuel home 278/950 vs 288/948 | **Invalidé** — la home utilise `annualPrice` canonique (vérif. `PricingSection.tsx:110`) ; le drift n'existe que dans la copie morte → fermée avec preuve |
| #4380 | Badge « -17% » du toggle annuel /checkout contredit la source unique « jusqu'à 20 % » (#4202) ; faux pour Operations (-20,2 %) | Issue + fix en PR #4387 |
| #4381 | `sections/PricingSection.tsx` + `PricingCard.tsx` morts (0 importeur prod, badge « -20% » + `×0.8` divergents) | Issue + fix en PR #4384 |
| #4419 | **P1** — billing dashboard : boutons « Payer en ligne » Starter/Business → 422 permanent (`Rule::in(PlanCode)` = free/pilot/operations/enterprise ; `StripeService` ne connaît que pilot/operations/enterprise) | Issue + fix en PR #4421 |

### Baselines locaux (tous verts sur main)
- `front/web` : lint ✓ tsc ✓ **496 tests** ✓
- `front/web-offline` : lint ✓ tsc ✓ 22 tests ✓
- `front/admin-dashboard` : lint ✓ build ✓
- `front/zkteco-kiosk` : pytest **27 tests** ✓
- Scan statique API : pas de TODO/FIXME, pas de catch muets, requêtes tenant-scopées OK (SocialDeclaration etc.), proxy Next.js durci (hop-by-hop, timeout 15s, 502 JSON).

## Phase 2 — Dette existante

- 8 PRs swarm vertes fusionnées (ci-dessus) ; #4362/#4363 rafraîchies (conflits CHANGELOG/errors.php résolus) → en attente CI.
- #4382 (PHPStan main — 18 erreurs héritées) = le débloqueur de ~30 PRs ; CI en file.
- La famine #3545 reste le goulot : chaque PR déclenche les 5 checks requis même frontend-only.

## Phase 3 — Implémentations livrées

| Issue | PR | Contenu | Validation |
|---|---|---|---|
| #4381 | #4384 | suppression composants tarifs morts + barrel + tests | lint/tsc/jest verts |
| #4380 | #4387 | badge annuel checkout → « jusqu'à 20 % » ×4 locales | lint/tsc/jest verts |
| #4419 | #4421 | billing dashboard → codes canoniques pilot/operations/enterprise | lint/tsc/jest verts (35 suites/499) |

Specs spec-kit : `.specify/features/4380-checkout-annual-badge/`, `4381-dead-pricing-section/`, `4419-billing-legacy-plan-codes/` + `docs/specifications/ISSUE_*.md`.

## Leçons

1. **Vérifier le composant LIVE avant de créer une issue** : le drift prix (278/950) existait dans la copie morte, pas dans la home — le P1 #4379 a dû être fermé avec preuve. Le grep sur un nom de fichier ambigu (`PricingSection` ×2) a failli produire une issue fausse.
2. **Le rename canonique #2977/#3919 n'a pas été propagé partout** : la vitrine (#4209) et pricing.ts étaient réalignés, mais le billing dashboard gardait starter/business → 422. Scanner `Rule::in(...)` backend vs valeurs envoyées par le front = technique de détection efficace.
3. **Les conflits errors.php sont des unions** : les PRs i18n ajoutent toutes des clés au même fichier — la résolution « prendre les deux côtés » est presque toujours correcte.
4. **Les checks requis s'appliquent à toutes les PRs** (même docs-only) → la file CI est le vrai goulot ; annuler les runs obsolètes avant de pousser reste indispensable (#3545).
