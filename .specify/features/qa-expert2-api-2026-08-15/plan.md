# Plan: QA Expert #2 — API (api/) (2026-08-15)

**Input**: spec.md — 11 findings.

## Stratégie

1. Corriger par priorité : P1 d'abord (bloquants build/rendu), puis P2 (UX/contrats), puis P3 (hygiène).
2. Chaque correctif : branche `fix/<issue>-<slug>` depuis `origin/main` récent, PR avec `Closes #N`, CHANGELOG sous `## [Unreleased]`.
3. Vérifications : lint + build pour web/admin ; `flutter analyze`/contrats pour mobile (CI) ; Pint/PHPStan/tests via CI pour API.
4. Anti-régression : vérifier que les fichiers touchés ne réécrasent pas des fixes plus récents de main (`git diff origin/main...HEAD`).

## Phases

### Phase 1 — #3055 [P2] API — GET /employees/{id}/leave-balances sans garde de rôle : un employé lit les soldes de tout coll
- [ ] Branche `fix/3055-slug` depuis origin/main ; implémentation minimale ; tests/checks ; PR `Closes #3055`.

### Phase 2 — #3056 [P2] API — essai self-service : réponse verify annonce 14 jours alors que le tenant est provisionné 30 j 
- [ ] Branche `fix/3056-slug` depuis origin/main ; implémentation minimale ; tests/checks ; PR `Closes #3056`.

### Phase 3 — #3057 [P2] API — échec d'envoi OTP trial avalé → 200 « Code envoyé » sans code, aucun resend (résiduel #2678)
- [ ] Branche `fix/3057-slug` depuis origin/main ; implémentation minimale ; tests/checks ; PR `Closes #3057`.

### Phase 4 — #3058 [P2] API — webhook email-bounce : services.mail_bounce_webhook.secret défini nulle part → 503 permanent (
- [ ] Branche `fix/3058-slug` depuis origin/main ; implémentation minimale ; tests/checks ; PR `Closes #3058`.

### Phase 5 — #3059 [P3] API — per_page/limit non bornés sur 11 endpoints (approvals, billing, ai-gateway, audit-log, cabinet
- [ ] Branche `fix/3059-slug` depuis origin/main ; implémentation minimale ; tests/checks ; PR `Closes #3059`.

### Phase 6 — #3060 [P3] API — clé de signature QR onboarding en fallback codée en dur (fail-open si APP_KEY vide → QR forgea
- [ ] Branche `fix/3060-slug` depuis origin/main ; implémentation minimale ; tests/checks ; PR `Closes #3060`.

### Phase 7 — #3061 [P3] API — drift OpenAPI résiduel : groupes PA2/platform à 0% (announcements, conversations, impersonatio
- [ ] Branche `fix/3061-slug` depuis origin/main ; implémentation minimale ; tests/checks ; PR `Closes #3061`.

### Phase 8 — #3062 [P3] API — méthode morte TrainingController::indexSessionsAll jamais routée (la route utilise indexAllSes
- [ ] Branche `fix/3062-slug` depuis origin/main ; implémentation minimale ; tests/checks ; PR `Closes #3062`.

### Phase 9 — #3063 [P3] API — LeavePolicyController dupliqué (modules Absence vs Planning) : la copie Absence est celle non 
- [ ] Branche `fix/3063-slug` depuis origin/main ; implémentation minimale ; tests/checks ; PR `Closes #3063`.

### Phase 10 — #3064 [P3] API — drift docs↔code : RBAC_ROUTE_MATRIX documente /onboarding-setup* sous api.manager, le code l'o
- [ ] Branche `fix/3064-slug` depuis origin/main ; implémentation minimale ; tests/checks ; PR `Closes #3064`.

### Phase 11 — #3065 [P3] API — POST /employees/link-user : employee_id non validé comme appartenant à la société de l'acteur 
- [ ] Branche `fix/3065-slug` depuis origin/main ; implémentation minimale ; tests/checks ; PR `Closes #3065`.

## Finalisation
- [ ] Mise à jour `docs/qa/QA_SESSION_2026-08-15-expert2.md` (bilan par surface).
- [ ] CHANGELOG.md : entrée `### Fixed` par PR.