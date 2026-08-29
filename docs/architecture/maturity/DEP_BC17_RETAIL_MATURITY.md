# DEP-BC17 — Rapport de maturité BC-17 RETAIL

> **Issue :** [DEP-BC17 #5893](https://github.com/kitokoh/leopardo-hr/issues/5893)
> **Contexte :** BC-17 — Retail & POS (produits, magasins, caisse, tickets, stocks, synchronisation POS)
> **Date :** 2026-08-28
> **Statut :** **Planifié** — aucune implémentation sur `main` ; la solution est en attente de validation des pilotes FuelStation/EduManager (MAT-018) avant lancement.

## 1. Cartographie (état `main`)

| Élément | État |
|---|---|
| `api/app/Solutions/Retail` | Absent (futur — registre BC, statut `planned`, priorité P3) |
| Migrations `*retail*` | Absentes |
| Routes `/api/v1/retail/*` | Absentes |
| Registre BC | `BC-17` = `planned`, owner @kitokoh, allowed_dependencies BC-02/03 |

## 2. Scorecard des 12 dimensions

| Dimension | Statut | Constat |
|---|---|---|
| Toutes (1-12) | ⏳ Planifié | Évaluation à l'arrivée du code — le DoD commun (12 dimensions) s'appliquera |

## 3. Positionnement programme

- P3 : la verticale Retail démarre **après** les pilotes FuelStation et EduManager (MAT-018) — les patterns (manifest de solution, activation tenant, runbook pilote, recette signée) seront réutilisés tels quels.
- Contrats cibles : BC-02 (TENANT), BC-03 (IDENTITY) ; synchronisation POS via BC-14 (INTEGRATION) et BC-19 (DEVICE).

## 4. Prochaine action

Ré-évaluer au go/no-go des pilotes (MAT-018) ; créer les issues RETAIL-* à partir du backlog de profondeur quand la verticale est lancée.
