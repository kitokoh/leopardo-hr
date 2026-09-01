# Feature Specification: QA — Session fusion & test plateforme (2026-08-15)

**Feature**: `qa-fusion-test-2026-08-15`
**Created**: 2026-08-15
**Status**: Implémentation en cours
**Input**: Mission du propriétaire — merger le max de branches mergeable, tester l'app dans tous
les sens (vitrine, web, admin, mobiles, workflows, API, logiques, onboarding, cohérence),
consigner chaque manquement selon la méthode Spec Kit (.specify/), implémenter en fin de test,
implémenter le max d'issues ouvertes, main vert.

## Contexte

Session menée en parallèle d'autres sessions QA expert (expert2/3/4/5). Les constats ci-dessous
sont **nouveaux** ou **non couverts par une PR mergeable** au moment de la détection (règle
anti-doublon). La file CI GitHub Actions étant saturée (~500 runs queued, aucun check ne
complète), la validation locale (PHPUnit sur PG16 + bootstrap CI) fait foi pour ces correctifs.

## User Stories & Testing

### US1 — Le parcours d'essai self-service ne renvoie plus 503 (P1)

Un visiteur qui signup puis vérifie son OTP (`POST /trial/verify`) obtient 201 + un tenant
provisionné. Régression introduite par le fix anti-race b3071d00 (Closes #2996) : la
`CompanyRequest` est CLAIMÉE en `processing`, mais la CHECK constraint `company_requests_status_check`
n'autorise que `pending/approved/rejected` → `SQLSTATE[23514]` → 503 `TRIAL_VERIFY_UNAVAILABLE`.

**Pourquoi P1** : le parcours d'acquisition principal est mort en test et en prod ; le rollback
du claim échoue aussi.

**Independent Test** : `php artisan test --filter=SelfServiceTrialTest` (7 tests) — avant fix :
4 KO (0 assert), après : 7/7 (52 assertions).

**Acceptance Scenarios**:
1. **Given** un signup OTP valide, **When** POST /trial/verify, **Then** 201 + tenant provisionné.
2. **Given** le même OTP rejoué, **When** 2e verify, **Then** 409/refus sans double-provision.
3. **Given** `migrate` rejoué 2×, **Then** la migration est idempotente.

### US2 — L'email de bienvenue annonce la durée d'essai réelle (P2)

`TrialWelcomeMail` passait `trialDays => 30` en dur alors que le tenant est provisionné 14 j
(VerifyTrialSignup / ProvisionGuidedTrial / CompanyProvisioningService). L'email promettait
30 jours à des comptes à 14 jours.

**Independent Test** : `SelfServiceTrialTest` asserte `$mail->trialDays === 14`.

**Acceptance Scenarios**:
1. **Given** un provisioning 14 j, **When** envoi du mail, **Then** badge « 14 jours ».
2. **Given** un plan avec `trial_days` différent, **When** envoi, **Then** la durée du plan est utilisée.

### US3 — Le build admin-dashboard est vert (P1)

`vite build` échouait sur main : `DocumentReportIcon` inexistant dans @heroicons/vue (issue #3114).
Corrigé (PR #3161, mergée) — constat de clôture : le fix est sur main, build OK.

## Requirements

### Functional Requirements

- **FR-001**: La contrainte `company_requests.status` DOIT accepter `processing` (migration idempotente, qualifiée `public.`).
- **FR-002**: `TrialWelcomeMail` DOIT dériver `trialDays` du provisioning (subscription_start → subscription_end, repli plans.trial_days, puis 14).
- **FR-003**: Le build `front/admin-dashboard` DOIT passer (`npm run build`).

## Success Criteria

- **SC-001**: `SelfServiceTrialTest` 7/7 vert (52 assertions) sur PG16 + bootstrap CI.
- **SC-002**: `npm run build` (admin-dashboard) passe.
- **SC-003**: `npm run test` (vitrine jest) 305/305 vert.
- **SC-004**: `npm run lint` (web) 0 erreur.

## Assumptions

- La file CI GitHub Actions reste saturée pendant la session ; les checks requis ne tournent pas
  à l'heure de la rédaction (validation locale de substitution).
- Les sessions parallèles couvrent les vagues expert2/3/4/5 ; anti-doublon via issues ouvertes.
