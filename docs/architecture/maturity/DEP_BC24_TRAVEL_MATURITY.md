# DEP-BC24 — Rapport de maturité BC-24 TRAVEL

> **Issue :** [TRAVEL-050 #5998](https://github.com/kitokoh/leopardo-hr/issues/5998)
> **Contexte :** BC-24 — TravelAgency (verticale agence de voyage : réseau, trajets, réservations, passagers, billets, paiements mobile money, locations, hôtels, rapports)
> **Date :** 2026-08-29
> **Statut :** **Code livré (branche travel, PRs #6127/#6273/#6330/#6334/#6351 + lot 3b)** — maturité à verrouiller au merge `main` et au GO pilote (gates MAT-018, `pilot-gates.json` entrée `travel`). 302 tests Feature verts, PHPStan 0 erreur, OpenAPI coverage 0 drift, Redocly 0 erreur.
> **Spécification :** `docs/specifications/SOLUTION_TRAVEL_AGENCY.md`

## 1. Cartographie (état `main`)

| Élément | État |
|---|---|
| `api/app/Modules/TravelAgency` | Absent de `main` (à créer — registre BC, statut `planned`) |
| Migrations `*travel*` | Absentes de `main` (à livrer, TRAVEL-010..014) |
| Routes `/api/v1/travel/*` | Absentes (à livrer, TRAVEL-020..033) |
| Feature flag `travelagency` + middleware | À livrer (TRAVEL-002) |
| Registre BC | `BC-24` = `planned`, owner @kitokoh, dépendances BC-02/03/04/11 |
| Ancien projet source | `kitokoh/gv-back` (fork `lesphinx/gv-back-unified`) — inventaire analysé, cartographie dans la spec |

## 2. Scorecard des 12 dimensions

| Dimension | Statut | Constat |
|---|---|---|
| Architecture DDD (1) | ✅ | Module `TravelAgency` complet (Domain/Application/Infrastructure/Interfaces), Actions, Policies, outbox |
| Tests & isolation (2-3) | ✅ | 302 tests Feature Travel (654→761 assertions), tests cross-tenant 401/403/404, RBAC `travel.*` |
| API & contrats (4-5) | ✅ | OpenAPI 1009+ opérations, coverage 0 drift, Redocly 0 erreur, Postman + guide partenaires |
| Sécurité & RGPD (6) | ✅ | Audit livré (`AUDIT_SECURITE_RGPD_TRAVEL.md`), PII chiffrée, callbacks HMAC, consentements |
| Observabilité (7) | ✅ | Outbox + dead-letter, jobs planifiés (expiration, adverts, synthèse Accounting) |
| Performance (8) | ⏳ | Budgets p95 à valider au pilote (gate `performance`, MAT-014) |
| Golden journey (9) | ✅ | GJ-TRAVEL-01 vert (`TravelGoldenJourneyTest`) + tunnel public E2E |
| Runbook & pilote (10-12) | ✅ | Runbook + recette UAT + pilot gates + pilot-check livrés |

## 3. Gates pilote (MAT-018 #5876)

Le go/no-go du pilote TravelAgency sera verrouillé par le registre `pilot-gates.json` (9 gates :
manifest, core flow, API/Policies, runbook, sécurité, performance, observabilité, golden journey
GJ-TRAVEL-01, recette signée TRAVEL-051). **Aucun GO prématuré possible** (garde CI).

## 4. Dépendances

- TRAVEL-001..004 : fondations (spec, squelette, registre BC, activation) — première vague.
- TRAVEL-010..014 : schéma & domaine — après fondations.
- TRAVEL-020..033 : API back-office + vente en ligne + paiements + billets.
- TRAVEL-040..043 : rapports, UI, OpenAPI, golden journey.
- TRAVEL-050..051 : maturité, runbook, pilote.
- Contrats : BC-02 (TENANT), BC-03 (IDENTITY), BC-04 (HR), BC-11 (CRM), BC-13 (COMMS), BC-20 (DOCUMENTS), BC-08 (ACCOUNTING).

## 5. Prochaine action

Valider la spécification `SOLUTION_TRAVEL_AGENCY.md` (propriétaire), puis créer/implémenter
TRAVEL-001 → TRAVEL-004 (fondations), livrer TRAVEL-010..014 (schéma), puis exécuter la scorecard
12 dimensions + mettre à jour ce rapport à l'arrivée du code sur `main`.
