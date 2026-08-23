# Spécification — Contrats par pays DZ/MA/TN/SN (Issue #5260)

**Issue** : [#5260 [P1][HR][contract] Contrats par pays — modèles DZ/MA/TN/SN + amendements + archivage](https://github.com/kitokoh/leopardo-hr/issues/5260)
**Date** : 2026-08-22 · **Module** : HR · **Branche** : `mod/hr/5260-contracts-by-country`
**Anti-collision** : module HR (propriété agent PM, cf. #5259) — touches UNIQUEMENT `api/app/Modules/HR/**`, `routes/modules/hr_extended.php`, `api/lang/*`, `api/resources/views/pdf/contract.blade.php`, `api/openapi.yaml` + dérivés.

## 1. État des lieux sur `main`

| Périmètre de #5260 | État |
|---|---|
| Cycle de vie contrat (draft → active → suspend/terminate/renew) | ✅ `ContractController` + `ContractLifecycleAction` (#3891) |
| Amendements (list/store) | ✅ `contract_amendments` + routes |
| Expirations (`GET /contracts/expiring`) | ✅ |
| Génération PDF | ✅ `ContractPdfGenerator` (Cabinet, via `ContractDocumentGeneratorInterface`) |
| **Modèles légaux par pays (clauses DZ/MA/TN/SN)** | ❌ **Absent** |
| **Signature « explicite » dédiée** | ❌ `activate` signe implicitement ; pas d'endpoint `sign` |

## 2. Besoin métier

1. Un manager récupère les **clauses légales types d'un pays** (`GET /contracts/templates?country=DZ`) pour bâtir un contrat conforme.
2. À la création d'un contrat, si aucune clause explicite n'est fournie, le contrat est **semé avec les clauses légales du pays** de l'entreprise (dérivé de la société de l'employé) → le PDF généré porte ces clauses.
3. **Signature explicite** : `POST /contracts/{id}/sign` enregistre la validation (date + document éventuel), sans activer le contrat.
4. **Historique complet testé** : amendements listés chronologiquement, expirations listées, transitions verrouillées.

## 3. Modèles légaux — `ContractCountryTemplates` (service)

`api/app/Modules/HR/Infrastructure/Services/ContractCountryTemplates.php` :
- `supportedCountries(): array` → `['DZ', 'MA', 'TN', 'SN']`
- `forCountry(string $country, string $contractType = 'cdi'): array` → bundle :
  - `country` (ISO), `contract_type`, `legal_references` (codes du travail : DZ loi 90-11, MA loi 65-99, TN loi 96-62, SN loi 97-17)
  - `probation` (période d'essai CDI/CDD), `notice_period`, `annual_leave`, `overtime`, `minimum_wage`, `social_security` — résumés sourcés (articles cités)
  - `clauses` : tableau `[{title, body}]` de clauses rédactionnelles types (CDI/CDD) — **modèles à faire relire par un expert légal (DoD « revue légale » explicite dans le spec + PR)**
- Chaque valeur porte un commentaire de source (article + texte).

⚠️ **Honnêteté légale** : les clauses sont des résumés structurés référençant les textes, PAS des consultations juridiques. Le DoD « un contrat généré est conforme (revue légale) » est traité par : mécanisme livré + sources citées + gate de revue documentée (`docs/specifications/ISSUE_5260_CAREER_TEMPLATES.md` §6).

## 4. API

| Méthode | Route | Acteur | Comportement |
|---|---|---|---|
| GET | `/contracts/templates?country=DZ` | principal/rh (manager) | bundle du pays ; 422 si pays non supporté |
| POST | `/contracts/{contract}/sign` | principal/rh | `signed_at` = now (+ `signed_document_path` optionnel) ; idempotent si déjà signé ; 404 cross-tenant |
| POST | `/contracts` (store enrichi) | principal/rh | `apply_legal_template` (défaut true si `clauses` absentes) : seed des clauses légales du pays de l'entreprise de l'employé |

Le pays est dérivé de `employee.company.country` (char(2)) — pas de nouvelle colonne.

## 5. PDF

`resources/views/pdf/contract.blade.php` : nouvelle section « Clauses légales » rendant `$contract->clauses` (titre + corps par clause) quand non vide — les contrats semés portent les clauses du pays.

## 6. i18n ×4

- `pdf.contract_legal_clauses_title` (titre de section PDF)
- `employees.contract_template_not_found` / `contract_signed` / `contract_already_signed` (erreurs/succès API)

## 7. Tests (Feature HR, RefreshTenantDatabase)

- Templates : bundle DZ/MA/TN/SN (réfs légales + clauses non vides) ; pays inconnu → 422 ; RBAC employé → 403 ; isolation tenant
- Store : sans `clauses` → semé avec le template du pays (entreprise DZ → clauses DZ) ; `clauses` explicites → non écrasées ; `apply_legal_template=false` → pas de seed
- Sign : draft → `signed_at` posé ; idempotent ; employé → 403 ; cross-tenant → 404
- Amendements : historique complet (store + list ordonnée) ; expiring listé

## 8. DoD

- [x] Mécanisme de modèles par pays livré (données sourcées) — revue légale formelle à planifier (gate documentée)
- [x] Historique complet testé (amendements + transitions)
- [x] OpenAPI documenté (2 nouvelles opérations + store enrichi) + SDK regénéré
