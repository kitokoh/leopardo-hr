# Tasks: QA Expert #2 — API (api/) (2026-08-15)

**Input**: spec.md + plan.md — chaque tâche correspond à une issue GitHub ouverte (label `qa-expert2-2026-08-15`).

## Phase 1 — #3055 [P2] API — GET /employees/{id}/leave-balances sans garde de rôle : un employé lit les soldes de tout coll
- [ ] T001 [#3055] Implémenter le correctif (détails dans le corps de l'issue #3055)
- [ ] T002 [#3055] Vérification : build/lint (web/admin) ou contrat/analyse (mobile/api) + CHANGELOG

## Phase 2 — #3056 [P2] API — essai self-service : réponse verify annonce 14 jours alors que le tenant est provisionné 30 j 
- [ ] T003 [#3056] Implémenter le correctif (détails dans le corps de l'issue #3056)
- [ ] T004 [#3056] Vérification : build/lint (web/admin) ou contrat/analyse (mobile/api) + CHANGELOG

## Phase 3 — #3057 [P2] API — échec d'envoi OTP trial avalé → 200 « Code envoyé » sans code, aucun resend (résiduel #2678)
- [ ] T005 [#3057] Implémenter le correctif (détails dans le corps de l'issue #3057)
- [ ] T006 [#3057] Vérification : build/lint (web/admin) ou contrat/analyse (mobile/api) + CHANGELOG

## Phase 4 — #3058 [P2] API — webhook email-bounce : services.mail_bounce_webhook.secret défini nulle part → 503 permanent (
- [ ] T007 [#3058] Implémenter le correctif (détails dans le corps de l'issue #3058)
- [ ] T008 [#3058] Vérification : build/lint (web/admin) ou contrat/analyse (mobile/api) + CHANGELOG

## Phase 5 — #3059 [P3] API — per_page/limit non bornés sur 11 endpoints (approvals, billing, ai-gateway, audit-log, cabinet
- [ ] T009 [#3059] Implémenter le correctif (détails dans le corps de l'issue #3059)
- [ ] T010 [#3059] Vérification : build/lint (web/admin) ou contrat/analyse (mobile/api) + CHANGELOG

## Phase 6 — #3060 [P3] API — clé de signature QR onboarding en fallback codée en dur (fail-open si APP_KEY vide → QR forgea
- [ ] T011 [#3060] Implémenter le correctif (détails dans le corps de l'issue #3060)
- [ ] T012 [#3060] Vérification : build/lint (web/admin) ou contrat/analyse (mobile/api) + CHANGELOG

## Phase 7 — #3061 [P3] API — drift OpenAPI résiduel : groupes PA2/platform à 0% (announcements, conversations, impersonatio
- [ ] T013 [#3061] Implémenter le correctif (détails dans le corps de l'issue #3061)
- [ ] T014 [#3061] Vérification : build/lint (web/admin) ou contrat/analyse (mobile/api) + CHANGELOG

## Phase 8 — #3062 [P3] API — méthode morte TrainingController::indexSessionsAll jamais routée (la route utilise indexAllSes
- [ ] T015 [#3062] Implémenter le correctif (détails dans le corps de l'issue #3062)
- [ ] T016 [#3062] Vérification : build/lint (web/admin) ou contrat/analyse (mobile/api) + CHANGELOG

## Phase 9 — #3063 [P3] API — LeavePolicyController dupliqué (modules Absence vs Planning) : la copie Absence est celle non 
- [ ] T017 [#3063] Implémenter le correctif (détails dans le corps de l'issue #3063)
- [ ] T018 [#3063] Vérification : build/lint (web/admin) ou contrat/analyse (mobile/api) + CHANGELOG

## Phase 10 — #3064 [P3] API — drift docs↔code : RBAC_ROUTE_MATRIX documente /onboarding-setup* sous api.manager, le code l'o
- [ ] T019 [#3064] Implémenter le correctif (détails dans le corps de l'issue #3064)
- [ ] T020 [#3064] Vérification : build/lint (web/admin) ou contrat/analyse (mobile/api) + CHANGELOG

## Phase 11 — #3065 [P3] API — POST /employees/link-user : employee_id non validé comme appartenant à la société de l'acteur 
- [ ] T021 [#3065] Implémenter le correctif (détails dans le corps de l'issue #3065)
- [ ] T022 [#3065] Vérification : build/lint (web/admin) ou contrat/analyse (mobile/api) + CHANGELOG
