# Plan de recette UAT — FuelStation (FUEL-022)

> **Issue :** [FUEL-022 #5816](https://github.com/kitokoh/leopardo-hr/issues/5816) — phase 1 : plan de recette
> **Gates :** [MAT-018 #5876](https://github.com/kitokoh/leopardo-hr/issues/5876) — la recette signée (gate `recette`) conditionne le GO pilote.

## 1. Cadre

La recette métier UAT couvre les parcours : **shifts, compteurs, ventes, caisses, stock, incidents, permissions**.
Chaque scénario doit être **signé par le métier** avec : date, exécutant, résultat (pass/fail), évidence (log/lien), anomalies ouvertes. **Zéro anomalie bloquante** avant release.

## 2. Scénarios de recette

| # | Parcours | Scénario | Critère de succès |
|---|---|---|---|
| U-01 | Shifts | Affectation pompiste → ouverture de shift → relevé ouverture | Shift tracé, idempotent |
| U-02 | Compteurs | Relevé par pompe/heure/opérateur, delta, anomalie, rollover | Écart expliqué ou anomalie flaggée (aucun ajustement silencieux) |
| U-03 | Ventes | Vente par pompe → session de caisse → clôture | Total sessions = Σ ventes ; clôture fige les écritures |
| U-04 | Caisse | Encaissements/décaissements, rapprochement | Écart nul ou expliqué et validé manager |
| U-05 | Stock | Livraison → niveau cuve → rapprochement compteurs/ventes/stock | Rapport d'écart explicable, rejouable |
| U-06 | Incidents | Incident matériel → maintenance → tâche → résolution | Cycle tracé, permissions respectées |
| U-07 | Permissions | operator vs manager vs principal sur routes `/api/v1/fuel/*` | 403/404 cross-tenant corrects, Policies appliquées |
| U-08 | Kill switch | Désactivation feature flag `fuel` en cours d'exploitation | 403 explicite, aucune écriture, réactivation propre |
| U-09 | Restauration | Restore scratch du tenant pilote (drill) | Preuve datée dans `RUNBOOK_DRILLS_LOG.md` |

## 3. Rôles de recette

- **Métier** (signataire) : responsable exploitation pilote ;
- **PM/QA** : exécution, évidence, suivi des anomalies ;
- **Support** : fenêtre planifiée, escalade P1 (RUNBOOK_INCIDENT_P1.md).

## 4. Sortie de recette

- PV de recette signé (par scénario) ;
- liste des anomalies bloquantes (doit être vide) ;
- release notes + formation pompistes/manager livrées ;
- gate `recette` passé à `validated` dans `pilot-gates.json` (décision du chef de projet, jamais de l'agent).

## 5. Prérequis

- Fondations FUEL-001..009 mergées ; runbook pilote (`RUNBOOK_PILOT_FUELSTATION.md`) appliqué ;
- kill switch et restauration testés avant la recette.
