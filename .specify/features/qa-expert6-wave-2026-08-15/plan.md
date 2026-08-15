# Plan: QA Expert 6 — Vague de constats 2026-08-15 (#3427–#3437)

**Input**: spec.md — 11 constats (1 P1, 4 P2, 6 P3).

## Stratégie

1. P1 d'abord (#3427 Edge authz), puis P2, puis P3.
2. Chaque correctif : branche `fix/<issue>-<slug>` depuis origin/main, PR `Closes #N`, CHANGELOG sous `[Unreleased]`.
3. Vérifications : gardes repo locaux (web/admin build+lint, checkers) ; API/mobile via CI (pas de PHP/Flutter local).
4. Anti-doublon : vérifier branches/PRs existantes avant chaque branche (#2400).
5. Coordination : le swarm merge en continu — re-fetch main avant chaque push.

## Phases

### Phase 1 — #3427 [P1] API Edge authz
- [ ] api.manager sur groupe edge admin + valid_days borné + test 403.

### Phase 2 — P2
- [ ] #3428 [P2] API CameraPermission tenant check.
- [ ] #3432 [P2] Mobile Contract.fromJson null-safe.
- [ ] #3434 [P2] Web FAQ EN/TR 14j + TrialWelcomeMail 14j.
- [ ] #3435 [P2] Web case-studies generateMetadata + sitemap.

### Phase 3 — P3
- [ ] #3429 [P3] API markPaid verrouillage.
- [ ] #3430 [P3] API onboarding complete/skip restreint.
- [ ] #3431 [P3] Mobile statut disputed localisé.
- [ ] #3433 [P3] Mobile DateTime.parse core.
- [ ] #3436 [P3] Admin CSV PayrollView échappé.
- [ ] #3437 [P3] Admin libellés features.

### Phase 4 — Post-merge
- [ ] Vérifier main vert + fermetures automatiques.
