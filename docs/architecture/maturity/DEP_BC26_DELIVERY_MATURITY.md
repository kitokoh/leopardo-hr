# Rapport de maturité — BC-26 DELIVERY

> **DEP-BC26 (issue #6281) — Deep maturity, BC-26 DELIVERY (module de livraison générique).**
> Conception v0.2 (élargie multi-tenant) — 2026-08-30. Agent propriétaire : 26 (DELIVERY).
> Cadre : `docs/architecture/BOUNDED-CONTEXT-DEEP-MATURITY-BACKLOG.md` (12 dimensions).
> Spec : `docs/specifications/SOLUTION_DELIVERY.md`.
> Registre : `dev-hub/governance/bounded-context-registry.json` (BC-26, statut `planned`).

## Statut

**DISCOVERED → MAPPED** — le contexte est conçu (domaine, invariants, API,
12 dimensions auditées en conception) mais **aucun code n'existe encore** :
les issues DELIVERY-101.. et BC-26-Dnn décrivent l'implémentation à venir.

## Positionnement

BC-26 DELIVERY est un **module de livraison dernier-kilomètre générique** :
tout tenant qui livre (agence de livraison, **restaurant BC-25**, retail
BC-17, e-commerce BC-14, client CRM BC-11, pharmacie…) active le même moteur
(colis/livraisons, tournées, livreurs, POD, tracking, COD, rapports) via le
feature flag `companies.features.delivery`. Les origines diffèrent
(`DeliverySource`), le moteur est unique et scopé `company_id`.

## Verdict par dimension (conception)

| # | Dimension | Verdict | Preuves / constats |
|---|---|---|---|
| D1 | Domaine | 🔵 CONÇU | Glossaire + agrégats + invariants dans la spec (état terminal unique, tournée 1 livreur/véhicule/jour, POD requis, clôture idempotente, **unique `(tenant, source, source_reference)`** — zéro doublon de livraison par commande source). |
| D2 | Données | 🔵 CONÇU | Migrations tenant `delivery_*` à écrire (livraisons avec `source`/`source_reference`, tournées, stops, événements, POD, règlements) — index tenant-first, uniques tenant-first, réentrantes. |
| D3 | Tenant | 🔵 CONÇU | Toutes les entités scopées `company_id` (fail-closed #3727) ; tests cross-tenant **multi-types** (agence vs restaurant vs e-commerce) dès le schéma (issue DELIVERY-102). |
| D4 | API | 🔵 CONÇU | Routes `/api/v1/deliveries/*` versionnées, Requests strictes, Resources allowlistées, OpenAPI — contrat défini dans la spec §4. |
| D5 | Autorisation | 🔵 CONÇU | Matrice RBAC `delivery.dispatcher/rider/manager/admin` + middleware `module.delivery` (kill switch). |
| D6 | Transactions | 🔵 CONÇU | Idempotence des événements (clé `(company_id, delivery_id, type, event_at)` / `idempotency_key`) et de la clôture ; verrouillage statut ; création par source idempotente. |
| D7 | Asynchronisme | 🔵 CONÇU | Jobs de clôture/export/notifications : retry borné, DLQ, replay (pattern Integration Runtime BC-14). |
| D8 | Sécurité | 🔵 CONÇU | POD = données personnelles (RGPD, rétention BC-20), URLs temporaires, logs redacted, rate limits. |
| D9 | Frontends | 🔵 CONÇU | App mobile livreur (offline + replay, pattern EdgeSync) + dashboard dispatcher multi-source — états loading/error/empty, permissions UI non autoritaires. |
| D10 | Performance | 🔵 CONÇU | Budgets p95 au registre MAT-014, index `(company_id, statut, date)`, pagination, pas de N+1. |
| D11 | Exploitation | 🔵 CONÇU | Runbook livraison à livrer (échecs, retards, COD manquants), logs corrélés, alertes. |
| D12 | Produit | 🔵 CONÇU | Golden journeys **par source** (agence manuelle, restaurant BC-25, e-commerce BC-14) + seed pilote synthétique (spec §7). |

## Sortie exigée par le backlog (BC-26)

- [ ] Deux recalculs produisent le même résultat (clôture de tournée — test golden)
- [ ] Les dashboards n'utilisent pas de jointures profondes transactionnelles
- [ ] Un livreur ne voit que les livraisons/tournées qui lui sont affectés (RBAC + isolation)
- [ ] Un événement rejoué ne duplique pas le suivi (idempotence)
- [ ] Une tournée ne peut pas être affectée à deux livreurs le même jour
- [ ] POD obligatoire pour passer à `delivered` (invariant workflow)
- [ ] **Une commande source (restaurant/retail/e-commerce) ne crée jamais deux livraisons** (unique `(tenant, source, source_reference)`)

## Prochaines étapes (issues)

1. **DELIVERY-101** — Fondations : registre BC-26 actif, squelette module DDD,
   manifest, middleware module, health endpoint (pattern restaurant/travel).
2. **DELIVERY-102** — Migrations & schéma tenant `delivery_*` (+ `source`,
   `source_reference`, unique idempotence) + tests cross-tenant multi-types.
3. **DELIVERY-103** — Domaine : enums (dont `DeliverySource`), ValueObjects,
   invariants testés.
4. **DELIVERY-201..208** — API livraisons/tournées/stops/events/POD/COD/
   rapports + **contrats sources** (restaurant/retail/e-commerce/CRM).
5. **BC-26-D01/D03/D05/D07/D10/D12** — Glossaire, isolation, RBAC, asynchronisme,
   budgets p95, golden journeys multi-source.

## Non-régression

Aucun code de production. Conception (spec + rapport + registre + issues) uniquement.
