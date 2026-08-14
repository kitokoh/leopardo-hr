# 🇩🇿 Référentiel de conformité paie — Algérie (DZ)

> **Programme FOCUS (F-02)** — Référentiel légal versionné du moteur de paie algérien.
> ✅ **Validé par expert comptable DZ — 2026-08-08** (IRG barème + abattement, CNAS 9 %/26 %, SMIG 20 000 DZD).
> Sources : loi de finances en vigueur, CNAS. Toute évolution des taux = procédure de mise à jour ci-dessous.

## Statut

| Règle | État | Référence | Validité |
|---|---|---|---|
| IRG (barème) | ✅ implémentée + **validée** | LF 2022 (réforme IRG) | 2026-08-08 ✅ |
| CNAS salariale 9 % | ✅ implémentée + **validée** | CNAS | 2026-08-08 ✅ |
| CNAS patronale 26 % | ✅ implémentée + **validée** | CNAS | 2026-08-08 ✅ |
| SMIG/SNA (20 000 DZD) | ✅ implémentée + **validée** | CNAS/loi | 2026-08-08 ✅ |
| Congés payés (2,5 j/mois, 1/10ᵉ) | 📝 à documenter/test | Code du travail (loi 90-11) | — |
| Préavis / licenciement | 📝 à documenter/test | loi 90-11 art. 98+ | — |
| Solde de tout compte / certificat | 📝 à documenter/test | loi 90-11 | — |
| Assurance chômage | 📝 à documenter/test | À identifier | — |
| Heures sup (25 %/50 %) | 📝 à documenter/test | loi 90-11 art. 18-19 | — |

## 1. IRG — Impôt sur le revenu global (salaires)

**Barème mensuel** (implémenté dans `AlgeriaPayrollRules::defaultTaxSlabs()`) :

| Tranche mensuelle (DZD) | Taux |
|---|---|
| 0 – 20 000 | 0 % |
| 20 001 – 40 000 | 23 % |
| 40 001 – 80 000 | 27 % |
| 80 001 – 160 000 | 30 % |
| 160 001 – 320 000 | 33 % |
| > 320 000 | 35 % |

**Abattement** : 40 % de l'impôt annuel, plancher 12 000 DZD/an, plafond 18 000 DZD/an
(implémenté : `min(max(taxAnnuelle × 0.40, 12000), 18000)`).

### 1bis. Assiette de l'IRG

**L'IRG est calculé sur le brut mensuel MINUS les cotisations salariales (CNAS)** :
`assiette IRG = brut − CNAS salariale` (implémenté dans `PayrollCalculator::calculateSlip`, validé par golden test F-03/F-04).

**Exemple chiffré (golden test F-03)** — brut 60 000 DZD :
- CNAS salariale = 5 400 → assiette = **54 600 DZD**
- IRG(54 600) = 4 600 + 14 600 × 27 % = 8 542/mois → annuel 102 504 → abattement plafonné 18 000 → **7 042 DZD/mois**
- Net = 60 000 − 5 400 − 7 042 = **47 558 DZD**

**Exemple chiffré (golden test F-03)** — salaire imposable mensuel 60 000 DZD :
- Tranche 1 (0–20 000) : 0 DZD
- Tranche 2 (20 001–40 000) : 20 000 × 23 % = 4 600 DZD
- Tranche 3 (40 001–60 000) : 20 000 × 27 % = 5 400 DZD
- Impôt mensuel brut : 10 000 DZD → annuel : 120 000 DZD
- Abattement : min(max(120 000 × 0,40 ; 12 000) ; 18 000) = 18 000 DZD
- **IRG mensuel net : (120 000 − 18 000) / 12 = 8 500 DZD**

## 2. CNAS — Cotisations sécurité sociale

| Cotisation | Taux | Assiette | Cap |
|---|---|---|---|
| CNAS salariale | 9 % | salaire brut | aucun (implémenté) — à confirmer |
| CNAS patronale | 26 % | salaire brut | aucun (implémenté) — à confirmer |

**Exemple chiffré (golden test F-03)** — brut 60 000 DZD : salariale 5 400 DZD, patronale 15 600 DZD.

## 3. SMIG / minima

- Salaire minimum mensuel implémenté : **20 000 DZD** (`minimumWage()`).

## 5. Prorata, heures supplémentaires, absences (F-05 — implémenté 2026-08-08)

**Méthode de prorata** : jours travaillés (actual_days_worked / 22 jours ouvrés standards),
recoupe `contract_start`/`contract_end` avec la période du run (PayrollCalculator::computeWorkedDays).

| Cas | Calcul | Résultat |
|---|---|---|
| Entrée 15/07 (17 j calendaires sur 31) | 22 × 17/31 = 12,06 j → 60 000 × 12,06/22 | **32 890,91** |
| Sortie 10/07 (10 j sur 31) | 22 × 10/31 = 7,10 j → 60 000 × 7,10/22 | **19 363,64** |
| Absence 1 jour (21/22) | 60 000 × 21/22 | 57 272,73 (retenue **2 727,27**) |
| Congés sans solde 5 j (17/22) | 60 000 × 17/22 | 46 363,64 (retenue **13 636,36**) |
| Heures sup 10 h @25 % + 5 h @50 % | taux horaire 346,16 (60 000/173,33) → 10×346,16×1,25 + 5×346,16×1,50 | **6 923,20** |

