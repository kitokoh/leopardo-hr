# DEP-BC15 — Rapport de maturité BC-15 FUEL

> **Issue :** [DEP-BC15 #5891](https://github.com/kitokoh/leopardo-hr/issues/5891)
> **Contexte :** BC-15 — FuelStation (verticale pilote : stations, pompes, compteurs, sessions pompistes, ventes, dépôts, bilans)
> **Date :** 2026-08-28
> **Statut :** **ACTIF (batch A livré 2026-08-30)** — fondations FUEL-001..008 sur `main`
> (issues #5795..#5802) + batch A FUEL-009/010/011/014/015/016/017/018/019/020
> (issues #5803..#5814, branche `bc/bc15-fuel-batch-a`). FUEL-012 (UI manager),
> FUEL-013 (mobile pompiste) et la partie client offline restent à livrer.

## 0. Mise à jour 2026-08-30 — batch A (FUEL-009..020)

| Dimension | Statut | Constat |
|---|---|---|
| D1 Manifest/catalogue | ✅ | Manifest `fuel_station` + flag par tenant (FUEL-001) |
| D2 Migrations | ✅ | 11 migrations tenant (000100..000408), parité `CreatesMvpSchema` #5443 |
| D3 Domaine | ✅ | Modèles DDD + enums + invariants (compteur actif unique, ajustement motivé, fidélité bornée) |
| D4 API/RBAC | ✅ | ~45 routes `/fuel-station/*`, policies deny-by-default, tests 401/403/404 |
| D5 Idempotence | ✅ | idempotency_key/external_id/UNIQUE station+date/outbox |
| D6 Isolation tenant | ✅ | FK composites + scopes + tests cross-tenant négatifs |
| D7 Asynchronisme | ✅ | Outbox `fuel_outbox_events` + `fuel:outbox-dispatch` (lease, backoff, dead-letter) |
| D8 Contrats | ✅ | `docs/contracts/fuel-accounting.md` (événements versionnés v1) |
| D9 i18n | ✅ | Templates notifications `fuel_*` fr/en/tr/ar |
| D10 Sécurité | ✅ | Audit `docs/security/AUDIT_SECURITE_FUELSTATION_2026-08-30.md` (16 contrôles) |
| D11 Observabilité | ✅ | Channel log `fuel-station` + événements outbox tracés |
| D12 Golden journey | ⏳ | GJ-06 à couvrir avec l'UI manager (FUEL-012) |

### Issues livrées (batch A)

FUEL-009 (#5803) stocks/rapprochement · FUEL-010 (#5804) incidents/maintenance ·
FUEL-011 (#5805) API & policies référentiel · FUEL-014 (#5808) sync offline (API) ·
FUEL-015 (#5809) contrat Accounting · FUEL-016 (#5810) CRM client & fidélité ·
FUEL-017 (#5811) reporting · FUEL-018 (#5812) import/export · FUEL-019 (#5813)
notifications · FUEL-020 (#5814) sécurité/perf/observabilité.

## 1. Cartographie (état `main`)

| Élément | État |
|---|---|
| `api/app/Solutions/FuelStation` | Absent de `main` (à créer — registre BC, statut `planned`) |
| Migrations `*fuel*` | Absentes de `main` (à livrer, FUEL-002/003/004) |
| Routes `/api/v1/fuel/*` | Absentes (à livrer, FUEL-005..011) |
| Manifest + activation tenant | En cours (FUEL-001, PRs #5841/#5844/#5851) |
| Registre BC | `BC-15` = `planned`, owner @kitokoh, allowed_dependencies BC-02/03/04/05 |

## 2. Scorecard des 12 dimensions

| Dimension | Statut | Constat |
|---|---|---|
| Toutes (1-12) | ⏳ Planifié | Chaque dimension sera évaluée à l'arrivée du code sur `main` — le DoD commun (12 dimensions) est le critère de sortie du pilote |

## 3. Gates pilote (MAT-018 #5876)

Le go/no-go du pilote FuelStation est verrouillé par le registre `pilot-gates.json` (9 gates : manifest, core flow, API/Policies, runbook, sécurité, performance, observabilité, golden journey GJ-06, recette signée FUEL-022). **Aucun GO prématuré possible** (garde CI).

## 4. Dépendances

- FUEL-001..008 : PRs en cours (9 PRs ouvertes sur le périmètre FUEL/EDU).
- FUEL-009..022 : issues libres, à implémenter après fusion des fondations.
- Contrats : BC-02 (TENANT), BC-03 (IDENTITY), BC-04 (HR), BC-05 (WORKFORCE).

## 5. Prochaine action

Quand FUEL-001..008 seront mergés : basculer `BC-15` en `status: active` dans le registre, livrer FUEL-009..014 (stocks, incidents, API, offline), puis exécuter la scorecard 12 dimensions + mettre à jour ce rapport.
