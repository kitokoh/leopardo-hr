# 🧪 Recette pilote — Paie DZ (template, issue #5247)

> Template de recette pour un **pilote réel** : un employé DZ, un bulletin
> conforme et un virement validés de bout en bout, sans assistance dev.
> Remplir une ligne par scénario dans le journal de validation (§4) et faire
> relire par un expert-comptable DZ avant passage `production`.

## 1. Contexte de la recette

| Champ | Valeur |
|---|---|
| Environnement | ☐ Staging · ☐ Prod pilote · ☐ Local |
| Entreprise pilote | (nom, pays DZ, devise DZD) |
| Période de paie testée | (ex. 2026-08) |
| Comptable recette | (nom / rôle) |
| Expert-comptable relisant | (nom / date) |
| Référence issue | #5247 (programme 100 % — W1) |

## 2. Données pilote (à constituer)

- [ ] Employé réel : fiche complète (NIF, RC entreprise, n° CNAS employeur,
      IBAN, `salary_type=fixed`, `salary_base`, date d'embauche).
- [ ] Structure salariale DZ active.
- [ ] Pointages du mois (ou absence assumée → prorata contrat) + absences
      approuvées le cas échéant.
- [ ] (Si applicable) heures supplémentaires enregistrées via pointage.

## 3. Scénarios de recette

| # | Scénario | Résultat attendu | Réussi ? |
|---|---|---|---|
| R1 | Création du run `DZ` → calcul | `calculated`, 0 anomalie bloquante | ☐ |
| R2 | Bulletin du pilote | IRG/CNAS/net conformes à la checklist du guide (écart ≤ 0,01 DZD) | ☐ |
| R3 | Mentions légales du bulletin | NIF, RC, n° CNAS, ID.Nat, cumuls annuels présents | ☐ |
| R4 | Validation RH → verrouillage | `validated` puis `locked`, audit horodaté | ☐ |
| R5 | Téléchargement PDF (portail employé + comptable) | PDF valide, un bulletin par page | ☐ |
| R6 | Virement `ccp_dz` (ou `cpa_dz`) | Fichier généré, **somme = somme des nets**, IBAN réels | ☐ |
| R7 | Déclaration CNAS trimestrielle | CSV par employé, montants 9 %/26 % exacts | ☐ |
| R8 | Export comptable (journal) | Une ligne par bulletin + totaux | ☐ |
| R9 | (Optionnel) Régularisation | Run `regularization`, bulletin marqué | ☐ |
| R10 | (Optionnel) Fin de contrat | Préavis + indemnité + solde de tout compte | ☐ |

## 4. Journal de validation

| Date | Scénario | Résultat (chiffres clés) | Écart / remarque | Validé par |
|---|---|---|---|---|
|  | R1–R2 | net = …, IRG = … |  |  |
|  | R3–R5 |  |  |  |
|  | R6–R8 | virement = … DZD |  |  |
|  | R9–R10 |  |  |  |

## 5. Critères d'acceptation (DoD #5247)

- [ ] Un pilote DZ produit bulletin + virement **sans assistance** (R1→R6).
- [ ] Bulletins conformes aux mentions légales (R3) et à la checklist du guide.
- [ ] Docs publiées + CHANGELOG (`docs/payroll/dz/` — issue #5247).
- [ ] Écarts identifiés remontés en issues (E1-E6 — spec #5240).
- [ ] Revue expert-comptable DZ consignée (→ passage `production`).

## 6. Journal pilote

Le suivi hebdomadaire des pilotes DZ se fait dans les carnets dédiés :
📓 `Carnet pilote 1/2/3 (DZ) — feedback hebdo` (#5186/#5187/#5188).
