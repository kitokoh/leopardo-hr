---

## 🔴 Conformité Paie Leopardo HR (preset leopardo-payroll v1.0.0)

> Ces contraintes s'appliquent à toute spec touchant `api/app/Modules/Payroll/`.
> Cocher avant de passer à `/speckit-tasks`.

### Pays & Référence légale
- **Pays ciblé** : [CODE ISO — DZ | CM | CI | SN | GA | CG | BF | ML]
- **Zone** : [Maghreb | CEMAC | CEDEAO]
- **Niveau actuel** : [placeholder | pilot | production]
- **Référence** : `docs/payroll/{PAYS}_COMPLIANCE.md`
- **Référence légale principale** : [ex. CGI Cameroun art. 68 / DZ Loi 90-11]

### Checklist conformité (NON NÉGOCIABLE)

**Calculs**
- [ ] Taux fiscal : tranches réelles du pays (pas placeholder)
- [ ] Cotisations sociales : taux + `cap` correct via `computeContribution()`
- [ ] Abattement frais pro déclaré dans `professionalExpensesDeduction()` si applicable
- [ ] Centimes additionnels / TRIMF / taxe équivalente dans `calculateBracketTax()` si applicable
- [ ] 13ème mois dans `thirteenthMonthMandatory()` si applicable

**Golden tests (minimum 3)**
- [ ] SMIG du pays → calcul manuel commenté dans le test
- [ ] Cadre moyen → IRG/IRPP + cotisations + net documentés
- [ ] Plafond cotisation : brut > cap → cotisation sur cap seulement
- [ ] Référence : `GoldenDzPayrollTest.php` comme modèle

**Isolation tenant**
- [ ] Tout endpoint paie : test `assert 404 cross-tenant`
- [ ] Toutes les requêtes : `->where('company_id', ...)`

**CI**
- [ ] PHPStan strict `[OK] No errors` prévu
- [ ] Coverage Payroll ≥ 80 % maintenu
- [ ] `docs/payroll/{PAYS}_COMPLIANCE.md` créé ou mis à jour
- [ ] CHANGELOG.md mis à jour dans la PR
