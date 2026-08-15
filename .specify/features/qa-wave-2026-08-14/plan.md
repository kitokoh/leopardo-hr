# Plan: QA Wave 2026-08-14 — Fiabilisation backend + UI admin

**Input**: spec.md + Constitution + registre project-state

## Décisions techniques

- **Tests paie (US1)** : aligner les unit tests ET les goldens sur la vérité documentée/golden :
  - Réforme ITS 2024 CI (#1918) : ITS mensuel unique, CN abolie → `calculateBracketTax` = 0
  - CNSS CI : plafonds branche 70 000 (guide CNPS, CI_COMPLIANCE.md §4) → SMIG 8 800,00 · 500 000 → 27 925,00
  - CSS SN : plafond branche 63 000 (#1913) → T1 patronal 51 768,00
  - GA pilot (#1824) : 8 tranches IRPP, CNSS plafonnée 3 000 000, préavis OHADA 30 j ; BF/ML pilot (#1829)
  - Résolveur #1868 : `UnsupportedCountryRulesException`
  - **Bug d'implémentation corrigé** : `CedeaoPayrollRules::noticePeriodDays()` aligné sur CI_COMPLIANCE.md §8 (catégorie × ancienneté, suppression du palier 90 j non documenté pour le défaut employé)
- **Login admin (US2)** : suppression des liens morts, `mailto:support@leopardo-rh.com` canonique
- **Coordination multi-agents** : issues #2288/#2289/#2290 auto-assignées ; le correctif PHPStan 43 erreurs a été livré en parallèle par un autre agent (commit 0f1ea1ee) — ce feature ne porte QUE le reliquat unique (tests paie + goldens + login admin)

## Fichiers touchés (référence)

- `api/app/Modules/Payroll/Infrastructure/Services/CountryRules/CedeaoPayrollRules.php`
- `api/tests/Unit/{AbstractCountryRulesCapTest,CedeaoRulesUnitTest,CemacRulesUnitTest,PayrollCalculatorUnitTest}.php`
- `api/tests/Unit/Payroll/CedeaoRulesUnitTest.php`
- `api/tests/Feature/Payroll/Golden/{GoldenCiPayrollTest,GoldenSnPayrollTest}.php`
- `api/tests/Feature/Payroll/PayrollCalculationContractTest.php`
- `front/admin-dashboard/src/views/auth/LoginView.vue`
- `scripts/qa_api_smoke.py`, `scripts/qa_api_write_workflows.py` (harnais QA réutilisable)
- `CHANGELOG.md`, `AGENTS.md`

## Contraintes

- PHPStan Strict level 8 = 0 erreur ; Pint diff-aware vert ; `Closes #issue` dans chaque PR ; CHANGELOG à jour ; jamais de push direct sur main.
