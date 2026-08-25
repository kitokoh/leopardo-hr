# Feature Specification: Paie DZ 100 % — exports virement CNEP/EDX, bordereau, DAS (Closes #5243)

**Feature Branch**: `mod/payroll/5243-exports-dz`
**Created**: 2026-08-23 | **Status**: Draft → In progress
**Issue**: #5243 (P1, payroll, ops)
**Spec**: `.specify/features/payroll-dz-exports-2026-08-23/spec.md`
**Anti-collision**: module `payroll` — uniquement `api/app/Modules/Payroll/**` (Infrastructure/Services, Infrastructure/Exports, Interfaces/Api/V1, routes, tests) + docs. Aucune collision avec `mod/payroll/5241-*` (moteur), `mod/attendance/5266-*` (HS), `mod/payroll/5245-*` (congés→paie), `fix/phpstan-command-pending-command` (perf) — fichiers distincts.

## Contexte

Le run de paie DZ doit produire tous les documents légaux : ordre de virement (formats banque), bordereau récapitulatif, état CNAS, déclaration annuelle des salaires (DAS).

**Audit de l'existant (fait le 2026-08-23) — ne pas refaire :**

| Besoin DZ | État existant | Verdict |
|---|---|---|
| Virement SEPA | `BankExportGenerator::generateSepaXml` (pain.001) | ✅ existe |
| Virement CCP Algérie Poste | `BankExportGenerator::generateCcpAlgerie` (ccp_dz) | ✅ existe |
| Virement CPA / BNA | `BankExportGenerator::generateCpaBna` (cpa_dz, bna_dz) | ✅ existe |
| Virement CSV générique / MA | `generateCsvGeneric` (csv_generic, virement_ma) | ✅ existe |
| **Virement CNEP** | ❌ absent | ➕ à créer |
| **Virement EDX (échange interbancaire)** | ❌ absent | ➕ à créer |
| État CNAS mensuel par run | `CnasDeclarationGenerator` (CSV matricule/nom/assiette/9 %/26 %) | ✅ existe |
| Déclaration CNAS trimestrielle | `POST /social-declarations/cnas-dz` (SocialDeclarationGenerator) | ✅ existe |
| **DAS — déclaration annuelle des salaires** | ❌ absent | ➕ à créer |
| **Bordereau de paie (totaux par cotisation)** | ❌ absent (journal existe, pas de bordereau) | ➕ à créer |
| Bridge export comptable | `PayrollAccountingExportService` (CSV matricule/nom/brut/déductions/net/coût) | 🟡 compléter : colonnes cotisations DZ (CNAS salariale/patronale, IRG) |
| Écritures comptables (bridge → journal) | `PayrollJournalGenerator` + journal de paie (`/payroll-runs/{run}/journal`) | 🟡 les écritures liées restent #5239 (Phase C) |

## User Stories & Testing

### User Story 1 — Virement CNEP et EDX (P1)

En tant que manager principal/comptable, je veux générer un ordre de virement au format CNEP Banque ou EDX (échange interbancaire algérien) pour un run validé/payé, afin de remettre un fichier lisible par la banque.

**Acceptance Scenarios**:
1. Given un run DZ validé avec 2 bulletins, When `POST /bank-exports {format: cnep_dz}`, Then un `BankExport` pending est créé et le job génère un fichier `.txt` pipe-delimited HEADER/DETAIL/FOOTER avec RIB, nom, montant net par employé et totaux (202).
2. Même scénario avec `edx_dz` → fichier `.txt` format enregistrements H/D/F à largeur fixe documenté, montants en DZD.
3. Given un format inconnu, Then 422 (validation `in:`).

### User Story 2 — Bordereau de paie (P1)

En tant que comptable, je veux télécharger le bordereau récapitulatif d'un run (totaux par cotisation + récap run) pour contrôler les montants déclarés.

**Acceptance Scenarios**:
1. Given un run DZ validé avec 2 bulletins (60 000 + 40 000), When `GET /payroll-runs/{run}/bordereau`, Then CSV avec section totaux par cotisation (Cotisations salariales 9 000, Cotisations patronales 26 000, Impôt sur le revenu 10 542) et section récap run (brut 100 000, net 80 458, coût employeur 126 000).
2. Given un employé non-manager, Then 403 ; run cross-tenant → 404 ; run non-DZ → 422.

### User Story 3 — Déclaration annuelle des salaires (DAS) (P1)

En tant que comptable, je veux produire la DAS d'une année (une ligne par employé : NIS, nom, salaires bruts, CNAS salariale/patronale, IRG retenu, net versé, mois) pour la transmettre aux autorités.

**Acceptance Scenarios**:
1. Given des bulletins validés DZ sur 2026, When `POST /social-declarations/das-dz {year: 2026}`, Then CSV par employé + totaux annuels cohérents (round-trip parse).
2. Given un employé inactif sans bulletin, Then absent de la déclaration.
3. Given un employé non-manager, Then 403 ; audit `payroll.das_declaration` journalisé.

## Requirements

### Functional Requirements

- **FR-001**: `BankExportGenerator` supporte `cnep_dz` et `edx_dz` (match arm + `fileExtension` txt + `mimeType` text/plain), montants nets en DZD par employé, un fichier par run validé/payé.
- **FR-002**: les validations `in:` du `BankExportController` (store + generate) et les enums OpenAPI incluent `cnep_dz`, `edx_dz`.
- **FR-003**: `DasDeclarationGenerator` produit un CSV DAS annuel (en-tête + ligne par employé + TOTAUX), agrégé depuis les bulletins validés de l'année du tenant.
- **FR-004**: `PayrollBordereauGenerator` produit le bordereau d'un run : totaux par cotisation (lignes détail groupées par nom/type) + récapitulatif run (brut, cotisations salariales, IRG, autres déductions, net, cotisations patronales, coût employeur).
- **FR-005**: `POST /social-declarations/das-dz` (manager principal/comptable, audit, année 2020-2099) et `GET /payroll-runs/{run}/bordereau` (manager, isolation tenant, pays DZ, audit) exposent les générateurs.
- **FR-006**: `PayrollAccountingExportService` ajoute les colonnes cotisations DZ (CNAS salariale, CNAS patronale, IRG) pour les runs DZ uniquement (compatibilité multi-pays préservée).
- **FR-007**: chaque générateur est couvert par un test de parsing round-trip (structure + totaux).

## Success Criteria

- **SC-001**: les 4 formats virement (cnep_dz, edx_dz) génèrent un fichier valide pour un run pilote (parse round-trip).
- **SC-002**: DAS et bordereau parsables et totaux exacts sur les données de test (60 000 + 40 000 DZD).
- **SC-003**: endpoints RBAC (403), isolation tenant (404), garde pays DZ (422), audit.
- **SC-004**: PHPStan strict (level 8) vert, Pint propre, tests Payroll verts, OpenAPI cohérente.

## Assumptions

- Les formats banque CNEP/EDX sont des conventions internes documentées (comme ccp_dz/cpa_dz/bna_dz existants) à valider avec la banque avant usage réel — même niveau de confiance `pilot` que le reste des exports DZ.
- La DAS est produite à partir des bulletins validés (`status = validated`) ; les runs non validés sont exclus.
- Les écritures comptables (bridge → journal) sont traitées par #5239 (Phase C) ; #5243 fournit les données (DAS/bordereau/export comptable enrichi).
