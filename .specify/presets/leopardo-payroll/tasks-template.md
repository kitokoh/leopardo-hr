# Tasks: [FEATURE NAME]

**Spec**: [SPEC FILE]
**Plan**: [PLAN FILE]
**Created**: [DATE]

---

> **🔴 LEOPARDO PAYROLL PRESET — Tâches obligatoires injectées automatiquement**

## Tâches Payroll Obligatoires (avant tout autre travail)

### T-0 : Lire les références légales
- [ ] Lire `docs/payroll/{PAYS}_COMPLIANCE.md` intégralement
- [ ] Identifier les taux applicables : fiscal + social + plafonds
- [ ] Vérifier que `confidenceLevel()` du pays est à jour

### T-1 : Compliance doc (si nouveau pays ou mise à jour)
- [ ] Créer ou mettre à jour `docs/payroll/{PAYS}_COMPLIANCE.md`
- [ ] Documenter chaque taux avec sa référence légale et sa date de validité
- [ ] Ajouter un exemple chiffré calculé à la main pour chaque règle

### T-2 : Règles pays (`{Pays}PayrollRules.php`)
- [ ] Implémenter `defaultTaxSlabs()` avec les tranches réelles (pas placeholder)
- [ ] Implémenter `calculateIncomeTax()` avec abattement et centimes additionnels si applicables
- [ ] Implémenter `socialContributions()` avec `cap` correct pour chaque cotisation
- [ ] Utiliser `computeContribution()` dans `calculateSocialCharges()` — pas de calcul inline
- [ ] Implémenter `professionalExpensesDeduction()` si abattement frais pro applicable
- [ ] Implémenter `calculateBracketTax()` si TRIMF ou taxe équivalente
- [ ] Implémenter `thirteenthMonthMandatory()` si 13ème mois légalement obligatoire
- [ ] Implémenter `noticePeriodDays()` avec durées légales par ancienneté
- [ ] Passer `confidenceLevel()` à `'pilot'`

### T-3 : Golden tests (NON NÉGOCIABLE — avant merge)
- [ ] Créer `api/tests/Feature/Payroll/Golden/Golden{Pays}PayrollTest.php`
- [ ] Test SMIG : calcul manuel commenté, valeur nette exacte
- [ ] Test cadre moyen : IRPP/ITSAS + cotisations + net documentés
- [ ] Test plafond cotisation : brut > plafond → cotisation sur plafond
- [ ] Test prorata entrée milieu de mois
- [ ] Test heures supplémentaires (si applicable)
- [ ] Test fin de contrat (préavis + sévérance si implémentés)
- [ ] DataProvider pour les cas tabulaires (tranches fiscales)

### T-4 : Seeder DB
- [ ] Ajouter les `TaxSlab` et `SocialContribution` dans `PayrollCountryConfigSeeder`
- [ ] `effective_from = '2024-01-01'` par défaut
- [ ] Vérifier idempotence (no duplicate on re-seed)

### T-5 : Tests d'isolation tenant
- [ ] Ajouter test cross-tenant sur tout nouvel endpoint paie : `assert 404`
- [ ] Vérifier que `company_id` scoping est présent dans tous les `query()`

### T-6 : Validation PHPStan + CHANGELOG
- [ ] `vendor/bin/phpstan analyse --configuration phpstan-strict.neon` → `[OK] No errors`
- [ ] Entrée CHANGELOG.md sous `## [Unreleased]` avec le changement documenté

---

## Tâches Feature Spécifiques

<!-- Les tâches propres à cette spec viennent ici, générées par /speckit-tasks -->

### [TASK_1]
[DESCRIPTION]

### [TASK_2]
[DESCRIPTION]

---

## Definition of Done (DoD)

- [ ] Tous les golden tests verts en local
- [ ] PHPStan strict `[OK] No errors`
- [ ] Coverage Payroll ≥ 80 % maintenu
- [ ] Test cross-tenant 404 présent
- [ ] `docs/payroll/{PAYS}_COMPLIANCE.md` à jour
- [ ] CHANGELOG.md mis à jour
- [ ] PR title suit le format : `feat(payroll): description (Closes #numero)`

*Tasks générées avec le preset `leopardo-payroll` v1.0.0*
