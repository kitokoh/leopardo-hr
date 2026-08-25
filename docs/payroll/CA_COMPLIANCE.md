# 🇨🇦 Référentiel de conformité paie — Canada (CA)

> Fiche issue #2119 + **audit 2026** (pack EN #5255, 2026-08-24). ⚠️ À valider par un comptable local avant passage à « production » (issue #1904). Niveau courant : `pilot` — modèle **FÉDÉRAL** (impôt provincial, minimums provinciaux et régimes provinciaux non modélisés).

## Statut

| Règle | État | Référence | Validité |
|---|---|---|---|
| Barème IR fédéral | ✅ implémentée (pilot) | LIR — CRA 2026 (indexation 2 % + taux 14 %) | vérifié le 2026-08-24 |
| Basic Personal Amount | ✅ implémentée (pilot) | CRA 2026 ($16 452, phase-out) | vérifié le 2026-08-24 |
| CPP / CPP2 | ✅ implémentée (pilot) | CRA 2026 (YMPE $74 600, YAMPE $85 000) | vérifié le 2026-08-24 |
| Assurance-emploi | ✅ implémentée (pilot) | CRA 2026 (1,63 %, MIE $68 900) | vérifié le 2026-08-24 |
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

## 2. Cotisations sociales — CPP/CPP2/EI (2026)

| Cotisation | Taux | Type | Plafond |
|---|---|---|---|
| RPC/CPP | 5,95 % / 5,95 % | salarié / employeur | YMPE $74 600/an ($6 216,67/mois), exemption $3 500/an |
| RPC2/CPP2 | 4 % / 4 % | salarié / employeur | tranche YMPE → YAMPE $85 000/an ($7 083,33/mois) |
| Assurance-emploi | 1,63 % / 2,282 % (1,4×) | salarié / employeur | MIE $68 900/an ($5 741,67/mois) |

Codes : `CPP_CA_EMP`/`CPP_CA_PAT` (5,95 %), `CPP2_CA_EMP`/`CPP2_CA_PAT` (4 %), `EI_CA_EMP` (1,63 %), `EI_CA_PAT` (2,282 %). Maximums annuels 2026 : CPP $4 230,45 + CPP2 $416 = $4 646,45 ; EI salarié $1 123,07 / patron $1 572,30.

## 3. Salaire minimum

**$18,15/h** (fédéral, 1er avril 2026 — +2,1 % CPI). Équivalent mensuel (173,33 h) : **$3 145,94 → 3 146,00**. Les provinces ont leurs propres minimums (souvent plus élevés : BC/ON ~$17,60-17,85+) — non modélisés, à surcharger au niveau entreprise.

## 4. Heures supplémentaires

Seuil hebdo **statutaire par province** (`overtimeThresholdWeeklyHours()`) : 40 h (BC, MB, NL, QC, NT, NU, SK, YT), 44 h (fédéral, AB, NB, ON), 48 h (NS, PE). Majoration **1,5×** au-delà du seuil (toutes provinces).

## 5. Fériés / calendrier

Fériés fédéraux : 1er jan, Good Friday, Victoria Day, 1er juil, Labour Day, Thanksgiving, 11 nov, 25 déc (+ fériés provinciaux à saisir manuellement).

## 6. Impôt provincial (non modélisé — périmètre pilot)

L'impôt sur le revenu **provincial** (ex. ON 5,05 %→13,16 %, QC 14 %→25,75 %) se superpose au fédéral en paie réelle — **non modélisé** dans `CanadaPayrollRules`. Un impôt provincial peut être saisi comme déduction au niveau entreprise. Les crédits fédéraux (âge, conjoint, handicap...) ne sont pas modélisés.

## 7. Fin de contrat

- **Préavis** (Code canadien du travail art. 230, fédéral) : 1 semaine après 3 mois, 2 après 1 an, puis +1 semaine par année jusqu'à **8 semaines** (≥ 8 ans) → `noticePeriodDays()`. Les provinces ont leurs propres régimes (non modélisés).
- **Indemnité de départ** : provinciale (ex. Ontario ESA — 1 semaine par année plafonnée 8). Approximation pilote : **1 semaine/année ≈ 0,2309 mois** → `severanceMonthsPerYear()`.

## 8. Arrondis

Chaque montant mensuel arrondi à 2 décimales ; l'IR est arrondi après division par la base annuelle (12) ; le crédit BPA est appliqué avant la division.

## 9. Niveau de confiance et avertissement

`confidenceLevel() = pilot` — valeurs sourcées CRA/Canada.ca 2026 mais non validées par un payroll provider certifié (PDT/T4) ; l'impôt provincial étant absent, les bulletins CA ne doivent pas être utilisés pour des obligations statutaires sans validation locale. `complianceWarning()` porte l'avertissement explicite.
