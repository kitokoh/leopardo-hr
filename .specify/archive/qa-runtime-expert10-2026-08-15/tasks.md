# Tasks — QA runtime expert 10 — 2026-08-15

| # | Tâche | Statut |
|---|-------|--------|
| T1 | Build + lint vitrine Next.js sur main | ✅ verts (eslint 0/0, build OK) |
| T2 | Build + lint admin Vue sur main (worktree dédié — éviter les races de merge) | ✅ verts |
| T3 | PHPStan strict level 8 local sur main (mêmes flags CI) | 🔴 33 erreurs → contribution #3515 (baseline régénérée, `[OK] No errors`) |
| T4 | Gardes repo rejouées localement (OpenAPI coverage, migrations, pays, orphelins) | ✅ toutes vertes |
| T5 | Sondes live prod (vitrine 13 routes, headers, /health, DNS, /plans 404 attendu) | ✅ consignées — preuve #2627, confirmation #3452 |
| T6 | Fermeture issues fixed-but-open avec preuve code | ✅ #3340, #3443 (vérifiés clos : #3324, #3436) |
| T7 | Implémenter #3528 (APP_VERSION sync + garde CI) | ✅ PR #3579 |
| T8 | Dé-confliction PRs swarm (CHANGELOG union / superset main) | ✅ 10 branches traitées, 2 merges débloqués (#3464, #3454) |
| T9 | Arbitrage doublon #3435 (1 PR = 1 issue) | ✅ #3482 fermée → canonique #3483 |
| T10 | Anti-doublon : vérifier branches+PRs avant tout claim | ✅ appliqué (#3528 libre ; #3431-3433 vus claimés → non touchés) |
