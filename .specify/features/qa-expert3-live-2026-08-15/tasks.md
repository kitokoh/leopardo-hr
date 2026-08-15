# Tasks — QA live expert 3 — 2026-08-15

| # | Tâche | Statut |
|---|-------|--------|
| T1 | Fix CORS `allowed_origins_patterns` (regex) + test `CorsPreviewOriginTest` | ✅ livré (PR #3396) |
| T2 | Fix `SuperAdmin` casts datetime + vérification runtime `/platform/users` 200 | ✅ livré (PR #3396) |
| T3 | Fix `trial/verify` `days` 30→14 + assertion test | ✅ livré (PR #3396) |
| T4 | Vérifier build vitrine (tsc, eslint) et admin (vite build) sur main | ✅ verts |
| T5 | Smoke API live (auth, RBAC, isolation, workflows, cockpit) | ✅ 40+ endpoints, 3 bugs trouvés/fixés |
| T6 | S'assurer que #3227 (constraint company_requests) atterrit sur main | ⏳ swarm (PR #3227) |
| T7 | Vérifier flag `NEXT_PUBLIC_ENABLE_BLOG` sur les environnements déployés | ⏳ à confirmer propriétaire |
