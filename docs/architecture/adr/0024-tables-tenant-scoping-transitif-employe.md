# ADR 0024 - Tables tenant scopées employé sans `company_id` (issue #7984)

## Status

Accepted.

**Date**: 2026-09-23

## Context

L'audit d'architecture du 2026-09-20 (issue #7984, point 5) relève 4 tables
tenant qui stockent de la donnée liée au tenant sans porter de colonne
`company_id`, alors que la convention `BelongsToCompany` couvre 295/410
migrations tenant :

| Table | Rattachement effectif | Migration |
|---|---|---|
| `device_tokens` | `employee_id` FK → `employees` (cascade) | `tenant/2026_05_18_000001` |
| `calendar_connections` | `employee_id` FK → `employees` (cascade) | `tenant/2026_05_18_000002` |
| `calendar_events` | `employee_id` FK → `employees` (cascade) | `tenant/2026_05_18_000002` |
| `app_notifications` | `user_id` (indexé, sans FK) | `tenant/2026_08_15_000002` |

## Decision

**Statu quo documenté — pas d'ajout de `company_id` sur ces 4 tables.**

Justification, table par table :

1. **`device_tokens`, `calendar_connections`, `calendar_events`** : le scoping
   tenant est *transitif et contraint* — chaque ligne appartient à un
   `employee` (FK avec cascade), et `employees.company_id` porte déjà le
   scoping tenant. Dupliquer `company_id` ici créerait une seconde source de
   vérité qui peut diverger (employé changeant de société, incohérence
   `token.company_id ≠ employee.company_id`) sans bénéfice d'isolation : ces
   tables ne sont jamais interrogées cross-tenant, toujours via l'employé ou
   ses relations.
2. **`app_notifications`** : même logique, transitif via `user_id`. Point de
   vigilance : `user_id` n'a **pas** de contrainte FK (les users vivent dans
   `public.users`, référence cross-schema non portable en mode
   schema-per-tenant — même limite que la chaîne de paiement, cf. migration
   `tenant/2026_09_23_000300_7984`). Toute requête sur cette table DOIT passer
   par le contexte utilisateur authentifié, jamais par un `user_id` arbitraire.

## Conditions de réexamen

Cette décision est à réévaluer si l'un de ces cas apparaît :

- une requête d'agrégation par société sur l'une de ces tables (reporting,
  quotas par tenant) — le join transitif deviendrait le goulot ;
- l'abandon du mode schema-per-tenant (#8055/#8056) au profit d'un scoping
  par colonne unique — la convention `BelongsToCompany` deviendrait alors
  obligatoire partout ;
- un incident d'isolation impliquant l'une de ces tables.

## Consequences

- Aucune migration de schéma pour ces 4 tables dans le lot #7984.
- Les revues de code peuvent pointer cet ADR quand l'absence de `company_id`
  sur une table scopée employé est questionnée.
- Toute NOUVELLE table scopée employé doit soit porter `company_id`
  (convention par défaut), soit référencer explicitement cet ADR dans sa
  migration.
