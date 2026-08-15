# Tasks — Render Redis + worker + scheduler (#3774)

## T1 — render.yaml
- [x] Base `leopardo-redis` (plan free, `allkeys-lru`, `ipAllowList: []`).
- [x] `REDIS_URL`/`REDIS_PASSWORD` branchés `fromDatabase` sur web, `leopardo-queue-worker`, `leopardo-scheduler`.
- [x] Commentaires override Upstash externe.

## T2 — Validation
- [x] YAML valide.
- [x] Aucune autre variable modifiée (mail, Stripe, Firebase, DB).
- [x] CHANGELOG `### Fixed` (Closes #3774).
