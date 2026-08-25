# 🇺🇸 Référentiel de conformité paie — États-Unis (US)

> Pack EN 100 % (#5255) — audit légal 2026-08-24. ⚠️ À valider par un expert-comptable local (US payroll provider) avant passage à « production » (issue #1904). Niveau courant : `pilot` — **fédéral uniquement** : federal income tax (single), FICA (SS + Medicare + Additional Medicare), FUTA. **Impôt d'État non modélisé** (voir §6).

## Statut

| Règle | État | Référence | Validité |
|---|---|---|---|
| Federal income tax (single) | ✅ implémentée (pilot) | IRS Rev. Proc. 2025-32 (OBBBA) | vérifié le 2026-08-24 |
| FICA (SS/Medicare) | ✅ implémentée (pilot) | IRS Topic 751 (wage base 2026) | vérifié le 2026-08-24 |
| FUTA | ✅ implémentée (pilot, taux effectif) | IRS (crédit max 5,4 pts) | vérifié le 2026-08-24 |
| Salaire minimum | ✅ $7,25/h (fédéral) | FLSA | vérifié le 2026-08-24 |
| Heures supplémentaires | ✅ 40 h/semaine, 1,5× | FLSA | vérifié le 2026-08-24 |
| Fériés / calendrier | ✅ 11 federal holidays (OPM) | PA2-COUNTRY-012 | à confirmer |
| Fin de contrat | ✅ at-will (aucun préavis/indemnité) | doctrine at-will (WARN hors moteur) | à confirmer |

## 1. Barème federal income tax 2026 (single, annuel, / 12)

| Tranche annuelle (revenu imposable) | Taux |
|---|---|
| $0 – $12 400 | 10 % |
| $12 401 – $50 400 | 12 % |
| $50 401 – $105 700 | 22 % |
| $105 701 – $201 775 | 24 % |
| $201 776 – $256 225 | 32 % |
| $256 226 – $640 600 | 35 % |
| > $640 600 | 37 % |

Assiette : (brut annuel − cotisations salariales) − **standard deduction $16 100** (single, 2026 — OBBBA + indexation). Autres statuts (MFJ $32 200, HoH $24 150) non modélisés. Les bornes 10 %/12 % bénéficient de la sur-indexation OBBBA (4 %) par rapport aux tranches supérieures (2,3 %).

## 2. Cotisations sociales — FICA + FUTA (2026)

| Cotisation | Taux | Type | Plafond |
|---|---|---|---|
| Social Security (FICA) | 6,2 % / 6,2 % | salarié / employeur | wage base $184 500/an ($15 375/mois) |
| Medicare (FICA) | 1,45 % / 1,45 % | salarié / employeur | non plafonné |
| Additional Medicare | 0,9 % | salarié seul | au-delà de $200 000/an (single) |
| FUTA | 6,0 % nominal → **0,6 % effectif** | employeur seul | premiers $7 000/an ($583,33/mois) |

Codes : `SS_US_EMP`/`SS_US_PAT` (6,2 %), `MED_US_EMP`/`MED_US_PAT` (1,45 %), `ADD_MED_US_EMP` (0,9 %, seuil $200 000/an), `FUTA_US_PAT` (6,0 % nominal, calcul au taux effectif 0,6 %).

Note : le taux FUTA nominal est de 6,0 % ; le crédit maximal de 5,4 points (état à jour) ramène le taux effectif à **0,6 %** — c'est ce taux effectif qui est appliqué (documenté dans la classe).

## 3. Salaire minimum

**$7,25/h** (fédéral, inchangé depuis 2009 — FLSA). Équivalent mensuel (173,33 h) : **$1 256,64**. De nombreux États imposent un minimum supérieur (non modélisé — à surcharger au niveau entreprise le cas échéant).

## 4. Heures supplémentaires

**FLSA** : seuil hebdo **40 h**, majoration légale **1,5×** (heure et demie) au-delà — `overtimeRateTiers() = [1.5]`. Exempt/non-exempt (salariés exonérés) non modélisé — la majoration s'applique par défaut au-delà de 40 h.

## 5. Fériés / calendrier

11 **federal holidays** (dates fixes, OPM — New Year's Day, MLK Day, Washington's Birthday, Memorial Day, Juneteenth, Independence Day, Labor Day, Columbus Day, Veterans Day, Thanksgiving, Christmas). Les fériés d'État varient (non modélisés).

## 6. Impôt d'État (non modélisé — périmètre pilot)

- **State income tax withholding** : 41 États + DC prélèvent un impôt sur le revenu (taux et barèmes par État, ex. CA 1 %→13,3 %, TX/FL/NV sans impôt) — **non modélisé** dans `UnitedStatesPayrollRules` (périmètre fédéral). Un impôt d'État peut être saisi comme déduction/composante au niveau entreprise.
- **State unemployment (SUTA)** : hors moteur.
- **Local taxes** (NYC, etc.) : hors moteur.

## 7. Fin de contrat

**At-will employment** : aucun préavis légal (`noticePeriodDays() = 0`) et aucune indemnité de licenciement statutaire (`severanceMonthsPerYear() = 0` — le WARN Act ne couvre que les licenciements collectifs, hors moteur). Toute garantie contractuelle reste paramétrable.

## 8. Arrondis

Chaque montant mensuel arrondi à 2 décimales ; l'IR est arrondi après division par la base annuelle (12).

## 9. Niveau de confiance et avertissement

`confidenceLevel() = pilot` — valeurs sourcées IRS (Rev. Proc. 2025-32, Topic 751) mais non validées par un payroll provider certifié ; l'impôt d'État étant absent, les bulletins US ne doivent pas être utilisés pour des obligations statutaires sans validation locale. `complianceWarning()` porte l'avertissement explicite.
