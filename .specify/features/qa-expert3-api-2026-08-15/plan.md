# Plan: QA Expert #3 — API (2026-08-15)

**Input**: spec.md.

## Stratégie
1. P1/P2 d'abord (sécurité, contrats), puis P3 (hygiène).
2. Chaque correctif : branche `fix/<issue>-<slug>` depuis origin/main, PR `Closes #N`, CHANGELOG.
3. Vérification locale : tests ciblés + PHPStan strict + suite complète ; CI comme source de vérité finale.

## Phases
### Phase 1 — Sécurité (livrée)
- [x] #3055 garde leave-balances (PR #3214)
- [x] #3060 QR fail-closed (PR #3214)
- [x] #3065 link-user cross-tenant (PR #3214)

### Phase 2 — Cohérence billing (livrée)
- [x] #3056 durée d'essai 30 j (PR #3343)

### Phase 3 — Hygiène API (livrée)
- [x] #3059 per_page borné (PR #3420)
- [x] #3062 route training (PR #3420)
- [x] TrainingGlobalListTest end_date (PR #3420)

### Phase 4 — Restants
- [ ] #3057 OTP avalé → réponse honnête + resend
- [ ] #3058 mail_bounce_webhook secret (config + .env.example + doc)
- [ ] #3061 OpenAPI drift (groupes PA2/platform)
- [ ] #3064 RBAC_ROUTE_MATRIX aligné
