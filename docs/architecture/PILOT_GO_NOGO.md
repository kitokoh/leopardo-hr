# Go/no-go des pilotes FuelStation & EduManager

> **Issue :** [MAT-018 #5876](https://github.com/kitokoh/leopardo-hr/issues/5876)
> **Registre :** `dev-hub/tools/pilot-gates.json`
> **Garde CI :** `dev-hub/tools/check-pilot-gates.sh` (job Hygiene Guards)
> **Tests :** `dev-hub/tools/tests/check-pilot-gates.test.sh`

## Principe

Aucun pilote métier (FuelStation, EduManager) ne passe en **GO** sans validation
complète de ses gates : la décision est **formelle**, datée et tracée dans ce
registre — un gate non validé suffit à maintenir le pilote en **pending** ou
**no_go**.

## Gates obligatoires (chaque pilote)

| Gate | Exigence | Référence |
|---|---|---|
| `manifest` | Manifest de solution + activation tenant idempotente | FUEL-001 / EDU-001 |
| `core_flow` | Parcours métier complet testé | FUEL-002..009 / EDU-002..008 |
| `api_security` | API, Policies, tests cross-tenant | FUEL-011 / EDU-009..010 |
| `runbook` | Pilote, runbook et rollback | FUEL-021 / EDU-021 |
| `security_review` | Revue sécurité des surfaces | MAT-017 |
| `performance` | Budgets performance | MAT-014 |
| `observability` | Observabilité et corrélation | MAT-009 |
| `golden_journey` | Golden journey pilote | GJ-06 / GJ-07 |
| `recette` | **Recette métier signée** | FUEL-022 / EDU-022 |

## Règles de décision

- **GO** : tous les gates `validated` + recette signée + fenêtre de pilote planifiée.
- **NO_GO** : au moins un gate bloqué (motif documenté dans le registre).
- **PENDING** : statut par défaut — le registre interdit un GO prématuré.

## Mise à jour

Quand un gate est livré, passer son `status` à `validated` dans la PR qui le
livre (avec le lien de preuve dans `label`). La décision finale
(`go_decision`) est prise par le chef de projet, jamais par l'agent seul.

## Exécution locale

```bash
bash dev-hub/tools/check-pilot-gates.sh
bash dev-hub/tools/tests/check-pilot-gates.test.sh
```

## Rollback

- Revert du commit ; registre + script autonomes sans état.
