# Feature Specification: CI — timeout-minutes sur tous les jobs GitHub Actions (issue #4414)

**Feature Branch**: `fix/4414-ci-timeouts`

**Created**: 2026-08-16

**Status**: Draft → Implemented

**Input**: Constat QA (session 2026-08-16) — seuls 3 jobs posent un `timeout-minutes`
(tests.yml:75, coverage-gate.yml:75, onboarding-smoke.yml:15). Les 72 autres retombent
sur le défaut GitHub de 360 min → un job pendu (infra, saturation CI, test bloquant)
occupe un runner jusqu'à 6 h et aggrave le mode de famine #3545.

## Problème

72/75 jobs sans `timeout-minutes` ; les schedulés lourds (codeql, secret-history-scan,
database-backup, admin-pages-deploy-guard) et les gates de deploy (deploy-main prepare,
e2e-staging, mobile-distribute) peuvent rester pendus sans borne.

## Décision

Politique de timeout par type de job (min) :

| Type | Timeout |
|---|---|
| Gardes/gouvernance (actionlint, claim-guard, hygiene, post-merge-audit, openapi lint, validate/sync, checks) | 15 |
| Web lint/build, offline, lighthouse, openapi coverage, regen-lock, smokes légers, release | 30 |
| Backend tests/coverage/phpstan/quality, payroll, jobs-queues, module structure | 45 |
| E2E/staging, k6 load, deploy (api, staging, admin, gate, prepare) | 45 |
| Scans schedulés (codeql, trufflehog, history, backup, restore) | 60–120 |
| Mobile CI (guard, analyze, build), kiosk | 90 |
| Distribution mobile (Firebase) | 120 |

Valeurs déjà posées conservées (backend-coverage 75 min — durée mesurée #4251 ;
onboarding-smoke 15 min).

## User Scenarios & Testing

### User Story 1 — Aucun job ne dépasse sa borne (Priority: P2)

**Independent Test**: `actionlint -no-color .github/workflows/*.yml` → 0 erreur
(validé localement, v1.7.12) ; `yaml.safe_load` de chaque workflow OK ; `grep -c
timeout-minutes` = 77 (73 jobs + 4 déjà présents ×2 doublons retirés → 75 lignes
uniques… vérifié par actionlint : aucune clé dupliquée).

**Acceptance Scenarios**:

1. **Given** la base de workflows, **When** on liste les jobs, **Then** chaque job a
   un `timeout-minutes` borné (≤ 120 sauf exception documentée).
2. **Given** un job qui pend (réseau/infra), **When** le timeout expire, **Then** le
   job échoue explicitement au lieu de rester pending 360 min.
3. **Given** la CI, **When** actionlint s'exécute, **Then** 0 erreur (clés non dupliquées).

## Edge Cases

- Jobs avec `timeout-minutes` déjà défini : non dupliqués (vérifié par actionlint).
- Workflows `workflow_dispatch`/schedulés : bornés aussi (pas d'exception).
- La politique reste conservatrice : les jobs mesurés lents (backend-coverage 62 min
  réels) gardent leur valeur existante.
