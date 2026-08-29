# Rapport de maturité — BC-18 FIELD

> **DEP-BC18 (issue #5894)** — Deep maturity, BC-18 Field Service & Fleet.
> Audité le 2026-08-28 (main `228c382`). Agent propriétaire : 18.
> Cadre : `docs/architecture/BOUNDED-CONTEXT-DEEP-MATURITY-BACKLOG.md` (12 dimensions).
> Registre : `dev-hub/governance/bounded-context-registry.json` (BC-18).

## Périmètre

Véhicules, interventions, équipements, maintenance et opérations terrain
génériques : `api/app/Modules/Fleet` (DDD), surface manager multi-véhicules,
intégration éventuelle avec les verticales (FuelStation, FieldService futur).

## Verdict par dimension

| # | Dimension | Verdict | Preuves / constats |
|---|---|---|---|
| D1 | Domaine | 🟡 PARTIEL | DDD complet (Application/Actions+DTOs, Domain/Contracts+Exceptions+Models, Interfaces). Vocabulaire : véhicules, maintenance, interventions. Le périmètre « field service » générique reste à élargir (le module est centré Fleet aujourd'hui). |
| D2 | Données | 🟢 PRÉSENT | Migrations tenant, FK/index cohérents, garde de schéma vert. |
| D3 | Tenant | 🟢 PRÉSENT | Modèles scopés (BelongsToCompany), isolation testée (FleetControllerTest/VehicleControllerTest). |
| D4 | API | 🟢 PRÉSENT | Contrôleurs Fleet + Vehicle, routes versionnées, Requests/Resources, OpenAPI couvert. |
| D5 | Autorisation | 🟢 PRÉSENT | Policies + gardes manager/principal, actions bornées (assignation, maintenance). |
| D6 | Transactions | 🟡 PARTIEL | Cycle maintenance/état véhicule simple ; pas d'invariants d'état documentés (ex. véhicule indisponible pendant maintenance). |
| D7 | Asynchronisme | 🟡 PARTIEL | Aucun job Fleet dédié (flux synchrone). |
| D8 | Sécurité | 🟢 PRÉSENT | Aucun secret ; PII conducteurs gérée par le contrat HR/Identity. |
| D9 | Frontend | 🟢 PRÉSENT | Espace manager (véhicules, maintenance) — surface présente. |
| D10 | Performance | 🟢 PRÉSENT | Listes paginées, index ; volume modéré. |
| D11 | Exploitation | 🟢 PRÉSENT | Logs structurés, audit via canal global. |
| D12 | Produit | 🟡 PARTIEL | Parcours véhicule → maintenance → retour service testé (22 tests locaux verts) ; pas de golden journey end-to-end ni seed pilote dédié. |

## Vérification locale (preuve)

```
php artisan test --filter="FleetControllerTest|VehicleControllerTest"
→ 22 passed (80 assertions)
```

## Recommandations (PR futures, non bloquantes)

1. **Invariants d'état** (D6) : formaliser le cycle de vie véhicule
   (disponible → en maintenance → indisponible → retour) avec tests de
   transition et refus des transitions invalides.
2. **Élargissement field service** (D1) : cadrer le futur FieldService
   (interventions terrain) comme extension du BC-18 sans dupliquer Fleet.
3. **Golden journey** (D12) : seed pilote + test end-to-end
   véhicule → maintenance → rapport → retour.

## Non-régression

Aucun code de production modifié. Rapport + vérifications uniquement.
