# DEP-BC24 — Rapport de maturité BC-24 TRAVEL

> **Issue :** [TRAVEL-104 #6009](https://github.com/kitokoh/leopardo-hr/issues/6009) — rapport de maturité (statut **Planifié**)
> **Lot parent :** [TRAVEL-003 #5978](https://github.com/kitokoh/leopardo-hr/issues/5978) — registre BC-24 TRAVEL actif
> **Contexte :** BC-24 — TravelAgency (verticale agence de voyage : réseau, trajets, réservations, passagers, billets, paiements mobile money, locations, hôtels, rapports)
> **Date :** 2026-08-30
> **Statut :** **Planifié** — la solution n'est pas encore sur `main` ; maturité à mesurer à l'arrivée du code (PRs #6127/#6129, #6273, #6340 en cours).
> **Spécification :** `docs/specifications/SOLUTION_TRAVEL_AGENCY.md` (validée propriétaire 2026-08-29)
> **Registre :** `dev-hub/governance/bounded-context-registry.json` — BC-24 = `planned`, owner @kitokoh, dépendances BC-02/03/04/11/13/20

## 1. Cartographie (état `main`, 2026-08-30)

| Élément | État |
|---|---|
| `api/app/Modules/TravelAgency` | Absent de `main` (sur branche `feat/travel-101-202-foundations`, PR #6127 — TRAVEL-101) |
| Migrations `*travel*` | Absentes de `main` (sur branche, TRAVEL-201..214) |
| Routes `/api/v1/travel/*` | Absentes de `main` (sur branche, TRAVEL-301..322) |
| Feature flag `travelagency` + middleware | Sur branche (TRAVEL-102, `module.travelagency`) |
| Registre BC | `BC-24` = `planned`, owner @kitokoh, dépendances BC-02/03/04/11/13/20 |
| Ancien projet source | `kitokoh/gv-back` (fork `lesphinx/gv-back-unified`) — inventaire analysé, cartographie dans la spec §2/§3 |

## 2. Scorecard des 12 dimensions

| Dimension | Statut | Constat |
|---|---|---|
| Toutes (1-12) | ⏳ Planifié | Chaque dimension sera évaluée à l'arrivée du code sur `main` — le DoD commun (12 dimensions, spec §11) est le critère de sortie du pilote. Mapping dimension → issues TRAVEL dans la version deep maturity (DEP-BC24 #6275). |

## 3. Gates pilote (MAT-018 #5876)

Le go/no-go du pilote TravelAgency est verrouillé par le registre `pilot-gates.json` (pilote
`travelagency`, 9 gates : manifest, core flow, API/Policies, runbook, sécurité, performance,
observabilité, golden journey GJ-TRAVEL-01, recette signée TRAVEL-051). **Aucun GO prématuré
possible** (garde CI `check-pilot-gates.sh`).

## 4. Dépendances

- TRAVEL-101..108 : fondations (squelette, middleware, registre BC, activation, manifest, seed-demo, harness) — PR #6127.
- TRAVEL-201..217 : schéma & domaine (référentiel géographique, réseau, routes/trajets, ventes, billets, outbox, locations, hôtels, enums, contrats, factories) — PR #6127/#6129.
- TRAVEL-301..322 : API back-office (référentiel, routes/trajets/tarifs, réservations, billetterie, locations, hôtels, matrice RBAC) — PR #6127.
- TRAVEL-401..413 : vente en ligne, paiements Cash/PVIT, billets PDF — PR #6273.
- TRAVEL-501..507, 601..609, 801..813, 901..913, 1001..1013 : rapports, UI admin, extensions métier, contenu communautaire, boutique publique & clôture — en cours.
- TRAVEL-050..051 : maturité, runbook, pilote — lot courant (issue #5998, #5999).
- Contrats : BC-02 (TENANT), BC-03 (IDENTITY), BC-04 (HR), BC-11 (CRM), BC-13 (COMMS), BC-20 (DOCUMENTS), BC-08 (ACCOUNTING).

## 5. Prochaine action

1. Merger sur `main` la chaîne BC-24 (fondations #6127 → shop #6273 → extensions #6340).
2. Exécuter la scorecard 12 dimensions à l'arrivée du code (DEP-BC24 #6275) et compléter ce rapport.
3. Livrer le runbook pilote + recette UAT + pilot gates (TRAVEL-050 #5998).
4. Exécuter le pilote sur tenant synthétique et basculer le registre BC-24 en `active` (TRAVEL-051 #5999).
