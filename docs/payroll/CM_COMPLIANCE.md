# 🇨🇲 Référentiel de conformité paie — Cameroun (CM)

> **Programme CEDEAO/CEMAC (issue #1821)** — Référentiel légal versionné du moteur de paie camerounais.
> ⚠️ **Statut : PILOT** — valeurs implémentées depuis sources publiques (CGI 2024, Code du travail 92/007), **à valider par expert-comptable camerounais avant passage en production**.
> Sources : Code général des impôts (CGI) 2024, Code du travail camerounais (loi 92/007 du 14/08/1992), CNPS Cameroun.

## Statut

| Règle | État | Référence | Validité |
|---|---|---|---|
| IRPP (barème annuel 4 tranches) | ✅ implémentée (pilot) | CGI 2024 art. 68 | à valider |
| Centimes additionnels (10 %) | ✅ implémentée (pilot) | CGI 2024 art. 68 | à valider |
| Abattement frais pro (30 %, plaf. 350 000 XAF/mois) | ✅ implémentée (pilot) | CGI 2024 | à valider |
| CNPS vieillesse salarié 4,2 % (plaf. 750 000) | ✅ implémentée (pilot) | CNPS | à valider |
| CNPS vieillesse patronal 4,2 % (plaf. 750 000) | ✅ implémentée (pilot) | CNPS | à valider |
| CNPS famille patronal 7,0 % (plaf. 750 000) | ✅ implémentée (pilot) | CNPS | à valider |
| CNPS AT patronal 2,0 % (non plafonné) | ✅ implémentée (pilot) | CNPS | à valider |
| SMIG (41 875 XAF/mois) | ✅ implémentée (pilot) | Décret 2014 | à vérifier 2024 |
| Préavis (15/30/60/90 j selon ancienneté) | ✅ implémentée (pilot) | Code du travail 92/007 art. 34 | à valider |
| Heures sup (+20 %/8 h, +30 % au-delà) | ✅ implémentée (pilot) | Code du travail 92/007 | à valider |
| 13ème mois | ❌ non obligatoire légalement (pratique conventionnelle) | — | — |
| Congés (1,5 j/mois 5 ans, 2 j 6-10 ans, 2,5 j > 10 ans) | 📝 à documenter/test | Code du travail 92/007 | — |
| Prime ancienneté (5 % après 2 ans, +1 %/an, plaf. 15 %) | 📝 à documenter/test | Code du travail 92/007 | — |
| Jours fériés fixes | ✅ documentés (voir §6) | — | — |
| Jours fériés islamiques mobiles | 📝 wiring calendrier islamique à venir | — | — |

## 1. IRPP — Impôt sur le revenu des personnes physiques (salaires)

**Barème annuel** (implémenté dans `CemacPayrollRules::defaultTaxSlabs()` pour CM, CGI 2024 art. 68) :

| Tranche annuelle (XAF) | Taux |
|---|---|
| 0 – 2 000 000 | 10 % |
| 2 000 001 – 3 000 000 | 15 % |
| 3 000 001 – 5 000 000 | 25 % |
| > 5 000 000 | 35 % |

**Assiette** : `brut − CNPS salariale − abattement frais professionnels`.

**Abattement frais professionnels** : 30 % du brut, plafonné 350 000 XAF/mois (4 200 000 XAF/an).
*Note d'implémentation (pilot)* : le moteur passe `brut − CNPS salariale` à `calculateIncomeTax()` ; l'abattement est appliqué sur cette base (≈ 28,7 % du brut au lieu de 30 %). Écart ± 1,3 point à valider par l'expert-comptable.

**Centimes additionnels** : 10 % de l'IRPP (centimes communaux) — IRPP final = `IRPP × 1,10`.

**Exemple** (brut 600 000 XAF/mois, CNPS salariale 25 200) :
- Base après CNPS : 574 800 · Abattement : min(574 800 × 30 %, 350 000) = 172 440
- Assiette mensuelle : 402 360 · Annuelle : 4 828 320
- IRPP annuel : 2 000 000×10 % + 1 000 000×15 % + 1 828 320×25 % = 200 000 + 150 000 + 457 080 = 807 080
- IRPP mensuel : 67 256,67 · Centimes : ×1,10 → **73 982,33 XAF**

## 2. CNPS — Cotisations sociales

| Cotisation | Taux | Plafond mensuel |
|---|---|---|
| Vieillesse salarié | 4,2 % | 750 000 XAF |
| Vieillesse patronal | 4,2 % | 750 000 XAF |
| Famille patronal | 7,0 % | 750 000 XAF |
| AT patronal | 2,0 % | non plafonné |

Codes `social_contributions` : `CNPS_CM_VIE_EMP`, `CNPS_CM_VIE_PAT`, `CNPS_CM_FAM_PAT`, `CNPS_CM_AT_PAT`.
Assiette plafonnée : `min(brut, 750 000)` pour vieillesse et famille ; AT sur le brut complet.

## 3. SMIG

41 875 XAF/mois (décret 2014) — **à vérifier** (SMIG 2024 publié par le MINEFOP).

## 4. Heures supplémentaires

+20 % pour les 8 premières heures supplémentaires/semaine, +30 % au-delà
(implémenté : `CemacPayrollRules::overtimeRateTiers()` — 1,20 / 1,30).

## 5. Préavis (Code du travail 92/007, art. 34)

| Ancienneté | Préavis |
|---|---|
| < 6 mois | 15 jours |
| 6 mois – 5 ans | 1 mois (30 j) |
| 5 – 10 ans | 2 mois (60 j) |
| > 10 ans | 3 mois (90 j) |

## 6. Jours fériés fixes CM

1er janvier · 11 février (Fête nationale de la jeunesse) · 1er mai · 20 mai (Fête nationale) · 15 août (Assomption) · 25 décembre (Noël) + fêtes islamiques mobiles (Eid el-Fitr, Eid el-Adha, etc. — câblage table `islamic_calendar` à venir).

## 7. À valider par expert-comptable camerounais

- [ ] Barème IRPP 2024 (4 tranches) — confirmation tranche > 5 000 000 à 35 %
- [ ] Centimes additionnels 10 %
- [ ] Abattement frais pro 30 % plafonné 350 000/mois + base de calcul (brut vs après CNPS)
- [ ] Taux CNPS 2024 (vieillesse 4,2/4,2, famille 7,0, AT 2,0) + plafond 750 000
- [ ] SMIG 2024
- [ ] Préavis art. 34 (4 niveaux)
- [ ] Prime d'ancienneté et congés (non implémentés — hors périmètre #1821)

## Procédure de mise à jour

Toute évolution des taux (loi de finances, arrêté CNPS) :
1. Mettre à jour `CemacPayrollRules` (defaults CM) ;
2. Mettre à jour le seeder `PayrollCountryConfigSeeder` (rows `social_contributions`/`tax_slabs` avec `effective_from`) ;
3. Mettre à jour ce référentiel (statut + validité) ;
4. Passer `confidenceLevel()` de `pilot` → `production` après validation expert.
