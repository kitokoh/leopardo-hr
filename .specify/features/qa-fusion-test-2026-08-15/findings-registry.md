# Registre des manquements — Session fusion & test 2026-08-15

> Mission : merger le max de branches, tester dans tous les sens, consigner (Spec Kit), implémenter.
> Anti-doublon : constats déjà couverts par une issue/PR mergeable exclus ou référencés.

| ID | Sév | Constat | Preuve | Statut |
|----|-----|---------|--------|--------|
| F1 | P1 | Trial/verify 503 : fix #2996 (b3071d00) utilise `status=processing` sur company_requests sans étendre la CHECK constraint (`pending/approved/rejected`) | `SQLSTATE[23514]` sur `POST /trial/verify` ; `SelfServiceTrialTest` 4 KO | Corrigé → PR #3227 (7/7 verts) |
| F2 | P2 | `TrialWelcomeMail` passe `trialDays => 30` en dur vs provisioning 14 j | `api/app/Mail/TrialWelcomeMail.php:39` | Corrigé → PR #3229 |
| F3 | P1 | Build admin rouge : `DocumentReportIcon` inexistant (issue #3114) | `vite build` KO sur main | Corrigé → PR #3161 (mergée) |
| F4 | P2 | OpenAPI : 137 routes non documentées (`check-openapi-coverage.sh`) | rapport local 2026-08-15 | Ouvert (partiel #3121 : 20 routes) |
| F5 | P3 | Mobile : manifeste vs routeurs réels désalignés (#2212 non résolu) | `check-mobile-manifest-routes.sh` ECHEC | Ouvert (#2212) |
| F6 | P2 | Email vs provisioning : `SendDripEmailsJob`/drips non vérifiés pour la durée annoncée | constat F2 connexe | À vérifier |
| F7 | P1 | PHPStan Strict rouge sur main (17 erreurs #3298 : GenerateBankExportJob 3e arg ignoré, PayrollCalculator clés dupliquées merge #3128, 3 erreurs TrialWelcomeMail introduites par mon #3229, fixtures non typées, baseline counts) | issue #3298 + run local | Corrigé → branche fix/3298-phpstan-main-vert (PR ouverte) |
| F8 | P1 | Verify trial : réponse `trial.days=30` + `ends_at=now+14j` (contradiction interne, provisioning réel 14j) | SelfServiceTrialController.php:222-225 | Couvert par session parallèle (#3218, days→14) |
| F9 | P2 | Flux trial E2E validé localement (signup→verify→201, tenant + manager provisionnés) après fix F1 | curl local (PG14 leopardo) | ✅ |

## Vérifications runtime effectuées (locale, PG16 + bootstrap CI)

- [x] `/api/v1/health`, `/health/live`, `/health/ready` → 200
- [x] Smoke `qa_api_smoke.py` : 37/38 (login super-admin 401 avant reset mot de passe ; 429 throttling attendus après répétitions)
- [x] Platform admin : login, /platform/auth/me, /platform/companies, /platform/country-defaults, /platform/plans → 200
- [x] Vitrine jest : 305/305 (16 suites) sur main courant
- [x] Web `next build` : OK ; Admin `vite build` : OK (après fix)
- [x] `SelfServiceTrialTest` : 7/7 (52 assertions)
- [x] Checks repo : migrations ✅, pays ✅, env parity ✅, unrouted controllers ✅ (145), orphan interfaces ✅ (20 allowlist)
- [x] `check-openapi-coverage.sh` : 137 routes manquantes (liste en annexe)
- [x] `check-mobile-manifest-routes.sh` : ECHEC (routes /history, /modules, /team, /tasks, /absences, /settings non déclarées dans les routeurs réels)

## Annexes

### OpenAPI — routes manquantes (extrait, 137 total)
/ai/actions/{param}/reject · /ai/agent/run · /ai/agent/workflows · /ai/voice/* · /announcements* ·
/auth/* · /cabinet/shared|shares|stats · /calendar/* · /company-requests · /conversations* ·
/dashboard/{admin,comptable,marketing,rh} · /departments* · /edge* · /employees/link-user ·
/employees/{param}/assign-role · /employees/{param}/leave-balances · /hr/* · /marketing/* ·
/me/contract · /notifications/* · /onboarding/* · /payrolls/{param}* · /platform/* ·
/public/careers/* · /recruitment/* · /reports/* · /smart-attendance/mode-settings ·
/support-tickets* · /user/* · /webhooks/*
