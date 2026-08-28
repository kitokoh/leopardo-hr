# Rapport de maturité — BC-02 TENANT

> **DEP-BC02 (issue #5878)** — Deep maturity, BC-02 Tenant & Isolation.
> Audité le 2026-08-28 (main `8b3609f`). Agent propriétaire : 02.
> Cadre : `docs/architecture/BOUNDED-CONTEXT-DEEP-MATURITY-BACKLOG.md` (12 dimensions).
> Registre : `dev-hub/governance/bounded-context-registry.json` (BC-02).

## Périmètre

Garde absolu du contexte multi-tenant : `api/app/Core/Tenant` (TenantManager,
Domain/Models, Infrastructure/Services/TenantCacheService), `TenantMiddleware`,
`TenantScopedJob` + `EnsureTenantContext`, migrations shared/tenant.

## Verdict par dimension

| # | Dimension | Verdict | Preuves / constats |
|---|---|---|---|
| D1 | Domaine | 🟢 PRÉSENT | `api/app/Core/Tenant/README.md`, contrat documenté dans TenantManager (docblocks), ADR-CRM-002 pour la distinction CRM. Vocabulaire stable (current_company, search_path, withinTenant). |
| D2 | Données | 🟡 PARTIEL | Migrations tenant dans `api/database/migrations/tenant` (schéma `shared_tenants`), garde de schéma #1613 (`check-migrations-tenant-schema.sh`). **Risque R1 (découverte)** : `absence_types.code` porte un index **UNIQUE GLOBAL** (`absence_types_code_unique` sur `code` seul) — deux tenants ne peuvent pas partager le même code standard (ex. `CONGE_ANNUEL`). Constraintes métier à re-scoper `(company_id, code)` — voir recommandation 1. |
| D3 | Tenant | 🟢 PRÉSENT | TenantManager (setTenant/resetToPrevious/withinTenant/current/clearTenant), restauration en `finally` prouvée (MAT-004 + test de rotation livré dans ce DEP), fail-closed `TenantContextMissingException` (trait BelongsToCompany), matrice de résolutions : HTTP (TenantMiddleware), jobs (`EnsureTenantContext`), console (withinTenant), events (MAT-004), webhooks (par contrat). |
| D4 | API | 🟢 PRÉSENT | Routes tenant sous `TenantMiddleware` (678 routes classifiées MAT-003), 401/403 cross-tenant testés (CrossTenantIndexIsolationTest, CrmPlatformIsolationTest, RouteOwnerGuardTest). |
| D5 | Autorisation | 🟡 PARTIEL | `company_id` n'est jamais une autorité (trait BelongsToCompany auto-remplit/scope). Mais pas de Policy tenant générique : le scopage Eloquent est la seule barrière sur de nombreuses surfaces (acceptable en shared-schema, à réviser en schema-isolation). |
| D6 | Transactions | 🟢 PRÉSENT | Provisioning search_path sauvegardé/restauré (PlatformCompanyHealthService::withTenantSearchPath), rotation de contexte testée (ce DEP). |
| D7 | Asynchronisme | 🟢 PRÉSENT | `TenantScopedJob` (contrat) + `EnsureTenantContext` (middleware job : établit le contexte, release si company introuvable) — testé dans ce DEP. |
| D8 | Sécurité | 🟢 PRÉSENT | Pas de secret dans Core/Tenant, search_path qualifié, audit trail via `audit_logs` (schéma tenant), garde .claim-marker / secrets scan. |
| D9 | Frontend | 🟢 N/A | BC core sans UI propre (les espaces employé/manager consomment l'API tenant). |
| D10 | Performance | 🟡 PARTIEL | TenantCacheService (clés `tenant:{companyId}:`) testé (MAT-004). Pas de budget N+1 spécifique BC-02 (MAT-014 en cours). |
| D11 | Exploitation | 🟢 PRÉSENT | Logs structurés (channel `structured`), Sentry context company_id/slug (TenantMiddleware), runbooks ops. |
| D12 | Produit | 🟡 PARTIEL | Parcours « aucun tenant A ne lit/écrit les données de B » démontré par tests (HTTP/jobs/events/cache/rotation). Pas de seed pilote BC-02 dédié. |

## Correctif livré (PR de ce DEP)

**Tests de rotation de contexte + contrat du middleware jobs**
(`api/tests/Feature/Tenant/TenantContextRotationTest.php`, 3 tests) :

1. Rotation rapide A→B→C→A avec écritures : chaque tenant ne voit que ses
   données (zéro croisement), contexte restauré après.
2. `EnsureTenantContext` : le job tourne dans le contexte du tenant cible,
   contexte restauré après (finally du withinTenant).
3. `EnsureTenantContext` : company introuvable → `release()` (retry), pas
   d'exécution, pas de crash.

## Risques et recommandations (PR futures / coordination)

1. **R1 — index global sur `absence_types.code`** (D2) : contrainte `unique('code')`
   au lieu de `unique(['company_id', 'code'])`. Impact multi-tenant réel (deux
   entreprises ne peuvent pas utiliser le même code standard). Fix = migration
   tenant + backfill → **PR de coordination avec BC-06 (LEAVE)** — ne pas
   fusionner dans ce DEP (périmètre BC-06, §7 du plan).
2. **Policies tenant formelles** (D5) : formaliser une Policy d'isolation par
   surface sensible plutôt que le seul scopage implicite.
3. **Matrice de résolutions versionnée** (D3) : publier la matrice
   HTTP/job/event/console/webhook → schéma cible + mécanisme, comme artefact
   de référence (les tests ci-dessus en sont la preuve exécutable).

## Non-régression

Aucun code de production modifié. Tests additifs. CRM commercial intact.
