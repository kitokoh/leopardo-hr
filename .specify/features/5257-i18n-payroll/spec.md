# Feature Specification: i18n paie ×4 — bulletins, exports, UI (issue #5257)

**Feature Branch**: `mod/payroll/5257-i18n-payroll-x4`
**Created**: 2026-08-23 · **Status**: Implémentée (PR en cours)

## 1. Contexte
Règle #2755 : aucune chaîne hardcodée utilisateur dans le module Payroll —
UI + bulletins + exports en fr/ar/tr/en. La locale entreprise est déjà
appliquée dans le PDF (`I18nCatalog::normalizeLocale(employee.preferred_language
?? company.language)`) et la vue bulletin est déjà i18n (29 appels `__()`).

## 2. Inventaire (main 2026-08-23)
- ~26 littéraux `message => '...'` dans les contrôleurs Payroll (dont workflow
  avances, paiement en masse, exports bancaires).
- 8 noms de lignes de bulletin créés en dur par le moteur (Salaire de base,
  Heures supplémentaires, Indemnité de congés payés, 13ème mois, Allocations
  familiales, Cotisations salariales, Impot sur le revenu, Cotisations
  patronales) + `flatPayrollTaxLabel()`.
- Exports légaux (CNAS/CNSS/DSN) = formats officiels imposés par les
  autorités → NON traduisibles (documenté, hors périmètre).

## 3. Changements
1. **Messages API** → clés `payroll.*` (×4) ; valeurs EN = littéraux
   historiques (aucune régression client) ; locale requête/entreprise servie.
2. **Noms de lignes** → `PayrollLineLabels` (map libellé→clé `line_*`) +
   accessor `PaySlipLine::label` ; rendu API (bloc `label` additif par ligne)
   et PDF via accessor (contourne le double-backslash Blade). Repli sur le
   libellé brut pour les composants personnalisés.
3. **Garde CI** `dev-hub/tools/check-payroll-i18n.py` : littéraux non
   localisés dans le module + parité des clés ×4 + libellés connus présents
   dans les catalogues — job `i18n-scan` dans `payroll-ci.yml`.
4. Tests : `PayrollI18nTest` (parité ×4, labels + fallback, stabilité EN,
   contenu ×4) ; assertions de messages mises à jour vers les valeurs
   localisées (locale entreprise par défaut fr).

## 4. DoD
- [x] 0 chaîne hardcodée utilisateur dans le module Payroll (scan CI vert,
      hors logs/dev et formats légaux).
- [x] Parité des clés ×4 vérifiée (test + scan).
- [x] Suite Payroll verte, PHPStan strict 0 erreur, Pint PASS.
