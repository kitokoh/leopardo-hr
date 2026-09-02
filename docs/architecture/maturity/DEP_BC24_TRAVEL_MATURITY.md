# DEP-BC24 — Rapport de maturité BC-24 TRAVEL

> **Issue :** [TRAVEL-050 #5998](https://github.com/kitokoh/leopardo-hr/issues/5998)
> **Contexte :** BC-24 — TravelAgency (verticale agence de voyage : réseau, trajets, réservations, passagers, billets, paiements mobile money, locations, hôtels, rapports)
> **Date :** 2026-08-29
> **Statut :** **Code livré (branche travel, PRs #6127/#6273/#6330/#6334/#6351 + lot 3b)** — maturité à verrouiller au merge `main` et au GO pilote (gates MAT-018, `pilot-gates.json` entrée `travel`). 302 tests Feature verts, PHPStan 0 erreur, OpenAPI coverage 0 drift, Redocly 0 erreur.
> **Spécification :** `docs/specifications/SOLUTION_TRAVEL_AGENCY.md`
=======
> **Date :** 2026-08-30
> **Statut :** **Planifié** — le module n'est pas encore sur `main` ; fondations, schéma, domaine, API back-office et extensions sont implémentés sur branches (PRs #6127/#6129, #6273, #6340). Scorecard ci-dessous = état des branches au 2026-08-30, à re-valider à l'arrivée du code sur `main`.
> **Spécification :** `docs/specifications/SOLUTION_TRAVEL_AGENCY.md` (validée propriétaire 2026-08-29)
> **Registre :** `dev-hub/governance/bounded-context-registry.json` — BC-24 = `planned`, owner @kitokoh, dépendances BC-02/03/04/11/13/20
>>>>>>> origin/pm/merge-all-open-branches

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
=======
| # | Dimension | Statut | Constat / preuve |
|---|---|---|---|
| 1 | Domaine | 🏃 (branche) | Enums + Value Objects (TRAVEL-215), invariants machines à états trajet/réservation/billet/paiement/location, actions applicatives dédiées (GenerateTripSeatsAction, CreateBookingAction, CheckInTicketAction…) |
| 2 | Données | 🏃 (branche) | 14 migrations tenant réentrantes travel_* (TRAVEL-201..214) + factories & parité `CreatesMvpSchema` (TRAVEL-108/217) ; index tenant-first ; préfixes vérifiés sans collision (garde #5431) |
| 3 | Tenant | 🏃 (branche) | `company_id` partout, `BelongsToCompany` fail-closed, middleware tenant, tests isolation cross-tenant (TRAVEL-108) |
| 4 | API | 🏃 (branche) | Référentiel + routes/trajets + réservations + billetterie + locations + hôtels (TRAVEL-301..322) + shop (TRAVEL-401..413) ; Requests strictes, Resources, OpenAPI couvert (SDK régénéré) |
| 5 | Autorisation | 🏃 (branche) | Matrice permissions `travel.*` + Policies par ressource (TRAVEL-322) ; réservé managers `principal`/`rh` + rôles agence |
| 6 | Transactions | 🏃 (branche) | Génération transactionnelle des sièges (TRAVEL-208), réservation/confirm/cancel/refund (TRAVEL-312..315), verrou stock sièges, callbacks paiement idempotents (TRAVEL-409) |
| 7 | Asynchronisme | 🏃 (branche) | Outbox `travel_outbox_events` (TRAVEL-211) + intégrations événementielles (TRAVEL-033) ; idempotence des jobs |
| 8 | Sécurité | ⏳ | Audit sécurité & RGPD avant pilote prévu (TRAVEL-1013) ; PII voyageurs chiffrée (déjà sur branche), callbacks signés HMAC (TRAVEL-409) |
| 9 | Frontend | 🏃 (branche) | UI admin web verticale (TRAVEL-601..609, PR #6316) + boutique publique (TRAVEL-1001..1004 en cours) |
| 10 | Performance | ⏳ | Gate `performance` pending (budgets MAT-014, benchmarks trajets/recherche) |
| 11 | Exploitation | 🏃 (lot courant) | Runbook pilote + recette UAT (TRAVEL-050) — `docs/ops/RUNBOOK_PILOT_TRAVELAGENCY.md` + `RECETTE_UAT_TRAVELAGENCY.md` ; drill DR-26 planifié |
| 12 | Produit | ⏳ | GJ-TRAVEL-01 (TRAVEL-1007 en cours) + pilote tenant synthétique (TRAVEL-051) — GO verrouillé par `pilot-gates.json` |
>>>>>>> origin/pm/merge-all-open-branches

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
1. Merger sur `main` la chaîne BC-24 : #6127 (fondations/schéma/API), #6273 (shop), #6340 (extensions), puis les lots en cours (501..507, 901..913, 1001..1013).
2. Re-exécuter cette scorecard à l'arrivée du code sur `main` (verdicts définitifs par dimension).
3. Exécuter le runbook pilote + recette UAT (TRAVEL-050) et le pilote tenant synthétique (TRAVEL-051), recette signée → bascule du registre BC-24 en `status: active`.
4. Clôturer les épics 0xx superseded (TRAVEL-002/004/010..014/020..024/030..033/040..043) avec renvoi vers les tickets canoniques 1xx-4xx une fois mergés (garde anti ghost-close #4816).
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
