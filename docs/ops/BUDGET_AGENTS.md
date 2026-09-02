# 💰 Budget & cadence agents — Leopardo RH (issue #5148, R6)

**Version** : 1.0 · **Date** : 2026-08-19 · **Mise à jour** : chaque vendredi (rituel bilan)
**Règle d'arrêt** : au plafond mensuel, les agents *feature* sont stoppés. Seuls les fixes P0/P1 continuent (sécurité, funnel, paie bloquante).

---

## Règles de cadence (contraignantes)

1. **1 agent feature max en parallèle** — une seule spec en cours d'implémentation à la fois
2. **Batchs quotidiens** : les agents poussent par lots (matin/soir), pas en flux continu
3. **50 % du temps de chaque agent** : traîne (fixes ouverts, branches mortes, doublons, dette) — cf. #5153
4. **Revue humaine de 100 % des PRs** — pas de merge auto
5. **Arrêt automatique au plafond** — le tableau ci-dessous est la source de vérité

## Tableau de suivi (à remplir chaque vendredi)

| Fournisseur | Usage | Coût estimé / mois | Plafond alloué | Consommé (mois courant) | Statut |
|---|---|---|---|---|---|
| Devin AI | agent feature / fixes | *(à chiffrer — facture fournisseur)* | | | 🟢 |
| Google Jules | frontend / mobile | *(à chiffrer)* | | | 🟢 |
| KiloClaw / Aria | agents internes | *(à chiffrer — API LLM)* | | | 🟢 |
| QA agents | sessions de test | *(à chiffrer)* | | | 🟢 |
| APIs LLM diverses (spec, génération) | divers | *(à chiffrer)* | | | 🟢 |
| **TOTAL** | | | **= plafond global** | | |

> ⚠️ **Action humaine requise** : renseigner les coûts réels dès la première édition (factures Devin/Jules + consommation API). Tant que le tableau est vide, le plafond ne peut pas être appliqué.

## Procédure

- **Vendredi (bilan)** : reporter la consommation réelle du mois dans le tableau + ajuster le plafond si besoin (décision fondateur)
- **Au plafond** : message dans l'issue de suivi + arrêt des agents feature ; les fixes P0/P1 continuent
- **Dépassement non justifié** : revue de la cadence (règle 1-3) avant d'augmenter le budget

## Liens
- Issue de suivi : #5148 · Plan 60 jours : `PLAN_60_JOURS.md` · Cadence : AGENTS.md (section gouvernance)

---
*Document généré depuis l'issue #5148 (plan 60 jours).*
