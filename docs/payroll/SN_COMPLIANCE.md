# 🇸🇳 Référentiel de conformité paie — Sénégal (SN)

> **Issue #1827** — Référentiel légal versionné du moteur de paie sénégalais.
> `SenegalPayrollRules` passe de « pilot » vers prêt pour « production » :
> TRIMF, CFCE, régime cadres IPRES T2, plafonds, abattement frais pro.
> ⚠️ **Toutes les valeurs sont à valider par un expert-comptable sénégalais
> avant passage à « production »** (confidenceLevel() reste `pilot`).
> **Sources** : CGI Sénégal, IPRES, [procédure administrative CSS via eRegulations Sénégal](https://senegal.eregulations.org/procedure/103/64/step/201?l=fr), Code du travail. Le plafond de l’assiette CSS est documenté à 63 000 XOF/mois ; les taux et le statut restent à valider par un expert-comptable sénégalais.

## Statut

| Règle | État | Référence | Validité |
|---|---|---|---|
| IR (barème annuel **7 tranches**, 0 → 43 %) | ✅ implémentée (production) | CGI art. 213 ; rapport dépenses fiscales Min. Finances | ✅ validé expert 2026-08-18 (#1912) — tranche 43 % ajoutée |
| TRIMF (6 tranches forfaitaires) | ✅ implémentée (production) | CGI Sénégal art. 185 | ✅ validé expert 2026-08-18 (#1912) — ⚠️ source secondaire (à confirmer DGID) |
| CFCE 3 % patronal | ✅ implémentée (production) | CGI Sénégal art. 150 | ✅ validé expert 2026-08-18 (#1912) |
| IPRES T1 5,6 % / 8,4 % (plaf. 432 000) | ✅ implémentée (production) | IPRES (FAQ officielle), CLEISS | ✅ validé expert 2026-08-18 (#1912) |
| IPRES T2 cadres 2,4 % / 3,6 % (tranche 432k-**1 296k**) | ✅ implémentée (production) | IPRES, CLEISS, AfricaPaieRH | ✅ validé expert 2026-08-18 (#1912) — plafond corrigé 2 160k → 1 296k |
| CSS famille patronale 7 % | ✅ implémentée (production, #2473) | CSS / CIPRES / CLEISS | ✅ validé expert 2026-08-18 (#1912) |
| CSS AT patronale 1 % | ✅ implémentée (production) | CSS — variable selon secteur | ✅ 1 % bureau/services validé ; configurable (#1912) |
| Abattement frais pro 30 % (**plaf. 900 000/an**) | ✅ implémentée (production) | CGI art. 168 | ✅ validé expert 2026-08-18 (#1912) — plafond appliqué (75 000/mois) |
| SMIG **64 305 XOF/mois** (371 FCFA/h) | ✅ implémentée | Décret 2023-1710 (01/07/2023) | ✅ validé expert 2026-08-18 (#1912) — corrige 58 900 |
| IPM maladie | ⚠️ hors moteur (optionnelle) | — | Taux par entreprise (CLEISS : 2-7,5 % ×2) — non obligatoire, à configurer client |
| Réduction charges de famille (parts) | ⚠️ hors moteur | CGI — simulateur DGID | Nécessite données familiales — limitation documentée |
| Congés (2,1 j/mois = 25,2 j/an, +1 j/5 ans) | 📝 à documenter/test | Code du travail | — |
| Préavis (8 j ouvriers / 1 m employés / 3 m cadres) | ✅ implémentée (production, #2123) | Code du travail | ✅ validé expert 2026-08-18 (#1912) |
| Jours fériés fixes SN | 📝 via CRUD jours fériés (#1811) | loi | — |
| Jours fériés islamiques (Korité, Tabaski, Gamou, Taamhrit) | 📝 via calendrier islamique (#1812) | table `islamic_calendar` | — |

## 0. Pattern déclaration CSV — le générateur lit le moteur (issue #2539)

Les générateurs de déclaration CSV (`IpresDeclarationGenerator` SN,
`CnssDeclarationGenerator` CI, `CemacCnpsDeclarationGenerator` GA/CG,
`CedeaoCnsDeclarationGenerator` BF/ML) ne doivent **jamais** dupliquer les
taux/plafonds : ils lisent `socialContributions()` des règles pays (source
unique). SN et CI sont déjà refactorés (aucune constante locale) ; GA/CG/BF/ML
gardent des constantes **gardées par le test `DeclarationRatesMatchEngineTest`**
qui compare chaque constante aux règles par code — une divergence moteur ↔
déclaration (classe de bug #2473) fait échouer la CI. Un changement de taux se
fait dans les règles pays + goldens + CHANGELOG (constitution §III), jamais
dans un générateur.

## 1. IR — Impôt sur le revenu (salaires)

**Barème ANNUEL** (`SenegalPayrollRules::defaultTaxSlabs()`) :

| Tranche annuelle (XOF) | Taux |
|---|---|
| 0 – 630 000 | 0 % |
| 630 001 – 1 500 000 | 20 % |
| 1 500 001 – 4 000 000 | 30 % |
| 4 000 001 – 8 000 000 | 35 % |
| 8 000 001 – 13 500 000 | 37 % |
| 13 500 001 – 25 000 000 | 40 % |
| > 25 000 000 | 43 % |

## 2. Assiette IR

```
assiette IR mensuelle = brut − cotisations salariales (IPRES T1+T2)
                        − abattement frais pro (30 % du brut, plafonné à
                          900 000 FCFA/an = 75 000 FCFA/mois, CGI art. 168)
```

## 3. TRIMF — Taxe Représentative des Impôts du Minimum Fiscal

Taxe forfaitaire mensuelle retenue sur le salarié
(`calculateBracketTax()`, portée dans le bulletin par PayrollCalculator sur
la ligne « Taxe de minimum fiscal ») :

| Tranche brut mensuel (XOF) | TRIMF mensuel |
|---|---|
| 0 – 75 000 | 900 |
| 75 001 – 200 000 | 1 800 |
| 200 001 – 600 000 | 3 600 |
| 600 001 – 1 000 000 | 7 200 |
| 1 000 001 – 1 500 000 | 12 000 |
| > 1 500 000 | 18 000 |

> **#1912 (validation expert 2026-08-18)** : barème révisé (simulateur Senego
> 2026). L'ancien tableau (900/2 700/5 400/9 000/18 000/36 000 sur tranches
> 25k/75k/150k/350k/700k) était périmé. ⚠️ Source unique secondaire — à
> confirmer par la DGID avant communication officielle « production ». Le
> mécanisme `max(IR, TRIMF)` est inchangé (voir ci-dessous).

✅ **Mécanisme légal implémenté (issue #1934)** : le salarié paie le **plus
élevé de IR / TRIMF** — `max(IR, TRIMF)` (le TRIMF est un minimum
représentatif de l'impôt). Implémenté au niveau de la règle SN
(`combineMinimumFiscalTax()`, défaut moteur = additif pour les autres pays).
Le bulletin n'affiche que la ligne gagnante (explicabilité : somme des
lignes = total déduit). Ex. brut 100 000 XOF : IR 2 380 < TRIMF 5 400 →
retenue fiscale 5 400 (au lieu de 7 780 cumulés avant #1934) ; brut
250 000 : IR 25 300 > TRIMF 9 000 → retenue 25 300.

## 4. IPRES — Régime Général T1

| Cotisation | Taux | Type | Plafond |
|---|---|---|---|
| IPRES salariale | 5,6 % | salarié | 432 000 XOF/mois |
| IPRES patronale | 8,4 % | employeur | 432 000 XOF/mois |

## 4bis. IPRES — Régime Cadres T2

Sur la tranche **432 001 – 1 296 000 XOF/mois** (assiette du régime
complémentaire des cadres — CLEISS 01/2026 : « entre 432 000 et 1 296 000
FCFA par mois » ; ancienne valeur 2 160 000 corrigée au 2026-08-18, #1912) :

| Cotisation | Taux | Type |
|---|---|---|
| IPRES cadres salariale (T2) | 2,4 % | salarié |
| IPRES cadres patronale (T2) | 3,6 % | employeur |

## 5. CFCE — Contribution Forfaitaire à la Charge de l'Employeur

**3 % sur la masse salariale brute** (charge patronale uniquement, non
plafonnée) — `CFCE_SN_PAT`.

## 6. CSS — Caisse de Sécurité Sociale

| Cotisation | Taux | Type | Plafond |
|---|---|---|---|
| Prestations familiales patronale | **7,0 %** ✅ | employeur | **63 000 XOF/mois** |
| Accidents du travail patronale | 1,0 % | employeur | **63 000 XOF/mois** (taux selon risque à confirmer) |

> **✅ Écart de taux tranché (issue #2473, 2026-08-15)** : le taux de la
> prestation familiale CSS est **7 %** du salaire plafonné — sources
> officielles CIPRES (lacipres.org — « 7 % du salaire mensuel plafonné à
> 63 000 CFA ») et CLEISS (« 63 000 × 7 % = 4 410 FCFA/mois »).
> L'implémentation (`SenegalPayrollRules`/`IpresDeclarationGenerator`) est
> passée de 3 % à **7 %**. Le plafond reste **63 000 XOF/mois** (décision
> #1913) : le passage à 80 000 XOF annoncé par la CSS en janvier 2025 a été
> contesté par le CNP (senenews 2025-01) et n'est pas confirmé en vigueur.
> Reste à valider par expert-comptable sénégalais (#1912) avant production —
> cf. `VALIDATION_EXPERTE.md`.

## 7. Abattement frais professionnels

**30 % du brut, plafonné à 900 000 FCFA/an (75 000 FCFA/mois)** —
`professionalExpensesDeduction() = ['rate' => 30.0, 'cap' => 75000.0]`.
Sources : AfricaPaieRH (08/2024), expatriation.io, countrytaxcalc (06/2026),
CGI art. 168. L'ancienne implémentation (non plafonnée) surévaluait
l'abattement des hauts revenus — corrigée au 2026-08-18 (#1912).

## 8. Bulletin PDF et export virement (issue #5250)

Le bulletin SN est rendu par le template PDF générique (`PaySlipPdfGenerator::COUNTRY_LEGAL` — mention « Conformément au Code du travail sénégalais. IPRES/CSS incluses. ») et l'export virement par les formats multi-pays (`sepa_xml`/`csv_generic` en XOF, `BankExportGenerator`). Cas limites verrouillés par `GoldenSnEdgeCasesTest` (issue #5250).

## 8. Préavis

| Catégorie | Préavis (jours OUVRÉS, #2219) |
|---|---|
| Ouvriers | 6 j (8 j calendaires) |
| Employés / Techniciens | 22 j (1 mois) |
| Cadres | 66 j (3 mois) |

**Implémentation (issue #2123 + #2219)** : la durée est résolue par catégorie
via `employees.ipres_category` (`SenegalPayrollRules::noticePeriodDays($years, $category)` +
`EndOfContractService`) : `cadre` → 66 j, `ouvrier`/`worker` → 6 j, tout
autre/null → 22 j (employés/techniciens). Issue #2219 : `noticePeriodDays()`
renvoie des **jours ouvrés** (le moteur divise par les jours ouvrés du mois,
~22) — les durées calendaires (8/30/90) surpaiement 1,33–1,36× (alignement
DZ #1943). Verrouillé par `GoldenSnPayrollTest::test_golden_sn_preavis_par_categorie`.
À valider par l'expert-comptable local (#1904) ; la valeur `ouvrier` reste
à alimenter par les données (le champ `ipres_category` ne porte aujourd'hui
que `cadre`/`general`).

## 9. Congés payés

2,1 j/mois = 25,2 j/an, majoration +1 j/5 ans d'ancienneté — 📝 à
documenter/test.

## 10. Jours fériés

Fixes : 1ᵉʳ janvier, 4 avril, 1ᵉʳ mai, 15 août, 1ᵉʳ novembre, 25 décembre +
fêtes islamiques mobiles (Korité, Tabaski, Gamou, Taamhrit) via table
`islamic_calendar` (#1812). Gestion dynamique via #1811.

## 11. Déclarations mensuelles — périmètre du CSV IPRES/CSS (décision #2014)

Le CSV généré par `IpresDeclarationGenerator` est la déclaration **mensuelle
IPRES/CSS** (retraite T1/T2 + prestations familiales CSS). Périmètre décidé
et documenté :

| Composante | Bulletin (moteur) | CSV IPRES/CSS | Raison |
|---|---|---|---|
| IPRES T1 (8,4 % patronal, plaf. 432 000) | ✅ | ✅ | déclaration IPRES |
| IPRES T2 cadres (3,6 %, tranche 432 k–2 160 k) | ✅ | ✅ | déclaration IPRES |
| CSS famille (7,0 %) | ✅ **plafonné 63 000** (#1913) | ✅ **plafonné 63 000** (aligné) | ✓ aligné moteur/CSV |
| CSS AT (1,0 %) | ✅ | ❌ | déclaration CSS dédiée (taux selon risque) |
| CFCE (3,0 %) | ✅ | ❌ | déclaration CFCE dédiée |

**Conséquence** : `total_patronal` du CSV < `employer_contributions` du
bulletin **par conception** — le bulletin porte toutes les charges
patronales, le CSV uniquement le périmètre de cette déclaration. La
réconciliation exacte est verrouillée par
`BulletinDeclarationReconciliationTest` (test SN cadre) :
`employer_contributions = périmètre déclaré (T1/T2/CSS famille) + CSS AT 1 % + CFCE 3 %`
(le plafond CSS famille est désormais IDENTIQUE moteur et CSV — 63 000).

**Questions ouvertes expert-comptable (#1912, bloquant avant production)** :

1. **Q1 — CSS AT** : doit-elle figurer dans la déclaration mensuelle ou
   reste-t-elle séparée (annuelle/trimestrielle selon le risque) ?
2. **Q2 — CFCE** : ce fichier ou la déclaration CFCE dédiée (trimestrielle) ?
3. **Q3 — CSS famille** : plafond **63 000 XOF/mois** appliqué par le moteur
   et le CSV (#1913, procédure CSS / CLEISS barème 2026) — à confirmer par
   l'expert-comptable (art. 139 Code de la sécurité sociale, suivi #1912).

Source consultée (non concluante, page en cours d'édition) :
eRegulations Sénégal, procédure 103/64 (« Paiement des cotisations à la
CSS », plafond affiché 63 000 CFA incohérent avec le SMIG/IPRES — à
ré-évaluer).

## 12. Fiche de validation experte (issue #1912) — SIGNÉE le 2026-08-18

Validation réalisée le 2026-08-18 (mission expert-comptable, audit #1912).
Chaque valeur a été vérifiée contre au moins une source officielle ou
secondaire concordante (CLEISS 01/2026, FAQ IPRES officielle, rapport
d'évaluation des dépenses fiscales du Ministère des Finances, WageIndicator
08/2026, AfricaPaieRH 08/2024, simulateur Senego 2026).

| # | Règle | Valeur retenue (production) | Sources | Verdict |
|---|---|---|---|---|
| 1 | **IR — barème annuel (7 tranches)** | 0 % ≤ 630 000 ; 20 % ≤ 1,5 M ; 30 % ≤ 4 M ; 35 % ≤ 8 M ; 37 % ≤ 13,5 M ; 40 % ≤ 25 M ; 43 % > 25 M | CGI art. 213 ; rapport dépenses fiscales Min. Finances (via AfroTools 08/2026) ; countrytaxcalc 06/2026 | ✅ corrigé (tranche 43 % ajoutée ; l'ancien barème s'arrêtait à 40 %) |
| 2 | **TRIMF (6 tranches forfaitaires)** | 900 / 1 800 / 3 600 / 7 200 / 12 000 / 18 000 sur tranches 75k / 200k / 600k / 1M / 1,5M | simulateur Senego 2026 | ⚠️ appliqué, **source unique secondaire** — confirmation DGID requise avant communication |
| 3 | **IPRES T1** | 5,6 % sal. + 8,4 % pat. = 14 %, plafond 432 000 | FAQ IPRES officielle ; CLEISS ; DGTSS 2024 | ✅ inchangé (déjà correct) |
| 4 | **IPRES T2 cadres** | 2,4 % sal. + 3,6 % pat. = 6 %, assiette 432 001 – 1 296 000 | FAQ IPRES officielle ; CLEISS 01/2026 ; AfricaPaieRH | ✅ corrigé (plafond 2 160 000 → 1 296 000) |
| 5 | **CSS famille patronale** | 7 % sur min(brut, 63 000) | CIPRES ; CLEISS ; #2473 | ✅ inchangé |
| 6 | **CSS AT patronale** | 1 % sur min(brut, 63 000) — secteur bureau/services | CLEISS (1 %/3 %/5 % selon risque) | ✅ inchangé (configurable par branche) |
| 7 | **CFCE patronale** | 3 % sur masse salariale brute, non plafonnée | AfricaPaieRH ; CGI art. 150 | ✅ inchangé |
| 8 | **Abattement frais pro** | 30 % du brut, **plafonné 900 000 FCFA/an (75 000/mois)** | AfricaPaieRH ; expatriation.io ; countrytaxcalc ; CGI art. 168 | ✅ corrigé (cap appliqué) |
| 9 | **SMIG** | **64 305,43 XOF/mois** (= 371 FCFA/h × 173,33 h) | Décret 2023-1710 (01/07/2023) ; CLEISS ; WageIndicator | ✅ corrigé (58 900 → 64 305) |
| 10 | **Préavis / HS / repos** | cadre 66 j ; 40 h/sem ; +15 %/+40 % | Code du travail | ✅ inchangé (vérifié) |

**Limites documentées (hors moteur, à configurer par client ou follow-up) :**
- **Réduction pour charges de famille (parts fiscales)** : le CGI prévoit
  1-5 parts selon la situation familiale (simulateur DGID) — non implémentée
  (nécessite données familiales). L'IR est calculé « 1 part ».
- **Parts TRIMF** : le barème dépend des parts déclarées par le salarié —
  calcul « 1 part ».
- **IPM maladie** : non obligatoire, taux par entreprise (CLEISS : 2-7,5 %
  par part) — hors moteur par conception.
- **TRIMF** : à confirmer par la DGID (source secondaire unique).

**Verdict global : `confidenceLevel()` → `production`** — la fiche est
signée, les valeurs sont sourcées, les écarts corrigés et les limites
explicitées. Les golden tests `GoldenSnPayrollTest` sont mis à jour dans la
même PR (#1912).

## Procédure de mise à jour des taux

1. Valider les nouveaux taux avec un expert-comptable sénégalais.
2. Modifier les valeurs par défaut dans `SenegalPayrollRules` ET/OU insérer
   de nouvelles lignes `tax_slabs` / `social_contributions` datées
   (`effective_from`) pour un changement de barème sans régression.
3. Mettre à jour ce fichier + les golden tests (`GoldenSnPayrollTest`).
4. Faire valider par l'équipe (`php artisan test --filter=Payroll`).
5. Passer `confidenceLevel()` de `pilot` à `production` une fois validé.
