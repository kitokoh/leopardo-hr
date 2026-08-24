# RBAC Matrix — Module Comptabilité (Accounting)

Date : 2026-08-24 · Issue **#5226** (consolidation API Phase A) · Source de référence : `docs/architecture/COMPTABILITE_CONCEPTION.md` §5

Cette matrice documente le contrôle d'accès de la surface API `/api/v1/accounting/*` (module Accounting, 19ᵉ module DDD). Elle complète `docs/security/RBAC_ROUTE_MATRIX.md` (ligne canonique ajoutée) et sera étendue à chaque merge des PRs de trésorerie/journal en cours.

## Rôles applicables

| Rôle | Guard | Périmètre comptabilité |
|---|---|---|
| **P** (principal) | tenant manager `manager_role=principal` | Validation, paramétrage, lecture complète |
| **FIN** (comptable) | tenant manager `manager_role=comptable` | CRUD complet, trésorerie, déclarations |
| **MKT** (marketing) | tenant manager `manager_role=marketing` | Lecture des contacts issus de SES leads (via le module Marketing, #5231) |
| EMP | tenant employee | Aucun accès (403) |
| PUBLIC | non authentifié | Aucun accès hors portail partagé tokenisé (#5225, PR en cours) |

## Middleware commun

Toutes les routes `/accounting/*` mergées passent par :

```
throttle:api → auth:sanctum → token.refresh → tenant → throttle:api-plan
  └── api.manager:comptable,principal   (groupe métier)
```

- **Rate limiting** : `throttle:api` (limiteur API global) + `throttle:api-plan` (plafond par plan) — pas de limiteur spécifique comptabilité.
- **Isolation tenant** : traits `BelongsToCompany` / résolution par compagnie courante (fail-closed #3727) — jamais d'id cross-tenant résolvable (404).

## Matrice de la surface

| Surface (routes) | P | FIN | MKT | EMP | Middleware | Statut | Preuve (tests) |
|---|:---:|:---:|:---:|:---:|---|---|---|
| Contacts `/accounting/contacts` (CRUD + filtres type/search, pagination ≤ 100) | RW | RW | - | - | `api.manager:comptable,principal` | ✅ mergé (#5222) | `AccountingContactCrudTest` (10 : CRUD, filtres, isolation tenant, RBAC comptable/principal/marketing/employé, 422, chiffrement NIF) |
| Paramétrage `/accounting/settings` (GET défauts pays / PUT upsert) | RW | RW | - | - | `api.manager:comptable,principal` | ✅ mergé (#5232) | `AccountingSettingsTest` (12 : défauts, upsert, 422 ×4, RBAC, isolation, provisioning) |
| Lecture marketing du contact qualifié `/marketing/leads/{lead}/contact` | R | R | **R (lead-scoped)** | - | `api.manager:marketing,principal` (module Marketing) | ✅ mergé (#5231) | `MarketingLeadConversionTest` (8 : conversion, fallback, 404 cross-tenant, 409, RBAC) |
| Documents `/accounting/documents*` (CRUD, next-number, send, payments, cancel, credit-note) | RW | RW | - | - | `api.manager:comptable,principal` | 🔜 PR #5352/#5377 (open) | à venir avec les PRs |
| Paiements `/accounting/payments*` + `/documents/{id}/payments` + reconcile + relances | RW | RW | - | - | `api.manager:principal,comptable` | 🔜 PR #5365 (open) | à venir |
| Journal `/accounting/journal*` (+ export CSV, clôture période, post) | RW | RW | - | - | `api.manager:principal,comptable` | 🔜 PR #5363 (open) | à venir |
| Rapports TVA `/accounting/reports/vat-declaration` | RW | RW | - | - | groupe comptabilité | 🔜 PR #5384 (open) | à venir |
| Audit logs `/accounting/audit-logs` | R | R | - | - | `api.manager:principal,comptable` | 🔜 PR #5377 (open) | à venir |
| Portail client partagé `/accounting/documents/shared/{token}` | PUBLIC (token 64 car., exp. 14 j) | PUBLIC | PUBLIC | PUBLIC | token-bound, aucun Sanctum | 🔜 PR #5357 (open) | à venir |

## Décisions et gaps documentés

1. **Marketing** : l'accès « lecture des contacts issus de ses leads » (conception §5) est porté par le module Marketing (`GET /marketing/leads/{lead}/contact`, #5231) — **pas** d'élargissement du CRUD `/accounting/contacts` au rôle marketing (un contact manuel/comptable n'est pas « issu d'un lead »). Le gap « liste des contacts d'un lead pour marketing » n'est pas requis en Phase A.
2. **Principal vs comptable** : la Phase A conserve un accès CRUD équivalent (validation métier différenciée prévue en Phase B — paiements #5229 : `principal` exécute les paiements après contrôle comptable).
3. **Rate limiting** : aucun limiteur dédié « comptabilité » (les opérations sensibles restent sous `throttle:api-plan`) — à réévaluer avec le portail client public (#5225) qui apporte ses propres gardes (token, expiration, throttle dédié).
4. **DoD #5226 — « aucun endpoint sans test »** : vérifié sur la surface mergée (contacts 10 tests, settings 12, conversion marketing 8, modèle/relations/isolation/perf/numbering couverts — voir `api/tests/Feature/Accounting/`). Les endpoints des PRs ouvertes (documents/payments/journal/TVA/audit) seront couverts par leurs propres suites avant merge.
