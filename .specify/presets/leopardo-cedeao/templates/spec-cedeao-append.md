---

## 🌍 Conformité Paie CEDEAO/UEMOA — Afrique de l'Ouest (preset leopardo-cedeao v1.0.0)

> Zone UEMOA/XOF : CI (Côte d'Ivoire), SN (Sénégal), BF (Burkina Faso), ML (Mali), TG, BJ, NE
> Devise partagée : XOF (franc CFA BCEAO)

### Niveau de maturité par pays

| Pays | Niveau | Impôt | Organisme social |
|------|--------|-------|----------------|
| CI | `pilot` | ITSAS + CN | CNSS (plaf. 1 647 315 XOF/mois) |
| SN | `pilot` | IR + TRIMF + CFCE | IPRES T1 (plaf. 432k) + T2 cadres + CSS |
| BF | `pilot` | IUTS | CNSS (plaf. 900 000 XOF/mois) |
| ML | `pilot` | ITS | INPS (plaf. 3 000 000 XOF/mois) |
| TG, BJ, NE | `placeholder` | — | — |

### Règles Côte d'Ivoire (CI)

| Règle | Valeur |
|-------|--------|
| ITSAS tranches | 0/2/21/24,5/29 % (annualisé) |
| Contribution Nationale | 1,5 % sur brut > 50 000 XOF/mois |
| Abattement frais pro | 20 % brut (non plafonné) |
| CNSS retraite salarié | 3,2 %, plafond 1 647 315 XOF/mois |
| CNSS retraite patronal | 4,5 %, même plafond |
| CNSS famille patronal | 5,75 %, même plafond |
| 13ème mois CI | Obligatoire (convention) — `thirteenthMonthMandatory()` = true |

### Règles Sénégal (SN)

| Règle | Valeur |
|-------|--------|
| IR tranches | 0/20/30/35/37/40 % (annualisé) |
| TRIMF | 900→36 000 XOF/mois selon tranche brut (6 tranches) |
| CFCE | 3 % patronal sur masse salariale |
| IPRES T1 | 5,6 % salarié / 8,4 % patronal — plafond 432 000 XOF/mois |
| IPRES T2 (cadres) | 2,4 % sal. / 3,6 % pat. — sur tranche 432k–2 160k XOF |
| CSS famille | 3 % patronal, non plafonné |

### Contraintes de cette spec

**Si pays = CI :**
- [ ] `CedeaoPayrollRules::forMemberCountry('CI')`
- [ ] ITSAS et CN calculés séparément et sommés dans `calculateIncomeTax()`
- [ ] Abattement 20 % dans `professionalExpensesDeduction()`
- [ ] Plafond CNSS 1 647 315 XOF via `computeContribution()`
- [ ] Golden tests dans `GoldenCiPayrollTest.php` — min 10 cas

**Si pays = SN :**
- [ ] TRIMF dans `calculateBracketTax()` — 6 tranches
- [ ] CFCE dans `socialContributions()` comme charge patronale
- [ ] IPRES T2 cadres distinct du régime général
- [ ] Plafond IPRES T1 432 000 XOF via `computeContribution()`
- [ ] Golden tests dans `GoldenSnPayrollTest.php` — min 10 cas

**Si pays = BF ou ML :**
- [ ] Utiliser `CedeaoPayrollRules::forMemberCountry('BF')` ou `'ML'`
- [ ] Plafond BF = 900k / ML = 3M via `computeContribution()`
- [ ] Golden tests min 6 cas par pays

**Pour tout pays CEDEAO :**
- [ ] Autres membres UEMOA non modifiés (test non-régression)
- [ ] `confidenceLevel()` à `'pilot'` minimum
