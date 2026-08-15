# Tasks: Session QA Expert 6 (2026-08-15)

**Input**: spec.md + findings-registry.md

## Phase 1 — Implémentation des constats

- [x] T-F6-001 [P2] [US1] Admin lint : catch bindings + retry mort → PR #3228 (Closes #3220).
- [x] T-F6-002 [P3] [US3] Vérifier collisions migrations sur origin/main → déjà corrigé (1e576375) → #3224 fermée avec preuve.
- [x] T-F6-003 [P3] [US2] Réparer check-issues-left-open-by-merged-prs.sh → PR #3301 (Closes #3225).
- [ ] T-F6-004 [P1] [US4] Relancer déploiement staging/prod + smoke post-deploy (ops, hors code — coordination swarm).

## Phase 2 — Merge campaign

- [ ] T-F6-005 [P2] Merger les PRs dont les 5 checks requis sont verts (boucle de merge).
- [ ] T-F6-006 [P2] Désengorger la file CI (cancel-orphan-runs.sh --superseded) — fait : 59 annulations.
- [ ] T-F6-007 [P2] Résoudre les conflits des PRs CONFLICTING et les remettre vertes.

## Phase 3 — Issues ouvertes

- [x] T-F6-008 [P2] Implémenter des issues ouvertes non assignées : #3326 checkout fallback (PR #3371), #3331 lien mort /offline (PR #3397), #3332 sitemap /share (PR #3399), #3321 per_page 8 endpoints (PR #3418), #3340 CSV injection (PR #3426).
- [x] T-F6-009 [P3] Vérifier #3323 (OpenAPI /public-holidays) → faux positif, fermé avec preuve.
- [ ] T-F6-010 [P2] Suivre la boucle de merge (179 runs orphelins annulés au total).
