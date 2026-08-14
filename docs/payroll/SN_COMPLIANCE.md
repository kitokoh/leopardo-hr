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
| IR (barème annuel 6 tranches) | ✅ implémentée (pilot) | CGI Sénégal | à valider expert |
| TRIMF (6 tranches forfaitaires) | ✅ implémentée (pilot) | CGI Sénégal | à valider expert |
| CFCE 3 % patronal | ✅ implémentée (pilot) | CGI Sénégal | à valider expert |
| IPRES T1 5,6 % / 8,4 % (plaf. 432 000) | ✅ implémentée (pilot) | IPRES | à valider expert |
| IPRES T2 cadres 2,4 % / 3,6 % (tranche 432k-2 160k) | ✅ implémentée (pilot) | IPRES | à valider expert |
| CSS famille patronale 3 % | ✅ implémentée (pilot) | CSS | à valider expert |
| CSS AT patronale 1 % | ✅ implémentée (pilot) | CSS — variable selon secteur | à valider expert |
| Abattement frais pro 30 % (non plafonné) | ✅ implémentée (pilot) | CGI Sénégal | à valider expert |
| SMIG 58 900 XOF/mois | ✅ implémentée | — | à valider |
| Congés (2,1 j/mois = 25,2 j/an, +1 j/5 ans) | 📝 à documenter/test | Code du travail | — |
| Préavis (8 j ouvriers / 1 m employés / 3 m cadres) | ✅ implémentée (pilot, niveau employé) | Code du travail | à valider expert |
| Jours fériés fixes SN | 📝 via CRUD jours fériés (#1811) | loi | — |
| Jours fériés islamiques (Korité, Tabaski, Gamou, Taamhrit) | 📝 via calendrier islamique (#1812) | table `islamic_calendar` | — |

## 1. IR — Impôt sur le revenu (salaires)

**Barème ANNUEL** (`SenegalPayrollRules::defaultTaxSlabs()`) :

| Tranche annuelle (XOF) | Taux |
|---|---|
| 0 – 630 000 | 0 % |
| 630 001 – 1 500 000 | 20 % |
| 1 500 001 – 4 000 000 | 30 % |
| 4 000 001 – 8 000 000 | 35 % |
| 8 000 001 – 13 500 000 | 37 % |
| > 13 500 000 | 40 % |

## 2. Assiette IR

```
assiette IR mensuelle = brut − cotisations salariales (IPRES T1+T2)
                        − abattement frais pro (30 % du brut, non plafonné)
```

## 3. TRIMF — Taxe Représentative des Impôts du Minimum Fiscal

Taxe forfaitaire mensuelle retenue sur le salarié
(`calculateBracketTax()`, portée dans le bulletin par PayrollCalculator sur
la ligne « Taxe de minimum fiscal ») :

| Tranche brut mensuel (XOF) | TRIMF mensuel |
|---|---|
| 0 – 25 000 | 900 |
| 25 001 – 75 000 | 2 700 |
| 75 001 – 150 000 | 5 400 |
| 150 001 – 350 000 | 9 000 |
| 350 001 – 700 000 | 18 000 |
| > 700 000 | 36 000 |

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

Sur la tranche **432 001 – 2 160 000 XOF/mois** (salaires au-delà du plafond
T1, hypothèse pilote : brut > 432 000 ⇒ régime cadres) :

| Cotisation | Taux | Type |
|---|---|---|
| IPRES cadres salariale (T2) | 2,4 % | salarié |
| IPRES cadres patronale (T2) | 3,6 % | employeur |

## 5. CFCE — Contribution Forfaitaire à la Charge de l'Employeur

**3 % sur la masse salariale brute** (charge patronale uniquement, non
plafonnée) — `CFCE_SN_PAT`.

> **Périmètre de déclaration (#2014)** : la CFCE ne figure PAS dans la
> déclaration mensuelle IPRES/CSS (CSV `IpresDeclarationGenerator`) — elle est
> déclarée via son propre canal (déclaration CFCE). Le patronal du bulletin
> l'inclut ; le CSV ne la déclare pas : divergence attendue, documentée §11.

## 6. CSS — Caisse de Sécurité Sociale

| Cotisation | Taux | Type | Plafond |
|---|---|---|---|
| Prestations familiales patronale | 3,0 % | employeur | **63 000 XOF/mois** |
| Accidents du travail patronale | 1,0 % | employeur | **63 000 XOF/mois** (taux selon risque à confirmer) |

> **Périmètre de déclaration (#2014)** : CSS AT (1 %, variable selon le
> secteur) n'est pas inclus dans la déclaration mensuelle IPRES/CSS (CSV) —
> elle fait l'objet d'une déclaration CSS dédiée (taux selon l'activité
> principale). CSS famille : le générateur CSV la plafonne au plafond T1
> (432 000) alors que le moteur ne la plafonne pas (Q3 — voir §11).

## 7. Abattement frais professionnels

**30 % du brut, non plafonné** — `professionalExpensesDeduction() =
['rate' => 30.0, 'cap' => null]`.

## 8. Préavis

| Catégorie | Préavis |
|---|---|
| Ouvriers | 8 jours |
| Employés / Techniciens | 1 mois |
| Cadres | 3 mois |

⚠️ L'interface n'expose que l'ancienneté : implémentation pilote au niveau
**employé/technicien** (30 jours) — ouvriers et cadres documentés ici, la
catégorie du contrat sera prise en compte dans un suivi.

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
| CSS famille (3,0 %) | ✅ plafonné 63 000 (#1913) | ✅ plafonné 432 000 (t1Base) | Q3 — écart de plafond 63 k vs 432 k, à trancher expert |
| CSS AT (1,0 %) | ✅ plafonné 63 000 (#1913) | ❌ | déclaration CSS dédiée (taux selon risque) |
| CFCE (3,0 %) | ✅ | ❌ | déclaration CFCE dédiée |

**Conséquence** : `total_patronal` du CSV < `employer_contributions` du
bulletin **par conception** — le bulletin porte toutes les charges
patronales, le CSV uniquement le périmètre de cette déclaration. La
réconciliation exacte est verrouillée par
`BulletinDeclarationReconciliationTest` (test SN cadre) :
`employer_contributions = périmètre déclaré (T1+T2+fam CSV) + écart famille
(63 k moteur vs 432 k CSV) + CSS AT 1 % @63 k + CFCE 3 %`.

**Questions ouvertes expert-comptable (#1912, bloquant avant production)** :

1. **Q1 — CSS AT** : doit-elle figurer dans la déclaration mensuelle ou
   reste-t-elle séparée (annuelle/trimestrielle selon le risque) ?
2. **Q2 — CFCE** : ce fichier ou la déclaration CFCE dédiée (trimestrielle) ?
3. **Q3 — CSS famille** : plafonnée au plafond IPRES T1 (432 000, comportement
   CSV) ou non plafonnée (comportement moteur) ? Les deux restent en l'état
   tant que la source officielle (CSS, art. 139 Code de la sécurité sociale)
   n'a pas confirmé — l'écart est documenté et verrouillé par le test.

Source consultée (non concluante, page en cours d'édition) :
eRegulations Sénégal, procédure 103/64 (« Paiement des cotisations à la
CSS », plafond affiché 63 000 CFA incohérent avec le SMIG/IPRES — à
ré-évaluer).

## 12. Fiche de validation experte (issue #1912 — bloquant avant « production »)

`SenegalPayrollRules::confidenceLevel()` reste `pilot` tant que chaque
élément ci-dessous n'est pas validé par un expert-comptable sénégalais
(template : `docs/payroll/_TEMPLATE_VALIDATION_EXPERTE.md` ; registre :
`docs/payroll/VALIDATION_EXPERTE.md`) :

| # | Règle | Valeur pilote implémentée | À valider |
|---|---|---|---|
| 1 | TRIMF (6 tranches forfaitaires) | 900 → 18 000 XOF/mois (barème §3) | tranches + seuils |
| 2 | IPRES T2 cadres (2,4 % / 3,6 %) | tranche 432 001–2 160 000, déclenchée si brut > 432 000 | seuil de déclenchement (catégorie réelle) |
| 3 | CSS AT 1 % | plafond assiette 63 000 XOF/mois | taux selon risque + canal de déclaration (mensuel vs annuel) |
| 4 | CFCE 3 % | masse salariale brute non plafonnée | taux + canal (trimestriel DGI) + périmètre fichier IPRES/CSS |
| 5 | Abattement frais pro 30 % | brut non plafonné (§7) | assiette exacte (plafonnée ?) |
| 6 | Plafond CSS famille 63 000 | 3 % sur min(brut, 63 000) (§6) | 63 000 vs 80 000 (décision CSS 2025 contestée) |
| 7 | Périmètre déclaration IPRES/CSS (§11) | AT + CFCE exclus par conception | confirmation expert |

**Critère de sortie #1912** : fiche signée → `confidenceLevel()` → `production`
+ `verification_date`/source dans ce fichier + `complianceWarning()` levé
(suivi #1872).

## Procédure de mise à jour des taux

1. Valider les nouveaux taux avec un expert-comptable sénégalais.
2. Modifier les valeurs par défaut dans `SenegalPayrollRules` ET/OU insérer
   de nouvelles lignes `tax_slabs` / `social_contributions` datées
   (`effective_from`) pour un changement de barème sans régression.
3. Mettre à jour ce fichier + les golden tests (`GoldenSnPayrollTest`).
4. Faire valider par l'équipe (`php artisan test --filter=Payroll`).
5. Passer `confidenceLevel()` de `pilot` à `production` une fois validé.
