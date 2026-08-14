# Fiche de validation experte — <PAYS> (<CODE ISO>)

> Template — copier vers `docs/payroll/<PAYS>_VALIDATION.md` (issue #1904).
> À remplir par l'agent, à signer par un expert-comptable local.

## Périmètre

- **Règles** : `...PayrollRules` (`api/app/Modules/Payroll/Infrastructure/Services/CountryRules/`)
- **Référentiel** : `docs/payroll/<PAYS>_COMPLIANCE.md`
- **Ticket** : issue #... (validation experte)
- **Date de vérification** : YYYY-MM-DD
- **Validateur** : _nom / cabinet_ (à remplir par l'expert)

## 1. Valeurs à valider

| # | Règle | Valeur implémentée | Source citée | Statut expert (✅ / ❌ / ⚠️) |
|---|---|---|---|---|
| 1 | Impôt (IR/ITS/ITSAS/IRPP) tranches | _barème complet_ | _art./décret_ | |
| 2 | Cotisation salariale A (taux + plafond) | _x % / cap_ | | |
| 3 | Cotisation patronale B (taux + plafond) | | | |
| 4 | ... | | | |
| 5 | Préavis (matrice 8 j/1 m/3 m) | | | |
| 6 | Heures supplémentaires (paliers) | | | |
| 7 | Abattement frais pro | | | |
| 8 | Arrondi / annualisation (× 12) | | | |
| 9 | Canaux de déclaration (fichiers CSV) | _périmètre déclaré_ | | |

## 2. Points bloquants / questions ouvertes

1. _…_
2. _…_

## 3. Décision

- [ ] Toutes les valeurs validées (aucun écart)
- [ ] Écarts identifiés → liste ci-dessous, à corriger en code + golden AVANT production
- [ ] `confidenceLevel()` peut passer `pilot` → `production` (date : YYYY-MM-DD)

## 4. Écarts constatés (si applicable)

| Règle | Valeur implémentée | Valeur légale | Correctif | Suivi |
|---|---|---|---|---|
| | | | | |

## 5. Signature

- **Expert-comptable** : _nom, cabinet, n° inscription, date_
- **Preuve** : _pièce jointe / référence_
