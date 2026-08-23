# 🇲🇦 Référentiel de conformité paie — Maroc (MA)

> Audit légal 2026 (issue #5248) — Pack Maroc 100 %. Niveau courant : `pilot`
> (à faire valider par un expert-comptable local avant passage à `production`,
> issue #1904 — procédure identique à SN #1912).
> Sources : CGI Maroc (art. 59-I, art. 73-I), Loi de finances 2023 et 2025,
> CNSS.ma (taux officiels), CLEISS (fiche Maroc), Upsilon Consulting 2026
> (expert-comptable, tableau 2026), loi 65-99 (Code du travail).

## Statut

| Règle | État | Référence | Validité | Confiance |
|---|---|---|---|---|
| Barème IR 2026 (6 tranches, max 37 %) | ✅ implémentée (audit 2026) | CGI art. 73-I — LF 2025 | 2026-08-23 | `pilot` |
| Abattement frais pro (35 % / 25 %, plafond 35 000) | ✅ implémentée (audit 2026) | CGI art. 59-I — LF 2023 | 2026-08-23 | `pilot` |
| CNSS salariale 4,48 % / patronale 8,98 % (plafond 6 000) | ✅ implémentée + vérifiée | CNSS.ma / CLEISS / Upsilon 2026 | 2026-08-23 | `pilot` |
| AMO 2,26 % / 4,11 % (non plafonnée) | ✅ implémentée + vérifiée | CNSS.ma / CLEISS / Upsilon 2026 | 2026-08-23 | `pilot` |
| IPE (perte d'emploi) 0,19 % / 0,38 % (plafond 6 000) | ✅ ajoutée (audit 2026) | CLEISS / Humantal / décrets IPE | 2026-08-23 | `pilot` |
| Allocations familiales 6,40 % patronale | ✅ ajoutée (audit 2026) | Upsilon 2026 / CLEISS | 2026-08-23 | `pilot` |
| Taxe formation professionnelle 1,60 % patronale | ✅ ajoutée (audit 2026) | Upsilon 2026 | 2026-08-23 | `pilot` |
| SMIG 2026 (17,92 MAD/h × 191 h) | ✅ implémentée (audit 2026) | loi 65-99 art. 184, accord social 2024-2026 | 2026-08-23 | `pilot` |
| Heures supplémentaires 25 % (jour) / 50 % (nuit) | ✅ implémentée (palier jour) | loi 65-99 art. 201 | à confirmer | `pilot` |
| Repos hebdo dimanche | ✅ implémentée | loi 65-99 | — | `pilot` |

**Confiance** : `MoroccoPayrollRules::confidenceLevel()` renvoie `pilot`.
L'audit 2026 (issue #5248) a mis à jour les taux sur sources officielles et
professionnelles publiées en 2026, mais le passage à `production` exige la
revue formelle d'un expert-comptable marocain (même procédure que SN #1912).

## 1. IR — Impôt sur le revenu (salaires)

**Barème ANNUEL 2026** (implémenté dans `MoroccoPayrollRules::defaultTaxSlabs()`,
méthode « taux × revenu net imposable − somme à déduire », puis ÷ 12) :

| Tranche annuelle (MAD) | Taux | Somme à déduire (MAD/an) |
|---|---|---|
| 0 – 40 000 | 0 % | 0 |
| 40 001 – 60 000 | 10 % | 4 000 |
| 60 001 – 80 000 | 20 % | 10 000 |
| 80 001 – 100 000 | 30 % | 18 000 |
| 100 001 – 180 000 | 34 % | 22 000 |
| > 180 000 | 37 % | 27 400 |

Réforme Loi de Finances 2025 (en vigueur depuis 01/2025, inchangée en 2026) :
seuil d'exonération relevé de 30 000 à **40 000 MAD/an**, taux marginal abaissé
de 38 % à **37 %**, tranche 80-100 k ramenée de 34 % à **30 %**.

### 1bis. Assiette de l'IR

`assiette IR = brut − cotisations salariales (CNSS + AMO + IPE) − abattement frais professionnels`

**Abattement frais professionnels** (CGI art. 59-I, LF 2023) :
- 35 % du brut annuel imposable si **< 78 000 MAD/an** ;
- 25 % si **≥ 78 000 MAD/an** ;
- plancher **2 500 MAD/an**, plafond **35 000 MAD/an** (ex-30 000 avant LF 2023).

## 2. Cotisations sociales (2026)

| Cotisation | Salarié | Employeur | Plafond mensuel |
|---|---|---|---|
| CNSS prestations sociales (court terme) | 0,52 % | 1,05 % | 6 000 MAD |
| CNSS long terme (retraite, invalidité, décès) | 3,96 % | 7,93 % | 6 000 MAD |
| **CNSS total** | **4,48 %** | **8,98 %** | **6 000 MAD** |
| AMO | 2,26 % | 4,11 % | sans plafond |
| IPE (indemnité pour perte d'emploi) | 0,19 % | 0,38 % | 6 000 MAD |
| Allocations familiales | — | 6,40 % | sans plafond |
| Taxe de formation professionnelle | — | 1,60 % | sans plafond |
| **Total** | **6,93 %** | **21,47 %** | — |

Décomposition vérifiée sur Upsilon Consulting 2026 (expert-comptable,
tableau CNSS 2026) et CLEISS : total employeur 21,09 % sans IPE → 21,47 %
avec IPE 0,38 % ; total salarié 6,74 % sans IPE → 6,93 % avec IPE 0,19 %.

## 3. SMIG

- **Secteur non agricole 2026** : **17,92 MAD/heure** × 191 h/mois = **3 422,72 MAD/mois**
  (loi 65-99 art. 184 — base mensuelle légale de 191 heures ; accord social
  2024-2026, revalorisation en vigueur 2026).

## 4. Heures supplémentaires

Loi 65-99 art. 201 : majoration **25 %** (heures de jour) à **50 %** (heures de
nuit / jour de repos), taux plus élevés les jours fériés. Seuil hebdo légal :
**44 h** (secteur non agricole). Le moteur modélise le palier « heures de
jour » 25 % uniquement (`overtimeRateTiers()`), faute d'horodatage
jour/nuit/férié dans l'interface générique — à valider avant usage réel.

## 5. Golden tests

`api/tests/Feature/Payroll/Golden/GoldenMaPayrollTest.php` — 18 cas calculés à
la main (issue #5248) : SMIG 2026, ouvrier, plafond CNSS exact, abattement
25 %, cadre moyen, tranche 34 %, tranche 37 %, très haut salaire (plafonds
atteints), barème légal verrouillé, bornes d'abattement, calendrier de
cotisations, simulation sans plafonds, paramètres pays, 13ᵉ mois, version de
règles.

## Exemples chiffrés (golden)

- **Brut 10 000 MAD** : salarié 506,20 (CNSS 268,80 + AMO 226,00 + IPE 11,40) ·
  employeur 1 772,60 · abattement 25 % (annuel ≥ 78 000) → assiette annuelle
  85 444,20 → tranche 30 % : IR mensuel **636,10** · net **8 857,70**.
- **Brut 60 000 MAD** : salarié 1 636,20 · employeur 7 827,60 · abattement
  plafonné 35 000 → assiette annuelle 665 365,60 → tranche 37 % : IR mensuel
  **18 232,11** · net **40 131,69**.

## Procédure de mise à jour

1. Source officielle nouvelle (LOI de finances, BO SGG, CNSS.ma, DGI) → mettre
   à jour `MoroccoPayrollRules` **et** ce document **dans le même PR**.
2. Recalculer les golden tests à la main et les mettre à jour dans le même PR.
3. Entrée `CHANGELOG.md` en tête d'`[Unreleased]`.
4. Faire valider par expert-comptable local → passage `confidenceLevel()` à
   `production` + enregistrement dans `docs/payroll/VALIDATION_EXPERTE.md`.
