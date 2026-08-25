# 🇹🇳 Référentiel de conformité paie — Tunisie (TN)

> Audit légal 2026 (issue #5249) — Pack Tunisie 100 %. Niveau courant : `pilot`
> (à faire valider par un expert-comptable local avant passage à `production`,
> issue #1904 — procédure identique à SN #1912).
> Sources : CGI Tunisie (art. 36 et 39), loi n° 2024-48 du 09/12/2024 (LF 2025),
> CNSS.tn (régime non agricole), CLEISS (fiche Tunisie), SmartPaie 2026,
> décret n° 2026-67 (SMIG 2026).

## Statut

| Règle | État | Référence | Validité | Confiance |
|---|---|---|---|---|
| Barème IRPP 2026 (8 tranches, max 40 %) | ✅ implémentée (audit 2026) | CGI art. 36 — LF 2025 (loi n° 2024-48) | 2026-08-23 | `pilot` |
| Abattement frais pro 10 % (bornes 1 000–1 500) | ✅ implémentée + vérifiée | CGI art. 39 (#2261) | 2026-08-23 | `pilot` |
| CNSS régime non agricole 9,18 % / 16,57 % | ✅ implémentée + vérifiée | CNSS.tn / CLEISS / SmartPaie 2026 | 2026-08-23 | `pilot` |
| Fonds perte d'emploi 0,50 % / 0,50 % | ✅ ajoutée (audit 2026) | LF 2025 art. 17 | 2026-08-23 | `pilot` |
| ASSP accidents du travail (patronale 0,4–4 %) | ✅ ajoutée — valeur pilote 1,00 % | CLEISS / Code des assurances sociales | 2026-08-23 | `pilot` |
| SMIG 2026 (48 h) | ✅ implémentée (audit 2026) | décret n° 2026-67 (effet 01/01/2026) | 2026-08-23 | `pilot` |
| Heures supplémentaires 25 % | ✅ implémentée (palier unique) | Code du travail art. 90 | à confirmer | `pilot` |

**Confiance** : `TunisiaPayrollRules::confidenceLevel()` renvoie `pilot`.
L'audit 2026 (issue #5249) a mis à jour les taux sur sources officielles et
professionnelles publiées en 2026 ; le passage à `production` exige la revue
formelle d'un expert-comptable tunisien.

## 1. IRPP — Impôt sur le revenu des personnes physiques

**Barème ANNUEL 2026** (implémenté dans `TunisiaPayrollRules::defaultTaxSlabs()`,
calcul progressif par tranches, puis ÷ 12) — **art. 36 de la loi n° 2024-48 du
09/12/2024 (LF 2025)**, en vigueur depuis le 01/01/2025, inchangé en 2026 :

| Tranche annuelle (TND) | Taux |
|---|---|
| 0 – 5 000 | 0 % |
| 5 001 – 10 000 | 15 % |
| 10 001 – 20 000 | 25 % |
| 20 001 – 30 000 | 30 % |
| 30 001 – 40 000 | 33 % |
| 40 001 – 50 000 | 36 % |
| 50 001 – 70 000 | 38 % |
| > 70 000 | 40 % |

> ⚠️ L'ancien barème (0–5 000 0 % · 5 001–20 000 26 % · 20 001–30 000 28 % ·
> 30 001–50 000 32 % · > 50 000 35 %) est **remplacé** par la LF 2025.

### 1bis. Assiette de l'IRPP

`assiette IRPP = brut − cotisations salariales (CNSS + perte d'emploi) − abattement frais professionnels`

**Abattement frais professionnels** (CGI art. 39) : **10 %** du revenu annuel
imposable, borné **[1 000 ; 1 500 TND/an]** (plancher 1 000, plafond 1 500).

## 2. Cotisations sociales (2026 — régime non agricole)

| Prélèvement | Salarié | Employeur | Plafond |
|---|---|---|---|
| CNSS régime non agricole | 9,18 % | 16,57 % | sans plafond général |
| Fonds d'assurance perte d'emploi (LF 2025 art. 17) | 0,50 % | 0,50 % | — |
| **Sous-total salarié / employeur** | **9,68 %** | **17,07 %** | — |
| ASSP — accidents du travail et maladies professionnelles | — | **0,4 % à 4 %** selon le secteur (valeur pilote 1,00 %) | — |
| **Total employeur (pilot)** | — | **18,07 %** | — |

Notes :
- La CNSS n'applique **pas** de plafond général de 5 000 TND aux cotisations
  ordinaires (le seuil « 6 × SMIG » concerne le régime complémentaire de
  pensions — source CNSS.tn).
- TFP et FOPROLOS (employeur) ne sont **pas** inclus dans le 16,57 % — hors
  périmètre de cette fiche, à documenter séparément.
- ASSP : taux sectoriel 0,4–4 % ; valeur pilote 1,00 % retenue (commerce/
  services), surchargeable par entreprise via la table `social_contributions`.

## 3. SMIG

- **Secteur non agricole 2026, régime 48 h/semaine** : **554,736 TND/mois**
  (décret n° 2026-67, revalorisation d'effet au 01/01/2026 ; 470,251 TND en
  régime 40 h). Référence 2025 : 528,32 TND (48 h).

## 4. Heures supplémentaires

Code du travail art. 90 : majoration **25 %** au-delà de la durée normale
hebdomadaire (48 h pour la plupart des secteurs non agricoles). Palier unique
modélisé (`overtimeRateTiers()`), à valider pour la distinction jour/nuit/férié.

## 5. Golden tests

`api/tests/Feature/Payroll/Golden/GoldenTnPayrollTest.php` — 15 cas calculés à
la main (issue #5249) : SMIG 2026, ouvrier, cadre moyen, cadre supérieur, haut
salaire, barème légal verrouillé (8 tranches), exemples publiés vérifiés
(25 000 → 4 750 · 35 000 → 7 900 · 60 000 → 16 950), bornes d'abattement
art. 39, calendrier de cotisations complet, paramètres pays, 13ᵉ mois,
version de règles.

## Exemples chiffrés (golden)

- **Brut 2 000 TND** : salarié 193,60 (CNSS 183,60 + PLE 10,00) · employeur
  361,40 · abattement plafonné 1 500 → imposable annuel 20 176,80 → IRPP
  mensuel **275,25** · net **1 531,15**.
- **Brut 10 000 TND** : salarié 968,00 · employeur 1 807,00 · imposable annuel
  106 884,00 → tranche 40 % → IRPP mensuel **2 958,63** · net **6 073,37**.

## Procédure de mise à jour

1. Source officielle nouvelle (LF, BO, CNSS.tn, DGI) → mettre à jour
   `TunisiaPayrollRules` **et** ce document **dans le même PR**.
2. Recalculer les golden tests à la main et les mettre à jour dans le même PR.
3. Entrée `CHANGELOG.md` en tête d'`[Unreleased]`.
4. Faire valider par expert-comptable local → passage `confidenceLevel()` à
   `production` + enregistrement dans `docs/payroll/VALIDATION_EXPERTE.md`.
