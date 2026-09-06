# Payroll — Cartographie des flux vers une couche Application (v1)

> Livrable d'étape de **#6896** (Part of #6841) — décision de périmètre +
> cartographie. État mesuré sur `main` (2026-09-06). Décision : **ADR-0020 D3** —
> la couche Application de Payroll se construit par **extraction de cas d'usage
> nommables** depuis les contrôleurs épais (#6569), par lots ordonnés, sans
> changement de comportement (pattern Expense #6894 / Planning #6895/#6906).
> Les Actions orchestrent les services Infrastructure **existants** ; la
> persistance et la politique métier restent dans les services.
> Cette cartographie est une **v1 de travail** : à confirmer fichier par
> fichier au moment de chaque extraction (les méthodes listées sont celles
> observées le 2026-09-06).

## État mesuré

- Module : 133 PHP — Domain 41 / Infrastructure 52 / Interfaces 38.
- `Application/` : 1 Service (`PayrollRegularizationService`) ; **0 Action** ;
  pas de `Actions/` ni `DTOs/`.
- `Infrastructure/Services/` (~30 services) : PayrollService, PayrollCalculator,
  PayrollCycleService, PayrollClosingService, PayrollWorkInputAggregator,
  PayrollAnomalyService, PayrollJournalGenerator, PayrollBordereauGenerator,
  PayrollAccountingEntryService, PayrollPaymentOrderService,
  PaymentConsentSignatureService, PaySlipPdfGenerator, CommissionService,
  EndOfContractService, IslamicCalendarService, LedgerService,
  BankExportGenerator, déclarations par pays (CnasDZ, DasDZ, CnssMA, DsnFR,
  Cedeao/Cemac ×11 générateurs), CountryRules (barèmes pays), etc.
- Routes : `api/routes/modules/payroll_engine.php` (+ segments dans `rh.php`,
  `hr_extended.php`, `api.php`).

## Contrôleurs épais prioritaires (mesure brute, lignes)

| Contrôleur | Lignes | Rôle | Cluster |
|---|---|---|---|
| `PayrollRunController` | 664 | cycle de paie (store→validate→lock→close) | **A — Cycle de paie** |
| `SocialDeclarationController` | 557 | déclarations sociales par pays (11 générateurs) | **B — Déclarations** |
| `SalaryAdvanceController` | 433 | avances : cycle d'approbation complet | **C — Avances** |
| `PaySlipController` | 394 | bulletins : lecture, PDF, envoi | **D — Bulletins** |
| `PaymentBatchController` | 337 | batch de paiement : store→paid→confirm | **E — Paiements** |
| `TaxSlabAdminController` | 333 | référentiels barèmes (admin) | F — Référentiels |
| `PayrollCycleController` | 289 | settings de cycle, soldes, résumés | A / lecture |
| (28 contrôleurs au total, ~6 556 lignes) | | | |

## Clusters → candidats Actions (ordre d'extraction proposé)

### Lot 1 — Cycle de paie (`PayrollRunController` + `PayrollCycleController`) — le plus critique
Flux : création de run → calcul → validation → clôture/lock → régularisation →
exports (journal, bordereau, anomalies).
Candidats Actions :
- `StartPayrollRunAction` (store : ouverture période + verrou anti-doublon)
- `CalculatePayrollRunAction` (calculate — orchestre PayrollCalculator +
  PayrollCalculationAuditRecorder)
- `ValidatePayrollRunAction` (validateRun — politique + verrous + événements)
- `CancelPayrollRunAction` (cancel — libération #2329-like)
- `LockPayrollRunAction` / `UnlockPayrollRunAction` (lock/unlock)
- `RegularizePayrollRunAction` (regularize — s'appuie sur
  `PayrollRegularizationService` existant en Application/)
- `GeneratePayrollJournalAction` / `GeneratePayrollBordereauAction` /
  `DetectPayrollAnomaliesAction` (journal, bordereau, anomalies)
- `UpdatePayrollCycleSettingsAction` (PayrollCycleController)
*Lecture pure (index/show/summary/soldes/mobileSummary) : reste au contrôleur
avec délégation service — pas d'Action pour les requêtes simples (règle
interne, cf. Expense/Planning).*

### Lot 2 — Avances de salaire (`SalaryAdvanceController`, 433 l.)
Cycle : store → approve/reject (manager) → markPaid → confirmReceived →
dispute → resolveDispute (+ downloadProof).
Candidats : `RequestSalaryAdvanceAction`, `ApproveSalaryAdvanceAction`,
`RejectSalaryAdvanceAction`, `MarkSalaryAdvancePaidAction`,
`ConfirmSalaryAdvanceReceivedAction`, `DisputeSalaryAdvanceAction`,
`ResolveSalaryAdvanceDisputeAction`. (Le module voisin `Planning` a servi de
patron pour un cycle approbation : #6895/#6906.)

### Lot 3 — Déclarations sociales (`SocialDeclarationController`, 557 l.)
Un use case par pays/générateur :
`GenerateCnasDzDeclarationAction`, `GenerateDasDzDeclarationAction`,
`GenerateCnssMaDeclarationAction`, `GenerateDsnFrDeclarationAction`,
`GenerateCnssCiDeclarationAction`, `GenerateIpresSnDeclarationAction`,
`GenerateCnpsCmDeclarationAction`, `GenerateCnssGaDeclarationAction`,
`GenerateCnssCgDeclarationAction`, `GenerateCnssBfDeclarationAction`,
`GenerateInpsMlDeclarationAction`.
Chaque Action délègue au générateur `Infrastructure/Services/*DeclarationGenerator`
correspondant (lecture employés via `Employee`, identifiants entreprise via
`companies.metadata`, casts `encrypted` — conventions existantes).

### Lot 4 — Paiements & exports bancaires
`PaymentBatchController` (batch store→paid→confirm), `PayrollPaymentOrderController`
(ordres de paiement), `BankExportController` (RIB/iban, formats bancaires),
`BulkPaymentController`, `PaymentDocumentController`.
Candidats : `CreatePaymentBatchAction`, `MarkPaymentBatchPaidAction`,
`ConfirmPaymentBatchAction`, `GenerateBankExportAction`,
`GeneratePaymentOrderAction`, `GeneratePaymentDocumentAction`.

### Lot 5 — Bulletins (`PaySlipController`)
Candidats : `GeneratePaySlipPdfAction` (downloadPdf — PaySlipPdfGenerator),
`SendPaySlipsAction` (envoi), `GetPaySlipDocumentAction` (document).
Lecture (index/myPaySlips/show) : reste interface + service.

### Lot 6 — Simulation / estimation / référentiels (admin) — dernier
`PayrollSimulationController`, `CotisationSimulationController`,
`EstimationController`, `TaxSlab{Admin,}Controller`, `SalaryStructureController`,
`SalaryComponentController`, `SocialContribution{Admin,}Controller`,
`RateValidationAdminController`, `PayrollAccountingController`, `LedgerController`,
`PayrollAuditController`, `EmployeeLoanController`, `EndOfContractController`,
`IslamicCalendarController`, `PublicHolidayController` — périmètre à arbitrer
lot par lot (beaucoup sont CRUD de référentiel → les Actions n'apportent de la
valeur que sur les flux à politique métier).

## Règles d'extraction (rappel ADR-0020 / précédents)

1. Une Action = un cas d'usage nommable, orchestration pure, **aucune
   persistance directe** (délégue aux services/ports existants).
2. **Aucun changement de comportement** : autorisations et validation restent
   au niveau interface ; les tests existants (448 golden paie DZ, Feature
   controllers) doivent passer sans modification autre que cosmétique.
3. Cliquets : PHPStan strict 0 sur delta, baseline **inchangée**, layer purity
   (#6568) 0 violation nouvelle, Pint OK, coverage ≥ 65 %.
4. Chaque lot = sa propre PR (`fix/<issue>-<slug>` ou suite dédiée), tests
   ciblés ajoutés, entrée CHANGELOG. Issue #6896 close quand tous les lots
   utiles sont livrés.
