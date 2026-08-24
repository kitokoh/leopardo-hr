# Feature Specification: Moteur multi-pays — plan comptable par pays + écritures salariales équilibrées (Closes #5256)

**Feature Branch**: `mod/payroll/5256-multi-country-accounting`
**Issue**: #5256 (P1, payroll, data-model)
**Created**: 2026-08-24
**Status**: Approved

## Contexte

Le moteur de paie multi-pays est le socle de la prod : le registre officiel des
pays existe (`CountryDefaults`, 21 pays), mais il manque le **plan comptable par
pays** (équivalents 641/645/421/431/4421) et le **pont vers la comptabilité** :
un `PayrollRun` validé doit pouvoir produire des **écritures salariales
équilibrées** (débit = crédit), consommées par le module Accounting en Phase C
(#5239). Aucune modification du moteur de calcul (FOCUS) — le service d'export
est en lecture seule sur les bulletins validés.

Références : `docs/architecture/COMPTABILITE_CONCEPTION.md` §6.3 (écritures
D 641 / D 645 / C 421 / C 431 / C 4421) · `docs/plan/PLAN_100PCT.md` (#5256).

## User Stories

### US1 — Un run validé produit un journal comptable équilibré par pays (P1)

En tant que comptable, je valide un run de paie dans un pays supporté et
j'obtiens les écritures salariales du run : débits = crédits, avec les codes
comptes du référentiel du pays.

**Pourquoi P1** : c'est le DoD central de #5256 (« Tous les pays déclarés
produisent un export comptable cohérent ») et le contrat consommé par #5239.

**Test indépendant** : `PayrollAccountingExportJournalTest` — pour chaque pays
du registre, un run avec bulletins validés produit un jeu d'écritures dont la
somme des débits égale la somme des crédits (par bulletin ET par run), avec les
codes comptes attendus du pays.

**Acceptance Scenarios**:

1. **Given** un `PayrollRun` validé avec bulletins `validated` (DZ), **When** je
   génère le journal, **Then** les écritures utilisent 641/645/421/431/4421 et
   débits = crédits.
2. **Given** un run avec bulletins non validés (`calculated`), **When** je
   génère le journal, **Then** seuls les bulletins `validated` produisent des
   écritures (règle #2223, cohérente avec l'export CSV).
3. **Given** un pays sans plan comptable explicite (ex. GA), **When** je génère
   le journal, **Then** le plan dérivé documenté est utilisé (SYSCOHADA via CM)
   et le journal reste équilibré.
4. **Given** des déductions personnalisées (ex. avance), **When** je génère le
   journal, **Then** le solde est crédité sur le compte « autres déductions »
   et l'équilibre est conservé.

### US2 — Le plan comptable par pays est documenté et traçable (P1)

En tant que responsable conformité, je connais la source de chaque code compte
(PCG, PCN, SYSCOHADA, THP, pratiques UK/US) et son niveau de confiance.

**Test indépendant** : le registre `PayrollCountryChartOfAccounts` couvre les
21 pays de `CountryDefaults` (explicites ou dérivés) et chaque entrée porte une
référence de référentiel.

## Requirements

- **FR-001** — `PayrollCountryChartOfAccounts` (Payroll module,
  `Infrastructure/Services/CountryRules/`) : registre immuable des codes comptes
  salariaux par pays — `salary_expense`, `employer_charges`, `net_payable`,
  `social_contributions`, `income_tax_withheld`, `other_deductions` — avec
  `forCountry()`, `all()`, couverture des 21 pays de `CountryDefaults` (10
  explicites DZ/MA/TN/SN/CI/CM/TR/FR/GB/US/CA + dérivés SYSCOHADA CEMAC/CEDEAO),
  `confidenceLevel` ('production' | 'pilot') et référence du référentiel.
- **FR-002** — `PayrollAccountingExportService::journalLines(PayrollRun): array`
  (lecture seule) : écritures équilibrées par bulletin validé —
  D `salary_expense` (brut), D `employer_charges` (cotisations patronales),
  C `net_payable` (net), C `social_contributions` (salariales + patronales),
  C `income_tax_withheld` (impôt + taxe forfaitaire), C `other_deductions`
  (reste, si > 0). Décomposition déterministe par type/lignes de bulletin :
  `Cotisations salariales` → social, composants personnalisés
  (`salary_component_id` non nul) → autres, impôt = résidu
  (`total_deductions − social − autres`) → équilibre garanti par construction.
- **FR-003** — chaque ligne porte `date`, `company_id`, `payroll_run_id`,
  `pay_slip_id`, `employee_id`, `account_code`, `account_label`, `debit`,
  `credit`, `reference` (corrélation du run) ; montants arrondis à 2 décimales ;
  débit OU crédit exclusif (jamais les deux).
- **FR-004** — tests d'intégration `PayrollAccountingExportJournalTest` :
  golden par pays (DZ/MA/TN/SN/CI/CM/TR/FR/GB/US) — équilibre par bulletin et
  par run, codes comptes attendus, exclusion des bulletins non validés,
  déductions personnalisées, pays dérivés.
- **FR-005** — documentation `docs/payroll/MULTI_PAYS_PLAN_COMPTABLE.md` :
  tableau par pays des comptes et référentiel source ; CHANGELOG en tête
  d'[Unreleased].

## Non-objectifs

- Aucune modification du moteur de calcul (FOCUS), ni des règles pays.
- Aucune persistance d'écritures dans Accounting (Phase C, #5239) — le service
  produit les lignes ; #5239 les consommera.
- Pas de rapprochement bancaire, pas de multi-devises, pas de TVA.

## Success Criteria

- `php artisan test --filter=PayrollAccountingExportJournal` vert.
- PHPStan strict (level 8) : 0 erreur sur le delta de la branche.
- Pint : formatage conforme.
- Les 21 pays de `CountryDefaults` produisent un journal équilibré (couvert par
  test unitaire du registre + tests d'intégration par pays).
- CHANGELOG + doc `MULTI_PAYS_PLAN_COMPTABLE.md` + PR `Closes #5256`.
