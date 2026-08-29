# Runbooks backup / restauration / rollback par bounded context

> **Issue :** [MAT-015 #5873](https://github.com/kitokoh/leopardo-hr/issues/5873)
> **Registre :** `dev-hub/tools/runbook-registry.json`
> **Garde CI :** `dev-hub/tools/check-runbooks.sh` (job Hygiene Guards)
> **Tests :** `dev-hub/tools/tests/check-runbooks.test.sh` (5 scénarios)

## Objectif

Chaque bounded context actif dispose d'une couverture **backup / restauration /
rollback / incident** — soit par un runbook dédié, soit via les runbooks
plateforme (`docs/GESTION_PROJET/RUNBOOK_BACKUP_RESTORE.md`,
`RUNBOOK_ROLLBACK.md`, `RUNBOOK_INCIDENT_P1.md`, `RUNBOOK_OPERATIONS.md`).

## Couverture par BC (résumé)

| BC | Runbooks dédiés | Couverture |
|---|---|---|
| BC-01 PLATFORM | DEPLOY, MARKETING_ROLLBACK | Provisioning, feature flags, rollback déploiement |
| BC-02 TENANT | — | Backup/restore schémas tenant (plateforme) |
| BC-03 IDENTITY | GOOGLE_OAUTH_PROD | Auth/SSO + incident P1 |
| BC-04..07, 09, 10, 12, 13, 18, 23 | — | Backup/restore plateforme (dump chiffré, restore scratch) |
| BC-08 ACCOUNTING | `docs/accounting/RUNBOOK.md` | Journal, clôtures, FEC |
| BC-11 CRM / BC-14 INTEGRATION / BC-20 DOCUMENTS | `docs/ops/RUNBOOK_FILES_CRM.md` | Outbox/inbox/DLQ, replay, purge |
| BC-15 FUEL / BC-16 EDU / BC-17 RETAIL / BC-22 ANALYTICS | à livrer (planifié) | Livré avec le pilote (FUEL-021, EDU-021) |

## Preuve d'exercice (critère d'acceptation MAT-015)

Un exercice de **restauration** et un **rollback contrôlé** produisent une
preuve datée dans `docs/GESTION_PROJET/RUNBOOK_DRILLS_LOG.md` :

```markdown
| Date | Type | Environment | Trigger | Result | Duration | Evidence | Actions |
|---|---|---|---|---|---|---|---|
| YYYY-MM-DD | restore / rollback | staging / prod | planned / incident | pass / fail | 00m | lien ou log | ticket de suivi |
```

Le garde exige que ce journal contienne au moins une entrée datée (dernier
exercice tracé).

## Exécution locale

```bash
bash dev-hub/tools/check-runbooks.sh
bash dev-hub/tools/tests/check-runbooks.test.sh
```

## Rollback

- Revert du commit du garde/registre ; scripts bash autonomes sans état.
- Un BC planifié passe à couvert dans la PR qui livre son runbook de pilote.
