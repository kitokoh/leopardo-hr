---

## 🌍 Conformité Paie CEMAC — Afrique Centrale (preset leopardo-cemac v1.0.0)

> Zone CEMAC : CM (Cameroun), GA (Gabon), CG (Congo), CF (RCA), TD (Tchad), GQ (Guinée Éq.)
> Devise partagée : XAF (franc CFA BEAC)

### Niveau de maturité par pays

| Pays | Niveau | Référence légale principale |
|------|--------|---------------------------|
| CM | `pilot` | CGI 2024 art. 68, Code travail 92/007 |
| GA | `pilot` | DGI Gabon — à valider |
| CG | `pilot` | DGI Congo — à valider |
| CF, TD, GQ | `placeholder` | Non encore validé |

### Règles Cameroun (CM) — implémentées `pilot`

| Règle | Valeur | Source |
|-------|--------|--------|
| IRPP tranches annuelles | 10/15/25/35 % | CGI 2024 art. 68 |
| Centimes additionnels | IRPP × 1.10 | CGI 2024 |
| Abattement frais pro | 30 %, plafond 350 000 XAF/mois | CGI 2024 |
| Assiette IRPP | brut − CNPS salariale − abattement | CM_COMPLIANCE.md §1 |
| CNPS vieillesse salarié | 4,2 %, plafond 750 000 XAF/mois | CNPS CM |
| CNPS vieillesse patronal | 4,2 %, même plafond | CNPS CM |
| CNPS famille patronal | 7,0 %, même plafond | CNPS CM |
| CNPS AT patronal | 2,0 % (pilote), non plafonné | CNPS CM |
| SMIG CM | 41 875 XAF/mois | Décret 2014 |
| Weekend CM | Dimanche (ISO 7) | Code travail CM |

### Règles Gabon (GA) / Congo CG

Voir `docs/payroll/GA_COMPLIANCE.md` et `docs/payroll/CG_COMPLIANCE.md`.

### Contraintes de cette spec

**Si pays = CM :**
- [ ] Utiliser `CemacPayrollRules::forMemberCountry('CM')`
- [ ] `computeContribution()` avec cap 750 000 XAF
- [ ] Centimes additionnels (× 1.10) dans `calculateIncomeTax()`
- [ ] `professionalExpensesDeduction()` = `['rate' => 30.0, 'cap' => 4200000.0]`
- [ ] Golden tests dans `GoldenCmPayrollTest.php` (modèle : 17+ cas existants)

**Si pays = GA ou CG :**
- [ ] Utiliser `CemacPayrollRules::forMemberCountry('GA')` ou `'CG'`
- [ ] Plafond CNSS GA = 3 000 000 XAF / CG = 2 500 000 XAF
- [ ] Golden tests : min 6 cas par pays

**Pour tout pays CEMAC :**
- [ ] `confidenceLevel()` à `'pilot'` minimum — jamais `'placeholder'` en production
- [ ] Les autres membres CEMAC non touchés restent inchangés (test de non-régression)