⚠️ Majorations HS (25 % jusqu'à 10 h/mois, 50 % au-delà) : seuil conventionnel **à confirmer** par la convention collective applicable.

**Source des heures sup** : non branchée (0) — le lien pointage → paie (F-20) alimentera `overtime_hours`.

## 6. Congés payés (F-07 — implémenté 2026-08-08)

- **Acquisition** : 2,5 j/mois (30 j/an), via politique `accrual_type=monthly` (commande `leave:accrue`, plafond `max_balance`).
- **Indemnité de congés** : la PLUS FAVORABLE entre
  - **maintien de salaire** : base mensuelle × jours de congé / jours ouvrés (22),
  - **règle du 1/10ᵉ** : (salaires bruts des 12 mois de référence / 10) × jours pris / congés acquis (30).
- Exemples (golden tests) :
  - 5 j, base 60 000, référentiel 720 000 → maintien 13 636,36 > 1/10ᵉ 12 000 → **13 636,36**
  - 10 j, base 60 000, référentiel 900 000 (augmentation) → 1/10ᵉ 30 000 > maintien 27 272,73 → **30 000,00**
  - Mois complet 22 j, référentiel 720 000 → maintien **60 000,00**
- ⚠️ À confirmer : assiette du « salaires bruts de référence » (12 mois glissants vs exercice).
- **Intégration bulletin** : à brancher sur les absences approuvées (F-20) — versée au départ en congé.

## 7. À compléter (prochaine itération — besoin expert paie DZ)

**Statut au 2026-08-12 (FOCUS 2 — F-31)** : la mécanique de fin de contrat
(préavis + indemnité de licenciement) est désormais **portée par les règles
pays** (`CountryRulesInterface::noticePeriodDays` / `severanceMonthsPerYear`,
consommées par `EndOfContractService` au lieu de valeurs codées en dur).

| Sujet | Statut | Référence | À faire |
|---|---|---|---|
| Congés payés (2,5 j/mois, 1/10ᵉ vs maintien) | ✅ implémenté + golden tests | F-07 (#1537), loi 90-11 | Assiette « 12 mois » à confirmer |
| Préavis — durées légales | ✅ implémenté (pilot, #1819) : **1 mois < 10 ans, 2 mois ≥ 10 ans** | loi 90-11 art. 73-5 (ordo 96-21) + conventions collectives | Valider expert comptable DZ |
| Indemnité de licenciement | ⚙️ implémenté : 1 mois/an via règles pays | loi 90-11 | **Plafond légal non appliqué** (à paramétrer/valider) |
| Solde de tout compte + certificat de travail | ✅ implémenté + golden tests | F-08 (#1538) | — |
| Heures supplémentaires (25 %/50 %) | ⚙️ implémenté (palier unique ×1,5 pilot) | loi 90-11 art. 33 | Seuil conventionnel à confirmer |
| Assurance chômage | ✅ documenté (#1819) : **incluse dans la CNAS 9 %/26 %** (0,5 pt de la part salariale) | loi 90-11 art. 97, décret exécutif 94-189 (CNAC) | Aucune cotisation séparée à ajouter |
| Rétroactifs et régularisations | 📝 à documenter | — | Mécanique de bulletin rétroactif non implémentée (roadmap) |

### 7bis. Assurance chômage (DZ-DEPTH #1819)

- **Applicable au secteur privé** : la loi 90-11 (art. 97) couvre le risque chômage ; la **CNAC** (Caisse Nationale d'Assurance Chômage) a été instituée par le décret exécutif **94-189** du 26/07/1994 et perçoit sa quote-part via le recouvrement CNAS.
- **Aucune cotisation séparée à ajouter** : la part « assurance chômage » est comprise dans les taux CNAS existants — **0,5 point de la part salariale de 9 %** (décomposition : assurances sociales 1,5 % + retraite 6,75 % + **chômage 0,5 %** + retraite anticipée 0,25 %). Ajouter une ligne `AC_DZ_EMP`/`AC_DZ_PAT` double compterait la cotisation.
- **Implémentation** : `AlgeriaPayrollRules::socialContributions()` conserve `CNAS_EMP 9 %` / `CNAS_PAT 26 %` (validés expert 2026-08-08) ; la ventilation est documentée dans ce référentiel.

### 7ter. Préavis (DZ-DEPTH #1819)

- **Base légale** : loi 90-11 modifiée par ordonnance 96-21 — **art. 73-5** : « Le licenciement ouvre droit, pour le travailleur qui n'a pas commis de faute grave, à un délai-congé dont la durée minimale est fixée dans les accords ou conventions collectives » ; **art. 68** (démission) renvoie aussi aux conventions collectives. L'employeur peut s'acquitter du délai-congé en versant **la rémunération totale que le travailleur aurait perçue pendant ce délai** (indemnité compensatrice — `PayrollCalculator::computeFinalSettlement`, `notice_pay`).
- **Durées implémentées (pilot)** — règle conventionnelle la plus répandue (CCN nationale / conventions de secteur) :

| Ancienneté | Préavis | Indemnité (base 60 000 DZD, 22 j ouvrés) |
|---|---|---|
| < 10 ans | 1 mois (30 j) | 60 000 × 30/22 = **81 818,18 DZD** |
| ≥ 10 ans | 2 mois (60 j) | 60 000 × 60/22 = **163 636,36 DZD** |

- ⚠️ Les conventions collectives peuvent prévoir des durées plus longues selon la catégorie (ouvriers/maîtrise/cadres) — à valider par expert comptable DZ avant passage en 'production'.

> Règle (inchangée) : toute modification de taux/durée = mise à jour
> **simultanée** du référentiel + du golden test + du CHANGELOG.

## Procédure de mise à jour

Toute modification de taux/barème = PR dédiée qui met à jour **simultanément** :
1. ce référentiel (avec référence légale + période de validité),
2. `AlgeriaPayrollRules` (ou le référentiel versionné qui le remplace),
3. les golden tests correspondants (F-03/F-04),
4. la mention dans le CHANGELOG.
