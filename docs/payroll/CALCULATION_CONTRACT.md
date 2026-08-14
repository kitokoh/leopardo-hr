# Contrat de calcul paie — simulation et bulletin (issue #1869)

> Référentiel : `docs/payroll/CALCULATION_CONTRACT.md` — Issue **[MULTI-PAYS][P1] #1869**
> Statut : actif depuis 2026-08-14.

## Objectif

La simulation (`POST /api/v1/cotisation-simulation`) et le bulletin réel
(`PayrollCalculator::calculateSlip()`) doivent produire **exactement les mêmes
montants** pour un même brut et un même contexte de règles pays, et la réponse
doit être **complète et explicable** : chaque composante du net est visible et
référencée.

## Noyau de calcul commun

Le point d'entrée unique est `PayrollCalculator::computeNetBreakdown(float $grossEarnings, CountryRulesInterface $rules)` :

| Étape | Appel métier | Sortie |
|---|---|---|
| 1 | `$rules->calculateSocialCharges($brut)` | cotisations salariales + patronales |
| 2 | `assiette = brut − cotisations salariales` | `taxable_gross` (non arrondi) |
| 3 | `$rules->calculateIncomeTax($assiette)` | `income_tax` |
| 4 | `$rules->calculateBracketTax($brut)` | `bracket_tax` (TRIMF SN / minimum fiscal, 0 si non applicable) |
| 5 | `retenues = salariales + impôt + bracket_tax` | `base_deductions` |
| 6 | `net = max(0, brut − retenues)` | `net_salary` |
| 7 | `coût = brut + patronales` | `total_cost` |

Le bulletin ajoute ensuite les **composants de déduction personnalisés**
(salary components) par-dessus `base_deductions` ; la simulation (brut simple,
sans composants) utilise `base_deductions` tel quel. Pour un cas sans
composants, les deux chemins retournent rigoureusement le même net.

## Politique d'arrondi (uniforme)

1. **L'impôt est calculé sur l'assiette non arrondie** (`brut − cotisations
   salariales`), exactement comme sur le bulletin — jamais sur une valeur déjà
   arrondie (au risque d'un décalage de centime simulation vs bulletin).
2. **Seuls les champs exposés** (`taxable_gross`, `total_deductions`,
   `net_salary`, `total_cost_employer`, montants de cotisations) sont arrondis
   à **2 décimales** (demi au plus proche, `round()` PHP).
3. **Plancher à 0** : `net_salary = max(0, brut − retenues)` — un brut nul ou
   des retenues supérieures au brut ne produisent jamais un net négatif.
4. `income_tax` et `bracket_tax` sont eux-mêmes arrondis à 2 décimales par les
   règles pays (`round(..., 2)` dans chaque `calculateIncomeTax`).

## Contrat de réponse — `POST /api/v1/cotisation-simulation`

```jsonc
{
  "data": {
    // Contexte (issue #1869)
    "country_code": "DZ",            // pays demandé = pays des règles appliquées
    "currency": "DZD",               // devise des règles pays (CountryDefaults)
    "rules_identifier": "AlgeriaPayrollRules", // classe de règles appliquée
    "rules_as_of": "2026-08-14",     // date d'effet des règles résolues
    "confidence_level": "production",// production | pilot | placeholder
    "rounding_policy": "…",          // politique d'arrondi (résumé)

    // Entrée
    "gross_salary": 60000.0,

    // Cotisations
    "employee_contributions": [ { "name": "…", "code": "CNAS_EMP", "rate": 9.0, "cap": null, "amount": 5400.0 } ],
    "employer_contributions": [ … ],
    "total_employee_deduction": 5400.0,   // = Σ cotisations salariales
    "total_employer_cost": 15600.0,       // = Σ cotisations patronales (clé legacy conservée)

    // Impôt et net — identiques au bulletin
    "taxable_gross": 54600.0,             // brut − cotisations salariales (affiché arrondi)
    "income_tax": 7042.0,
    "bracket_tax": 0.0,                   // TRIMF / minimum fiscal (0 si non applicable)
    "total_deductions": 12442.0,          // = salariales + income_tax + bracket_tax
    "net_before_tax": 54600.0,            // clé rétro-compatible (brut − salariales, sans impôt)
    "net_salary": 47558.0,                // = max(0, brut − total_deductions)
    "total_cost_employer": 75600.0        // = brut + cotisations patronales
  }
}
```

### Rétro-compatibilité

- `net_before_tax` et `total_employer_cost`/`total_cost_employer` sont
  conservés (clients existants) ; leur sens est documenté ci-dessus.
- `income_tax` et `net_salary` sont **toujours présents** (0 inclus) — jamais
  absents ni `null`.

## Invariants vérifiés par les tests

- `net_salary === max(0, gross_salary − total_deductions)` (à 2 décimales).
- `total_deductions === total_employee_deduction + income_tax + bracket_tax`.
- `total_cost_employer === gross_salary + total_employer_cost`.
- `income_tax(simulation) === income_tax(bulletin)` pour un même brut et un
  même contexte (test `test_contract_simulation_matches_payslip_for_same_case`).
- Chaque champ monétaire a ≤ 2 décimales.
- Pays inconnu → **422** `UNSUPPORTED_COUNTRY_RULES` avec message métier
  explicite (aucun fallback silencieux vers une autre juridiction).

## Erreurs

| Cas | Réponse |
|---|---|
| `gross_salary` manquant / négatif | 422 validation |
| `country_code` hors liste supportée | 422 validation |
| Règle pays absente du résolveur | 422 `UNSUPPORTED_COUNTRY_RULES` (« Aucune règle de paie enregistrée pour le pays… ») |
| Non manager | 403 |

## Périmètre

Ce contrat couvre le **noyau brut → net** (cotisations, impôt, net, coût
employeur). Il ne couvre pas (hors scope) : prorata jours ouvrés, heures
supplémentaires, congés, 13ème mois, allocations familiales, composants
déduction personnalisés — ces éléments restent propres au bulletin réel et
sont documentés dans les référentiels pays (`DZ_COMPLIANCE.md`, etc.).
