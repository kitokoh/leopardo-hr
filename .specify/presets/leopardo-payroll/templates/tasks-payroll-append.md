---

## 🔴 Tâches Payroll Obligatoires (preset leopardo-payroll v1.0.0)

Ces tâches sont **non négociables** pour toute PR touchant `api/app/Modules/Payroll/`.

### P-1 : Compliance doc
- [ ] `docs/payroll/{PAYS}_COMPLIANCE.md` créé ou mis à jour
- [ ] Chaque taux porte une référence légale et une date de validité

### P-2 : Règles pays
- [ ] `{Pays}PayrollRules.php` implémenté avec taux réels
- [ ] `computeContribution()` utilisé dans `calculateSocialCharges()` — jamais de calcul inline
- [ ] `confidenceLevel()` à `'pilot'` minimum

### P-3 : Golden tests (AVANT merge)
- [ ] `GoldenDzPayrollTest.php` comme modèle de format
- [ ] Test SMIG : calcul entier commenté, net exact
- [ ] Test plafond : brut > cap → cotisation sur cap
- [ ] Test prorata entrée milieu de mois
- [ ] DataProvider pour les cas tabulaires

### P-4 : Isolation tenant
- [ ] Test `assert 404 cross-tenant` sur tout nouvel endpoint
- [ ] `company_id` scope présent dans toutes les requêtes Eloquent

### P-5 : PHPStan + CHANGELOG
- [ ] `vendor/bin/phpstan analyse --configuration phpstan-strict.neon` → 0 erreurs
- [ ] Entrée CHANGELOG.md sous `## [Unreleased]`
