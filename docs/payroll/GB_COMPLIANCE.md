# 🇬🇧 Référentiel de conformité paie — Royaume-Uni (GB)

> Pack EN 100 % (#5255) — audit légal 2026-08-24. ⚠️ À valider par un expert-comptable local (payroll provider HMRC-recognised) avant passage à « production » (issue #1904). Niveau courant : `pilot` — PAYE + National Insurance Class 1 modélisés ; pension auto-enrolment, SSP, student loans, statutory payments (SMP/SPP) NON modélisés.

## Statut

| Règle | État | Référence | Validité |
|---|---|---|---|
| Barème PAYE | ✅ implémentée (pilot) | HMRC 2026-27 (PA gelée) | vérifié le 2026-08-24 |
| National Insurance Class 1 | ✅ implémentée (pilot) | HMRC 2026-27 | vérifié le 2026-08-24 |
| Salaire minimum | ✅ NLW £12,71/h (21+) | GOV.UK (1er avril 2026) | vérifié le 2026-08-24 |
| Heures supplémentaires | ✅ seuil 48 h (pas de majoration légale) | Working Time Regulations 1998 | à confirmer |
| Fériés / calendrier | ✅ source GOV.UK (bank holidays E&W) | PA2-COUNTRY-012 | à confirmer |
| Fin de contrat | ✅ préavis ERA 1996 s.86 ; indemnité Redundancy Payments Act (approx.) | Employment Rights Act 1996 | à confirmer |

## 1. Barème PAYE (annuel, / 12)

| Tranche annuelle | Taux |
|---|---|
| 0 – £12 570 (personal allowance) | 0 % |
| £12 571 – £50 270 | 20 % |
| £50 271 – £125 140 | 40 % |
| > £125 140 | 45 % |

Assiette : brut − cotisations salariales (NI Class 1). Personal allowance portée par la tranche à 0 % (bornes inclusives du helper progressif). La PA reste gelée à £12 570 jusqu'en 2027-28 (indexation CPI ensuite).

## 2. Cotisations sociales — National Insurance Class 1 (2026-27)

| Cotisation | Taux | Type | Seuil/plafond |
|---|---|---|---|
| NI employé (main rate) | 8 % | salarié | bande PT £12 570/an (£1 047,50/mois) → UEL £50 270/an (£4 189,17/mois) |
| NI employé (higher rate) | 2 % | salarié | au-delà de l'UEL |
| NI employeur | 15 % | employeur | au-delà du ST £5 000/an (£416,67/mois) |

Codes : `NI_GB_EMP` (8 %, cap mensuel UEL), `NI_GB_EMP_HI` (2 %), `NI_GB_PAT` (15 %).

Non modélisés (documentés) :
- **Employment Allowance** £10 500/an (relief annuel par employeur — hors bulletin).
- **Pension auto-enrolment** : minimum légal 5 % sal. / 3 % pat. sur les qualifying earnings (bande £6 240–£50 270) — paramétrable en composante contractuelle.
- **Statutory Sick Pay** £118,75/semaine (2026-27) : carence 3 jours — `sickLeavePolicy()` documentée (pas d'IJ en fraction du salaire).
- **Student Loan Plan** : retenues selon plan (1/2/4) — hors moteur.
- **Statutory payments** (SMP/SPP/ShPP...) : hors moteur.

## 3. Salaire minimum

National Living Wage 2026-27 : **£12,71/h** pour les 21+ (GOV.UK, 1er avril 2026 — recommandation Low Pay Commission). Équivalent mensuel temps plein (173,33 h) : **£2 203,02 → 2 203,00**. Autres tranches d'âge (18-20 : £10,85 ; < 18/apprentis : £8,00) non modélisées.

## 4. Heures supplémentaires

**Working Time Regulations 1998** : plafond légal de **48 h/semaine** (opt-out individuel possible). Le Royaume-Uni n'impose **aucune majoration légale** des HS — les taux sont contractuels → `overtimeRateTiers()` vide (le moteur n'injecte aucune majoration par défaut).

## 5. Fériés / calendrier

England & Wales : 8 bank holidays par an (dates fixes et mobiles publiées par GOV.UK) — source `publicHolidaysSource()`. Écosse et Irlande du Nord ont leurs propres calendriers (non modélisés).

## 6. Fin de contrat

- **Préavis** (Employment Rights Act 1996 s.86) : 1 semaine par année complète d'ancienneté, plafond **12 semaines**, exigible après 1 mois d'emploi → `noticePeriodDays()`.
- **Indemnité de licenciement économique** (Redundancy Payments Act 1996) : 0,5 semaine (< 22 ans), 1 semaine (22-40), 1,5 semaine (41+), plafond 20 années et plafond hebdomadaire (~£700). Approximation pilote : **1 semaine/année ≈ 0,2309 mois** → `severanceMonthsPerYear()`.

## 7. Arrondis

Chaque montant mensuel arrondi à 2 décimales (round PHP half-away) ; l'IR est arrondi après division par la base annuelle (12).

## 8. Niveau de confiance et avertissement

`confidenceLevel() = pilot` — valeurs sourcées HMRC/GOV.UK mais non validées par un payroll provider certifié RTI/FPS ; le bulletin PDF et les exports ne doivent pas être utilisés pour des obligations statutaires sans validation locale. `complianceWarning()` porte l'avertissement explicite.
