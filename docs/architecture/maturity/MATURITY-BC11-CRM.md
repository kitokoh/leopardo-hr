# Rapport de maturité — BC-11 CRM

> **DEP-BC11 (issue #5887)** — Deep maturity, BC-11 Customer CRM.
> Audité le 2026-08-28 (main `228c382`). Agent propriétaire : 11.
> Cadre : `docs/architecture/BOUNDED-CONTEXT-DEEP-MATURITY-BACKLOG.md` (12 dimensions).
> Registre : `dev-hub/governance/bounded-context-registry.json` (BC-11).

## Périmètre

CRM **client** (tenant) : accounts, contacts, leads, opportunités, activités,
tâches et pipelines du tenant — `api/app/Modules/CRM`. Distinction stricte
d'avec le **CRM commercial Leopardo** (BC-01, Platform/Marketing, schéma
public) : ADR-CRM-002, matrice `CRM_API_MATRICE_TENANT_PLATFORM.md`,
`CrmPlatformIsolationTest`. Programme V0/V1 en cours (issues #5705→#5731).

## Verdict par dimension

| # | Dimension | Verdict | Preuves / constats |
|---|---|---|---|
| D1 | Domaine | 🟡 PARTIEL | DDD complet en place (Application/Actions, Domain/Contracts+Enums+Exceptions+Models, Infrastructure/Jobs+Repositories+Services). Vocabulaire CRM client documenté (specs PLAN-V0-V1-CRM-CLIENT, ADR dual-contexts). Programme V0 partiellement livré. |
| D2 | Données | 🟡 PARTIEL | Migrations tenant CRM (accounts/contacts/leads/pipelines/opportunités/tasks/imports/outbox). Index dédiés ; couverture V0 en cours (imports, dédup). |
| D3 | Tenant | 🟢 PRÉSENT | 100 % tenant-scopé (BelongsToCompany), isolation prouvée (`CrmPlatformIsolationTest`, outbox tenant), aucun accès au CRM commercial (ADR-CRM-002). |
| D4 | API | 🟡 PARTIEL | 3 contrôleurs sur main (Lead, Import, Dedup) + 9 routes déclarées ; le programme V1 (recherche, dashboard, campagnes, canaux) est en cours via PRs agents ; OpenAPI couvert sur les routes existantes. |
| D5 | Autorisation | 🟢 PRÉSENT | Policies CRM + gardes manager/principal, consentements (BC-12), séparation des surfaces platform/tenant (matrice #5737). |
| D6 | Transactions | 🟡 PARTIEL | Outbox persistée après commit (CrmOutboxTest : zéro doublon au replay), conversion lead → opportunité testée ; pipeline/états V0 partiellement versionnés. |
| D7 | Asynchronisme | 🟢 PRÉSENT | Outbox CRM (`crm_outbox_events`, consumer registry, retry/backoff/DLQ, commande `crm:outbox-dispatch`) — solide. |
| D8 | Sécurité | 🟢 PRÉSENT | PII CRM : HMAC lookup + registre RGPD (#5713), consentements, pas de secret dans les fixtures (CrmPilotSeeder déterministe). |
| D9 | Frontend | 🟡 PARTIEL | Client mobile CRM terrain (#5730 en cours) ; pas d'UI web tenant dédiée sur main. |
| D10 | Performance | 🟡 PARTIEL | Imports groupés + benchmark seeder séparé (`benchmark-crm-dz`) ; budgets p95 non versionnés (MAT-014 en cours). |
| D11 | Exploitation | 🟢 PRÉSENT | Runbook files CRM (`RUNBOOK_FILES_CRM.md`), outbox supervisée, observabilité queue. |
| D12 | Produit | 🟡 PARTIEL | Parcours account → contacts → leads → opportunités → tâches seedé (CrmPilotSeeder, déterministe) + 43 tests locaux verts ; V1 en cours. |

## Vérification locale (preuve)

```
php artisan test tests/Feature/Crm/
→ 43 passed (180 assertions)
```

## Recommandations (PR futures, non bloquantes)

1. **Coordination programme** : le DEP-BC11 recoupe le programme CRM V0/V1
   (#5705→#5731) déjà piloté par d'autres agents — les PRs de maturité BC-11
   doivent se limiter aux invariants transverses (isolation, outbox,
   consentements) et ne pas dupliquer le V1.
2. **États de pipeline versionnés** (D6) : formaliser les transitions
   (lead → opportunité → gagnée/perdue) en tests d'invariants dédiés.
3. **UI web tenant** (D9) : une fois le V1 API mergé, prévoir la surface
   web (espaces clients) avec les mêmes gardes de séparation.

## Non-régression

Aucun code de production modifié. Rapport + vérifications uniquement. Le CRM
commercial Leopardo (BC-01) n'est pas touché.
