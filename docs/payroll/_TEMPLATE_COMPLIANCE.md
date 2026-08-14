# 🇽🇽 Référentiel de conformité paie — <PAYS> (<CC>)

> Issue #1875 — Template de fiche pays obligatoire (playbook
> `docs/specifications/PAYS_ONBOARDING_PLAYBOOK.md`). Chaque pays supporté par
> le moteur de paie doit fournir ce référentiel avant implémentation, et le
> maintenir à jour à chaque modification de taux (procédure golden #1938).
> ⚠️ **À valider par un expert-comptable local avant passage à « production »**
> (issue #1904).

## Statut

| Règle | État | Référence | Validité |
|---|---|---|---|
| Barème IR/IRPP | ✅/⏳/❌ implémentée (pilot/placeholder/production) | <loi/décret/art.> | à confirmer / vérifié le <date> |
| Cotisations sociales | … | … | … |
| SMIG / salaire minimum | … | … | … |
| Heures supplémentaires | … | … | … |
| Fériés / calendrier | … | … | … |
| Fin de contrat (préavis, indemnités) | … | … | … |

## 1. Barème IR / IRPP

| Tranche (préciser annuelle/mensuelle) | Taux |
|---|---|
| 0 – … | … % |

Assiette : <brut − cotisations salariales − abattement frais pro (taux, plafond)>.
Bornes **inclusives** (helper progressif `AbstractCountryRules`).

## 2. Cotisations sociales

| Cotisation | Taux | Type (salarié/employeur) | Plafond |
|---|---|---|---|
| … | … | … | plafonné/non plafonné |

Codes : `…`.

## 3. SMIG / salaire minimum

<valeur mensuelle + source>.

## 4. Heures supplémentaires

Seuil hebdo légal : <h>. Paliers : +20 % / +30 % (…).

## 5. Fériés / calendrier

<calendrier officiel ; islamique si applicable — seules les dates `confirmed`
alimentent la paie (#1930)>.

## 6. Fin de contrat

Préavis : <j selon ancienneté>. Indemnité de licenciement : <mois/année>.
Solde de tout compte : <…>.

## 7. Arrondis

<règle>.

## 8. Niveau de confiance et avertissement

- `confidenceLevel()` : `placeholder` / `pilot` / `production`
- Date de vérification : <AAAA-MM-JJ>
- Avertissement affiché : <message i18n `api/lang/*/payroll.php`>

## 9. Sources

- <texte officiel + URL>
- <vérifié le : date, par : nom/rôle>

## 10. Golden tests

`api/tests/Feature/Payroll/Golden/Golden<CC>PayrollTest.php` — cas calculés à
la main avec référence à la section ci-dessus (issue #1938).
