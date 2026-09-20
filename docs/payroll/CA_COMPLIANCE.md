# 🇨🇦 Référentiel de conformité paie — Canada (CA)

> Fiche issue #2119 + **audit 2026** (pack EN #5255, 2026-08-24) + **lot BC-07 #7933** (2026-09-21 : impôt provincial QC/ON, RRQ/RRQ2 + RQAP + AE réduit Québec). ⚠️ À valider par un comptable local avant passage à « production » (issue #1904). Niveau courant : `pilot` — fédéral complet + provincial **QC et ON uniquement** (autres provinces : fédéral-seul documenté, §6).

## Statut

| Règle | État | Référence | Validité |
|---|---|---|---|
| Barème IR fédéral | ✅ implémentée (pilot) | LIR — CRA 2026 (indexation 2 % + taux 14 %) | vérifié le 2026-08-24 |
| Basic Personal Amount | ✅ implémentée (pilot) | CRA 2026 ($16 452, phase-out) | vérifié le 2026-08-24 |
| CPP / CPP2 | ✅ implémentée (pilot) | CRA 2026 (YMPE $74 600, YAMPE $85 000) | vérifié le 2026-08-24 |
| Assurance-emploi | ✅ implémentée (pilot) | CRA 2026 (1,63 %, MIE $68 900) | vérifié le 2026-08-24 |
| **Impôt provincial QC** | ✅ implémentée (pilot, #7933) | Revenu Québec 2026 + ministère des Finances QC (paramètres 2026, nov. 2025) | vérifié le 2026-09-21 |
| **Impôt provincial ON (+ surtaxe)** | ✅ implémentée (pilot, #7933) | Taxation Act, 2007 (ON) — paramètres indexés 2026 | vérifié le 2026-09-21 |
| **RRQ/QPP + RRQ2 (employé QC)** | ✅ implémentée (pilot, #7933) | Retraite Québec / RAMQ 2026 (6,30 %, max $4 479,30) | vérifié le 2026-09-21 |
| **RQAP (employé QC)** | ✅ implémentée (pilot, #7933) | Revenu Québec 2026 (0,430 % / 0,602 %, MRA $103 000) | vérifié le 2026-09-21 |
| **AE taux réduit QC** | ✅ implémentée (pilot, #7933) | CEIC/EDSC 2026 (1,30 %, réduction RQAP 0,33 pt) | vérifié le 2026-09-21 |
| **Abattement du Québec (16,5 %)** | ✅ implémentée (pilot, #7933) | T4032-QC 2026 (« reste au taux de 16,5 % pour 2026 ») | vérifié le 2026-09-21 |
| Salaire minimum | ✅ $18,15/h (fédéral) | Canada.ca (1er avril 2026) | vérifié le 2026-08-24 |
| Heures supplémentaires | ✅ seuil 40-48 h selon province, 1,5× | Code canadien du travail / provinces | à confirmer |
| Fériés / calendrier | ✅ fériés fédéraux | PA2-COUNTRY-012 | à confirmer |
| Fin de contrat | ✅ préavis CLC art. 230 ; indemnité approx. provinciale | Code canadien du travail | à confirmer |

## 1. Barème IR fédéral 2026 (annuel, / 12)

| Tranche annuelle | Taux |
|---|---|
| $0 – $58 523 | **14 %** (taux réduit de 15 % → 14 % au 1er juillet 2025, plein effet 2026) |
| $58 524 – $117 045 | 20,5 % |
| $117 046 – $181 440 | 26 % |
| $181 441 – $258 482 | 29 % |
| > $258 483 | 33 % |

Assiette : brut − CPP/CPP2/EI salariales. **Basic Personal Amount 2026** appliqué en crédit non remboursable : $16 452 × 14 % ($2 303,28) pour un revenu ≤ $181 440 ; élimination progressive linéaire entre $181 440 et $258 482 jusqu'au BPA plancher $14 829 (crédit $2 076,06).

## 1bis. Impôt provincial 2026 — QC et ON (#7933)

**Résolution de province EXPLICITE** : `forProvince('QC'|'ON'|…)` (codes ISO 3166-2:CA) ou override entreprise `company.metadata.payroll_ca_province` (lu par `forCompany()`). Code inconnu → `InvalidArgumentException` (aucun fallback silencieux). Sans province : **fédéral-seul** (comportement historique documenté). La province participe à `rulesVersion()` (suffixe `-qc`/`-on`/`-federal`).

### Québec (source : Revenu Québec « Income Tax Rates » 2026 ; ministère des Finances QC, « Parameters of the Personal Income Tax System for 2026 », nov. 2025)

| Tranche annuelle | Taux |
|---|---|
| $0 – $54 345 | 14 % |
| $54 346 – $108 680 | 19 % |
| $108 681 – $132 245 | 24 % |
| > $132 245 | 25,75 % |

Crédit personnel de base QC : **$18 952 × 14 % = $2 653,28**. **Abattement du Québec** : l'impôt fédéral de base (après crédit BPA) est réduit de **16,5 %** (Loi sur les arrangements fiscaux ; T4032-QC 2026 : « l'abattement de l'impôt du Québec reste au taux de 16,5 % pour 2026 »).

### Ontario (source : Taxation Act, 2007 (ON), paramètres indexés 2026 — facteur 1,019, tranches $150k/$220k non indexées)

| Tranche annuelle | Taux |
|---|---|
| $0 – $53 891 | 5,05 % |
| $53 892 – $107 785 | 9,15 % |
| $107 786 – $150 000 | 11,16 % |
| $150 001 – $220 000 | 12,16 % |
| > $220 000 | 13,16 % |

Crédit personnel de base ON : **$12 989 × 5,05 % = $655,94**. **Surtaxe ON 2026** : 20 % de l'impôt ontarien de base au-delà de **$5 818** + 36 % au-delà de **$7 446**. La **contribution-santé de l'Ontario (Ontario Health Premium)** n'est **pas** modélisée (pilot).

**Simplification pilote assumée** : l'assiette provinciale = assiette fédérale (brut − cotisations salariales). Les déductions spécifiques QC (déduction pour emploi, déduction RRQ supplémentaire…) et les crédits provinciaux au-delà du montant personnel de base ne sont pas modélisés.

## 2. Cotisations sociales — CPP/CPP2/EI (2026)

| Cotisation | Taux | Type | Plafond |
|---|---|---|---|
| RPC/CPP | 5,95 % / 5,95 % | salarié / employeur | YMPE $74 600/an ($6 216,67/mois), exemption $3 500/an |
| RPC2/CPP2 | 4 % / 4 % | salarié / employeur | tranche YMPE → YAMPE $85 000/an ($7 083,33/mois) |
| Assurance-emploi | 1,63 % / 2,282 % (1,4×) | salarié / employeur | MIE $68 900/an ($5 741,67/mois) |

Codes : `CPP_CA_EMP`/`CPP_CA_PAT` (5,95 %), `CPP2_CA_EMP`/`CPP2_CA_PAT` (4 %), `EI_CA_EMP` (1,63 %), `EI_CA_PAT` (2,282 %). Maximums annuels 2026 : CPP $4 230,45 + CPP2 $416 = $4 646,45 ; EI salarié $1 123,07 / patron $1 572,30.

## 2bis. Cotisations d'un employé QUÉBÉCOIS — RRQ/RRQ2, AE réduit, RQAP (2026, #7933)

Pour un employé scopé `QC`, les cotisations REMPLACENT le bloc fédéral :

| Cotisation | Taux | Type | Plafond |
|---|---|---|---|
| RRQ/QPP (base 5,3 % + supplémentaire 1 %) | **6,30 %** / 6,30 % | salarié / employeur | YMPE $74 600/an ($6 216,67/mois), exemption $3 500/an — max annuel $4 479,30 |
| RRQ2/QPP2 | 4 % / 4 % | salarié / employeur | tranche YMPE → YAMPE $85 000/an |
| Assurance-emploi (taux RÉDUIT QC) | **1,30 %** / **1,82 %** (1,4×) | salarié / employeur | MIE $68 900/an — max salarié $895,70 |
| RQAP | **0,430 %** / **0,602 %** | salarié / employeur | MRA $103 000/an ($8 583,33/mois) — max salarié $442,90 |

Sources (vérifiées le 2026-09-21) : **Retraite Québec** — taux 2026 RRQ 6,30 % (le taux de base passe de 5,4 % à 5,3 % en 2026, annonce mise à jour économique QC automne 2025 ; cotisation maximale $4 479,30), MPE $74 600 / MSGA $85 000 ; **Revenu Québec** — RQAP 2026 : salarié 0,494 % → **0,430 %**, employeur 0,692 % → **0,602 %**, MRA $98 000 → **$103 000** ; **CEIC/EDSC** (rapport actuariel AE 2026) — taux AE 2026 $1,63/$100 hors QC, **réduction RQAP 33 ¢** → taux QC **$1,30** (patronal $1,82, max $1 253,98).

Codes : `QPP_CA_EMP`/`QPP_CA_PAT`, `QPP2_CA_EMP`/`QPP2_CA_PAT`, `EI_QC_EMP`/`EI_QC_PAT`, `QPIP_QC_EMP`/`QPIP_QC_PAT`.

## 3. Salaire minimum

**$18,15/h** (fédéral, 1er avril 2026 — +2,1 % CPI). Équivalent mensuel (173,33 h) : **$3 145,94 → 3 146,00**. Les provinces ont leurs propres minimums (souvent plus élevés : BC/ON ~$17,60-17,85+) — non modélisés, à surcharger au niveau entreprise.

## 4. Heures supplémentaires

Seuil hebdo **statutaire par province** (`overtimeThresholdWeeklyHours()`) : 40 h (BC, MB, NL, QC, NT, NU, SK, YT), 44 h (fédéral, AB, NB, ON), 48 h (NS, PE). Majoration **1,5×** au-delà du seuil (toutes provinces).

## 5. Fériés / calendrier

Fériés fédéraux : 1er jan, Good Friday, Victoria Day, 1er juil, Labour Day, Thanksgiving, 11 nov, 25 déc (+ fériés provinciaux à saisir manuellement).

## 6. Impôt provincial — périmètre modélisé et limites (#7933)

L'impôt provincial est modélisé pour **QC et ON uniquement** (§1bis). Les autres provinces/territoires reconnus (`PROVINCE_CODES`) restent **fédéral-seul** : c'est un comportement DOCUMENTÉ (pas un fallback silencieux — un code inconnu lève une exception, et `complianceWarning()` acte la limite). Un impôt provincial hors QC/ON peut être saisi comme déduction au niveau entreprise. Non modélisés : Ontario Health Premium, crédits fédéraux/provinciaux au-delà du montant personnel de base (âge, conjoint, handicap…), déductions spécifiques QC.

## 7. Fin de contrat

- **Préavis** (Code canadien du travail art. 230, fédéral) : 1 semaine après 3 mois, 2 après 1 an, puis +1 semaine par année jusqu'à **8 semaines** (≥ 8 ans) → `noticePeriodDays()`. Les provinces ont leurs propres régimes (non modélisés).
- **Indemnité de départ** : provinciale (ex. Ontario ESA — 1 semaine par année plafonnée 8). Approximation pilote : **1 semaine/année ≈ 0,2309 mois** → `severanceMonthsPerYear()`.

## 8. Arrondis

Chaque montant mensuel arrondi à 2 décimales ; l'IR (fédéral + provincial) est arrondi après division par la base annuelle (12) ; les crédits BPA/provinciaux et l'abattement du Québec sont appliqués avant la division (politique `CALCULATION_CONTRACT.md`).

## 9. Niveau de confiance et avertissement

`confidenceLevel() = pilot` — valeurs sourcées CRA/Canada.ca/Revenu Québec/Retraite Québec 2026 mais non validées par un payroll provider certifié (PDT/T4/RL-1) ; l'assiette provinciale simplifiée (§1bis) et l'absence de l'Ontario Health Premium font que les bulletins CA ne doivent pas être utilisés pour des obligations statutaires sans validation locale. `complianceWarning()` porte l'avertissement explicite. Golden tests : `GoldenCaPayrollTest` (fédéral, 10 cas) + `GoldenCaProvincialPayrollTest` (#7933 — 8 cas calculés à la main : QC 2 000/6 000/9 000/15 000, ON 2 000/6 000/9 000/15 000, + exception province inconnue + override entreprise).
