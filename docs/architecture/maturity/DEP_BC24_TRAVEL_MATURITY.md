# DEP-BC24 — Rapport de maturité BC-24 TRAVEL

> **Issue :** [TRAVEL-050 #5998](https://github.com/kitokoh/leopardo-hr/issues/5998) + [DEP-BC24 #6275](https://github.com/kitokoh/leopardo-hr/issues/6275)
> **Contexte :** BC-24 — TravelAgency (verticale agence de voyage : réseau, trajets, réservations, passagers, billets, paiements mobile money, locations, hôtels, rapports)
> **Date :** 2026-08-29 (mise à jour : 2026-08-30)
> **Statut :** **Planifié** — la solution n'est pas encore sur `main` ; maturité à mesurer à l'arrivée du code (issues TRAVEL-001..051). Pipeline d'implémentation actif : voir §6.
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
| Toutes (1-12) | ⏳ Planifié | Chaque dimension sera évaluée à l'arrivée du code sur `main` — le DoD commun (12 dimensions, §11 de la spec) est le critère de sortie du pilote |

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

Valider la spécification `SOLUTION_TRAVEL_AGENCY.md` (propriétaire), merger les PRs de fondations
(#6127), boutique/paiements/billets (#6273), UI admin (#6316) et extensions 8xx (#6340), puis
exécuter la scorecard 12 dimensions et mettre à jour ce rapport à l'arrivée du code sur `main`.

## 6. Suivi pipeline (2026-08-30)

Le contexte est en cours d'implémentation par lots parallèles — branches `feat/travel-*` et
`bc/bc24-*` ; aucune fusion sur `main` à ce jour (le code travel n'existe donc pas encore sur
`main`) :

| Lot | Branche / PR | Périmètre |
|---|---|---|
| Fondations | `feat/travel-101-202-foundations` (PR #6127) | TRAVEL-101..108 + 201..203 (squelette DDD, flag, manifest, onboarding, migrations pays/villes/stations/offices) |
| Schéma & domaine | issues TRAVEL-204..217 (branches à venir) | Migrations réseau/routes/ventes/location/hôtels, enums, contracts, factories |
| Boutique & paiements | `feat/travel-401-404-shop` (PR #6273) | TRAVEL-401..405 (shop, paiements cash/PVIT, billets PDF) |
| UI admin | `bc/bc24-admin-screens` (PR #6316) | TRAVEL-601..609 + 1008 (navigation, écrans référentiel/réseau/réservations/check-in/billets/rapports/locations) |
| Extensions 8xx | `bc/bc24-travel-extensions` (PR #6340) | TRAVEL-801..809 (sièges auto, aller-retour, groupe, recherche flexible, multi-devise…) |
| Qualité & pilote | `bc/bc24-travel-qualite-pilote` (PR #6352) | TRAVEL-050/051 + DEP-BC24 : runbook, recette UAT, gates, audit RGPD, golden journey |

Scorecard 12 dimensions : ⏳ Planifié — sera évaluée dimension par dimension à la fusion des lots
ci-dessus (DoD commun `docs/architecture/BOUNDED-CONTEXT-DEEP-MATURITY-BACKLOG.md`). Les gates
pilote (MAT-018) restent verrouillés (aucun GO prématuré, garde CI).
