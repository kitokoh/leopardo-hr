# DEP-BC24 — Rapport de maturité BC-24 TRAVEL

> **Issues :** [DEP-BC24 #6275](https://github.com/kitokoh/leopardo-hr/issues/6275) (deep maturity) · [TRAVEL-104 #6009](https://github.com/kitokoh/leopardo-hr/issues/6009) (rapport Planifié) · [TRAVEL-050 #5998](https://github.com/kitokoh/leopardo-hr/issues/5998) (runbook/recette/gates)
> **Contexte :** BC-24 — TravelAgency (verticale agence de voyage : réseau, trajets, réservations, passagers, billets, paiements mobile money, locations, hôtels, rapports)
> **Date :** 2026-08-30
> **Statut :** **Planifié** — le module n'est pas encore sur `main` ; fondations, schéma, domaine, API back-office et extensions sont implémentés sur branches (PRs #6127/#6129, #6273, #6340). Scorecard ci-dessous = état des branches au 2026-08-30, à re-valider à l'arrivée du code sur `main`.
> **Spécification :** `docs/specifications/SOLUTION_TRAVEL_AGENCY.md` (validée propriétaire 2026-08-29)
> **Registre :** `dev-hub/governance/bounded-context-registry.json` — BC-24 = `planned`, owner @kitokoh, dépendances BC-02/03/04/11/13/20

## 1. Progression des lots (état 2026-08-30)

| Lot | Issues | État |
|---|---|---|
| Fondations (squelette DDD, middleware, registre, activation, manifest, seed-demo, harness) | TRAVEL-101..108 | 🏃 implémenté — branche `feat/travel-101-202-foundations` (PR #6127) |
| Schéma & domaine (référentiel géo, réseau, routes/trajets/sièges, ventes, billets, outbox, locations, hôtels, enums, contrats, factories) | TRAVEL-201..217 | 🏃 implémenté — PR #6127/#6129 |
| API back-office (référentiel, routes/trajets/tarifs, réservations, billetterie, check-in, manifest, locations, hôtels, RBAC) | TRAVEL-301..322 | 🏃 implémenté — PR #6127 |
| Vente en ligne, paiements Cash/PVIT, billets PDF | TRAVEL-401..413 | 🏃 implémenté — PR #6273 |
| UI admin web (navigation + écrans verticale) | TRAVEL-601..609 | 🏃 implémenté — PR #6316 (merge dans #6127) |
| Extensions métier (sièges auto, aller-retour, groupe, flexible, multi-devise, transporteurs, remboursements partiels, correspondances, fidélité, annulations) | TRAVEL-801..813 | 🏃 implémenté — PR #6340 |
| Rapports & exports | TRAVEL-501..507 | 🏃 en cours (autre agent BC-24) |
| Contenu communautaire (articles, likes, commentaires) | TRAVEL-901..913 | 🏃 en cours (autre agent BC-24) |
| Boutique publique, import legacy, golden journey, runbook, audit | TRAVEL-1001..1013 | 🏃 en cours (autre agent BC-24) |
| Maturité, runbook, pilote | TRAVEL-050/051 | 📝 lot courant (ce rapport + `docs/ops/`) |

## 2. Cartographie (état `main`, 2026-08-30)

| Élément | État |
|---|---|
| `api/app/Modules/TravelAgency` | Absent de `main` (sur branche `feat/travel-101-202-foundations`, PR #6127 — TRAVEL-101) |
| Migrations `*travel*` | Absentes de `main` (sur branche, TRAVEL-201..214, préfixes `0006xx`/`0009xx` sans collision) |
| Routes `/api/v1/travel/*` | Absentes de `main` (sur branche, TRAVEL-301..322) |
| Feature flag `travelagency` + middleware `module.travelagency` | Sur branche (TRAVEL-102) |
| Registre BC | `BC-24` = `planned`, owner @kitokoh, dépendances BC-02/03/04/11/13/20 |
| Ancien projet source | `kitokoh/gv-back` (fork `lesphinx/gv-back-unified`) — cartographie spec §2/§3 |

## 3. Scorecard des 12 dimensions (état branches BC-24, 2026-08-30)

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

## 4. Gates pilote (MAT-018 #5876)

Le pilote `travelagency` est enregistré dans `dev-hub/tools/pilot-gates.json` (9 gates :
manifest, core_flow, api_security, runbook, security_review, performance, observability,
golden_journey, recette — tous `pending`). **Aucun GO prématuré possible** (garde CI
`check-pilot-gates.sh`, consistance go_decision/gates).

## 5. Dépendances

- Fondations : TRAVEL-101..108 et TRAVEL-201..217 (PRs #6127, #6129).
- Spec : `docs/specifications/SOLUTION_TRAVEL_AGENCY.md`.
- Registre officiel : `dev-hub/governance/bounded-context-registry.json` (BC-24, `planned`).
- Contrats : BC-02 (TENANT), BC-03 (IDENTITY), BC-04 (HR), BC-11 (CRM), BC-13 (COMMS), BC-20 (DOCUMENTS), BC-08 (ACCOUNTING).
- DoD commun 12 dimensions : `docs/architecture/BOUNDED-CONTEXT-DEEP-MATURITY-BACKLOG.md`.

## 6. Prochaine action

1. Merger sur `main` la chaîne BC-24 : #6127 (fondations/schéma/API), #6273 (shop), #6340 (extensions), puis les lots en cours (501..507, 901..913, 1001..1013).
2. Re-exécuter cette scorecard à l'arrivée du code sur `main` (verdicts définitifs par dimension).
3. Exécuter le runbook pilote + recette UAT (TRAVEL-050) et le pilote tenant synthétique (TRAVEL-051), recette signée → bascule du registre BC-24 en `status: active`.
4. Clôturer les épics 0xx superseded (TRAVEL-002/004/010..014/020..024/030..033/040..043) avec renvoi vers les tickets canoniques 1xx-4xx une fois mergés (garde anti ghost-close #4816).
