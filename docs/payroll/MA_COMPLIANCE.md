# 🇲🇦 Référentiel de conformité paie — Maroc (MA)

> Issue #1875 — Fiche pays obligatoire (playbook
> `docs/specifications/PAYS_ONBOARDING_PLAYBOOK.md`).
> ⚠️ **À valider par un expert-comptable local avant passage à « production »**
> (issue #1904). Statut runtime : `pilot`.

## Statut

| Règle | État | Référence | Validité |
|---|---|---|---|
| Barème IR | ✅ implémentée (`pilot`) | CGI Maroc art. 59-73 (barème annuel 2024) | à confirmer |
| Abattement frais professionnels | ✅ implémentée (issue #2260) | CGI Maroc art. 58 | à confirmer |
| Cotisations CNSS | ✅ implémentée (`pilot`) | CNSS (art. 17 loi 14-94) | à confirmer |
| Cotisation AMO | ✅ implémentée (`pilot`) | Loi 65-00 | à confirmer |
| SMIG | ✅ 3 111 MAD/mois | Décret SMIG secteur non agricole | à confirmer |
| Heures supplémentaires | ⏳ non implémentées | CGI art. 58 (majorations) | — |
| Fériés / calendrier | 🔴 placeholder | aucun calendrier officiel câblé | — |
| Fin de contrat (préavis, indemnités) | ⏳ défaut générique | Code du travail marocain art. 52-65 | — |

## 1. Barème IR / IRPP

Barème **annuel** (CGI Maroc) — bornes inclusives (helper progressif
`AbstractCountryRules`) :

| Tranche annuelle (MAD) | Taux | Déduction forfaitaire |
|---|---|---|
| 0 – 30 000 | 0 % | 0 |
| 30 001 – 50 000 | 10 % | 3 000 |
| 50 001 – 60 000 | 20 % | 8 000 |
| 60 001 – 80 000 | 30 % | 14 000 |
| 80 001 – 180 000 | 34 % | 17 200 |
| 180 001 et + | 38 % | 24 400 |

**Assiette** : `brut − cotisations salariales (CNSS + AMO) − abattement frais professionnels`.

**Abattement frais professionnels (CGI art. 58 — issue #2260)** :
35 % du **revenu brut annuel**, avec **plancher 2 500 MAD** et **plafond
30 000 MAD par an**, appliqué **AVANT** le barème (méthode dédiée
`MoroccoPayrollRules::moroccoProfessionalExpensesAbatement()`).

> Historique : avant #2260, l'IR annuel était calculé directement sur
> `(brut − cotisations) × 12` sans abattement → **sur-imposition de tous les
> salariés marocains** (ex. SMIG 3 111 MAD : IR 40,13 MAD/mois sans
> abattement → 0,00 avec abattement). Golden tests : `GoldenMaPayrollTest`
> (SMIG, cadre 10 000, haut salaire 50 000 — valeurs calculées à la main).

## 2. Cotisations sociales

| Cotisation | Taux | Type | Plafond |
|---|---|---|---|
| CNSS (code `CNSS_EMP`) | 4,48 % | salarié | 6 000 MAD/mois |
| CNSS (code `CNSS_PAT`) | 8,98 % | employeur | 6 000 MAD/mois |
| AMO (code `AMO_EMP`) | 2,26 % | salarié | non plafonné |
| AMO (code `AMO_PAT`) | 4,11 % | employeur | non plafonné |

## 3. SMIG / salaire minimum

3 111 MAD/mois (secteur non agricole, 2024) — `MoroccoPayrollRules::minimumWage()`.

## 4. Heures supplémentaires

Non implémentées (défaut du moteur). À compléter (majorations CGI art. 58).

## 5. Fériés / calendrier

🔴 Placeholder — aucun calendrier officiel marocain câblé
(`publicHolidaysSource()` : « placeholder »). À traiter (issue #2255).

## 6. Fin de contrat

Préavis défaut générique (30 j) — à spécialiser selon Code du travail
marocain (art. 52-65) et catégorie.

## 7. Arrondis

Impôt calculé sur l'assiette non arrondie ; montants exposés arrondis à 2
décimales (demi au plus proche) — `docs/payroll/CALCULATION_CONTRACT.md`.

## 8. Niveau de confiance et avertissement

`pilot` — validation experte requise avant `production` (issue #1904,
registre `VALIDATION_EXPERTE.md`).
