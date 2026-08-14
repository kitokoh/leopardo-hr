# 🇨🇮 Référentiel de conformité paie — Côte d'Ivoire (CI)

> **Programme CEDEAO/UEMOA (issue #1825)** — Référentiel légal versionné du moteur de paie ivoirien.
> ⚠️ **Statut : PILOT** — valeurs implémentées depuis sources publiques (CGI 2024, Code du travail), **à valider par expert-comptable OHADA-CI avant passage en production**.
> Sources : Code général des impôts (CGI) ivoirien 2024 (art. 116-120), Code du travail ivoirien (loi 2015-532, art. 18/21), CNPS Côte d'Ivoire.

## Statut

| Règle | État | Référence | Validité |
|---|---|---|---|
| ITSAS (barème annuel 5 tranches) | ✅ implémentée (pilot) | CGI CI art. 116-120 | à valider |
| Abattement frais pro (20 %, non plafonné) | ✅ implémentée (pilot) | CGI CI | à valider |
| CN (Contribution Nationale 1,5 % > 50 000 XOF) | ✅ implémentée (pilot) | CGI CI | à valider |
| CNSS retraite salarié 3,2 % (plaf. 1 647 315) | ✅ implémentée (pilot) | CNSS CI | à valider |
| CNSS retraite patronal 4,5 % (plaf. 1 647 315) | ✅ implémentée (pilot) | CNSS CI | à valider |
| CNSS famille patronal 5,75 % (plaf. 1 647 315) | ✅ implémentée (pilot) | CNSS CI | à valider |
| CNSS AT patronal 2,0 % (non plafonné, pilote) | ✅ implémentée (pilot) | CNSS CI | à valider |
| SMIG (75 000 XOF/mois) | ✅ implémentée (pilot) | — | à vérifier 2024 |
| 13ème mois (conventions de branche) | ✅ implémentée (pilot) | conventions OHADA-CI | à valider |
| Préavis (matrice catégorie × ancienneté) | ✅ implémentée (pilot, palier ancienneté) | Code du travail art. 18 | à valider |
| Heures sup (+15 %/8 h, +35 %/14 h, +50 % au-delà) | ✅ implémentée (pilot) | Code du travail art. 21 | à valider |
| Congés (2,2 j/mois, +0,2 j/5 ans) | 📝 à documenter/test | Code du travail art. 25.1 | — |
| Jours fériés fixes | ✅ documentés (voir §7) | — | — |
| Jours fériés islamiques mobiles | 📝 wiring calendrier islamique à venir | — | — |

## 1. ITSAS + CN — Impôts sur les traitements et salaires

**Barème annuel ITSAS** (implémenté dans `CedeaoPayrollRules::defaultTaxSlabs()` pour CI, CGI CI art. 116-120) :

| Tranche annuelle (XOF) | Taux |
|---|---|
| 0 – 600 000 | 0 % |
| 600 001 – 2 000 000 | 2 % |
| 2 000 001 – 5 000 000 | 21 % |
| 5 000 001 – 10 000 000 | 24,5 % |
| > 10 000 000 | 29 % |

**Assiette ITSAS** : `brut − CNSS salariale − abattement frais professionnels`.
**Abattement frais professionnels** : 20 % du brut, **non plafonné**.
*Note d'implémentation (pilot)* : le moteur passe `brut − CNSS salariale` à `calculateIncomeTax()` ; l'abattement est appliqué sur cette base (≈ 19,4 % du brut au lieu de 20 %). Écart ± 0,6 point à valider par l'expert-comptable.

**CN — Contribution Nationale** : `max(0, brut mensuel − 50 000) × 1,5 %` (seuil annuel 600 000 XOF). Calculée séparément (`CedeaoPayrollRules::calculateBracketTax()`), affichée sur le bulletin sous la ligne « Contribution Nationale (CN) ».

**Impôt total mensuel = ITSAS mensuel + CN mensuelle.**

**Exemple** (brut 200 000 XOF/mois, CNSS salariale 6 400) :
- Base après CNSS : 193 600 · Abattement : 38 720 · Assiette mensuelle : 154 880 · Annuelle : 1 858 560
- ITSAS annuel : 1 258 560 × 2 % = 25 171,20 → **ITSAS mensuel : 2 097,60 XOF**
- CN = 150 000 × 1,5 % = **2 250,00 XOF**
- **Impôt total mensuel : 4 347,60 XOF**

## 2. CNSS — Cotisations sociales

| Cotisation | Taux | Plafond mensuel |
|---|---|---|
| Retraite salarié | 3,2 % | 1 647 315 XOF |
| Retraite patronal | 4,5 % | 1 647 315 XOF |
| Famille patronal | 5,75 % | 1 647 315 XOF |
| AT patronal | 2,0 % (pilote) | non plafonné |

Codes `social_contributions` : `CNSS_CI_RET_EMP`, `CNSS_CI_RET_PAT`, `CNSS_CI_FAM_PAT`, `CNSS_CI_AT_PAT`.
Assiette plafonnée : `min(brut, 1 647 315)` pour retraite et famille ; AT sur le brut complet.

**Exemple** (brut 2 000 000 XOF) : salariale = 1 647 315 × 3,2 % = **52 714,08** ; patronale = 74 129,18 + 94 720,61 + 40 000,00 = **208 849,79 XOF**.

## 3. SMIG

75 000 XOF/mois — **à vérifier** (SMIG ivoirien 2024).

## 4. Heures supplémentaires (Code du travail art. 21)

| Plage hebdomadaire | Majoration |
|---|---|
| 40 – 48 h (8 premières h HS) | +15 % |
| 48 – 54 h (h HS 9 à 14) | +35 % |
| > 54 h / nuit / dimanche | +50 % |

Implémenté : `CedeaoPayrollRules::overtimeRateTiers()` — 1,15 / 1,35 / 1,50.

## 5. 13ème mois

Pratique généralisée via les conventions de branche (obligatoire dans la plupart des branches, convention OHADA-CI) → `thirteenthMonthMandatory() = true` pour CI. Versé en décembre, entièrement imposable (ligne « 13ème mois » incluse dans le brut taxable).

## 6. Préavis (Code du travail art. 18)

| Catégorie | < 5 ans | ≥ 5 ans |
|---|---|---|
| Ouvriers | 8 jours | 15 jours |
| Employés / Techniciens | 1 mois (30 j) | 2 mois (60 j) |
| Cadres | 3 mois (90 j) | 3 mois (90 j) |

*Note d'implémentation (pilot)* : le moteur ne transmet pas la catégorie à `noticePeriodDays()` → approximation implémentée sur l'ancienneté seule (30 j < 5 ans, 60 j 5-10 ans, 90 j > 10 ans — palier employé/technicien). La matrice complète ci-dessus est la cible ; l'évolution d'interface (paramètre catégorie) est requise pour la distinguer.

## 7. Jours fériés fixes CI

1er janvier · lundi de Pâques · 1er mai · Ascension · lundi de Pentecôte · 7 août (Fête de l'indépendance) · 15 août (Assomption) · 1er novembre (Toussaint) · 15 novembre (Fête de la paix) · 25 décembre (Noël) + fêtes islamiques mobiles (Aïd el-Fitr, Aïd el-Adha, Maouloud — câblage table `islamic_calendar` à venir).

## 8. À valider par expert-comptable OHADA-CI

- [ ] Barème ITSAS 2024 (5 tranches annuelles)
- [ ] Abattement frais pro 20 % non plafonné + base de calcul (brut vs après CNSS)
- [ ] CN 1,5 % sur la part du brut > 50 000 XOF
- [ ] Taux CNSS 2024 (retraite 3,2/4,5, famille 5,75, AT 2,0) + plafond 1 647 315
- [ ] SMIG 2024
- [ ] Préavis art. 18 (matrice catégorie × ancienneté)
- [ ] 13ème mois (champ d'application des conventions de branche)
- [ ] Congés 2,2 j/mois (non implémentés — hors périmètre #1825)

## Procédure de mise à jour

Toute évolution des taux (loi de finances, arrêté CNSS) :
1. Mettre à jour `CedeaoPayrollRules` (defaults CI) ;
2. Mettre à jour le seeder `PayrollCountryConfigSeeder` (rows `social_contributions`/`tax_slabs` avec `effective_from`) ;
3. Mettre à jour ce référentiel (statut + validité) ;
4. Passer `confidenceLevel()` de `pilot` → `production` après validation expert.
