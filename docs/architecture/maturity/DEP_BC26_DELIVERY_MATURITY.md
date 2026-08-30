# Rapport de maturité — BC-26 DELIVERY

> **DEP-BC26 (issue #6281) — Deep maturity, BC-26 DELIVERY (module de livraison générique).**
> Conception v0.2 (élargie multi-tenant) — 2026-08-30. Agent propriétaire : 26 (DELIVERY).
> Cadre : `docs/architecture/BOUNDED-CONTEXT-DEEP-MATURITY-BACKLOG.md` (12 dimensions).
> Spec : `docs/specifications/SOLUTION_DELIVERY.md`.
> Registre : `dev-hub/governance/bounded-context-registry.json` (BC-26, statut `active`).

## Statut

**DISCOVERED → MAPPED → IMPLEMENTED (socle + API + D05/D07/D10/D12)** — le
contexte est conçu et **implémenté sur `pm/merge-delivery-socle`** (fondations
DELIVERY-101..104, API DELIVERY-201/202/204/207, RBAC BC-26-D05, asynchronisme
BC-26-D07, budgets p95 BC-26-D10, golden journey BC-26-D12) ; la consolidation
vers `main` suit le protocole de branches par lot (batch PM). Dimensions
restantes : DELIVERY-203/205/206/208 et BC-26-D01/D03 (issues en cours par
l'équipe BC-26).

## Positionnement

BC-26 DELIVERY est un **module de livraison dernier-kilomètre générique** :
tout tenant qui livre (agence de livraison, **restaurant BC-25**, retail
BC-17, e-commerce BC-14, client CRM BC-11, pharmacie…) active le même moteur
(colis/livraisons, tournées, livreurs, POD, tracking, COD, rapports) via le
feature flag `companies.features.delivery`. Les origines diffèrent
(`DeliverySource`), le moteur est unique et scopé `company_id`.

## Verdict par dimension

| # | Dimension | Verdict | Preuves / constats |
|---|---|---|---|
| D1 | Domaine | 🟢 IMPLÉMENTÉ (partiel) | Enums, ValueObjects, machine à états `DeliveryStateMachine` (état terminal unique, POD obligatoire, pas de saut d'étape), `DeliveryReference`/`Money`/`IdempotencyKey` (DELIVERY-103). Glossaire unifié `DELIVERY_GLOSSARY.md` : BC-26-D01 (issue en cours — équipe). |
| D2 | Données | 🟢 IMPLÉMENTÉ | Migrations tenant `delivery_*` (5 tables) réentrantes : uniques tenant-first (`company_id, reference`, `company_id, source, source_reference` → zéro doublon par commande source, `company_id, route_date, driver_id` → 1 livreur/véhicule/jour), index `(company_id, status, created_at)`, `(company_id, delivery_id)` + `(route_id, sort_order)` (D10). Parité `CreatesMvpSchema` + tests `DeliverySchemaTest`. |
| D3 | Tenant | 🟢 IMPLÉMENTÉ (partiel) | Tout scopé `company_id` (trait `BelongsToCompany` fail-closed #3727), repository 404-sûr, tests isolation (lecture/écriture/événements/URLs cross-tenant 404). Matrice de tests cross-tenant **multi-types** (agence vs restaurant vs e-commerce) : BC-26-D03 (issue en cours — équipe). |
| D4 | API | 🟢 IMPLÉMENTÉ | Routes `/api/v1/delivery/*` versionnées (201/202/204/207) : CRUD livraisons paginé, tournées (création/assign/clôture idempotentes), événements idempotents + lien public borné, rapports summary/export. Requests strictes, Resources allowlistées, OpenAPI (`api/openapi.yaml` + miroir `dev-hub/openapi/v1.yaml`). |
| D5 | Autorisation | 🟢 IMPLÉMENTÉ | Matrice deny-by-default `docs/architecture/DELIVERY_RBAC.md` + middleware `delivery.role` (admin/dispatcher/manager/rider/reports) + Policies `DeliveryPolicy`/`DeliveryRoutePolicy` (ownership livreur `driver_id = id`). Tests 401/403/404 par rôle + cross-employé + cross-tenant (BC-26-D05, #6294). |
| D6 | Transactions | 🟢 IMPLÉMENTÉ | Idempotence des événements (unique `(company, delivery, type, event_at)` + `idempotency_key`, rejeu → événement existant), clôture idempotente (2 exécutions → mêmes totaux), verrouillage `SELECT FOR UPDATE` sur statuts et affectations, création par source idempotente (unique `(tenant, source, source_reference)`). |
| D7 | Asynchronisme | 🟢 IMPLÉMENTÉ | `CloseDeliveryRouteJob` + `ExportDeliveryReportJob` tenant-scoped (`EnsureTenantContext`, contexte restauré en fin de job — BC-02), retry borné (3 × backoff 10/30/60 s), DLQ `delivery_dead_letters` + `delivery:replay-dlq` sans doublon, logs corrélés, commandes console (BC-26-D07, #6295). |
| D8 | Sécurité | 🟡 PARTIEL | POD = données personnelles : lien public par token borné (64 chars, TTL 7 j, anti-énumération, no-referrer), PII limitée dans le suivi public. Upload POD via contrat BC-20 + rétention RGPD : DELIVERY-203 (app mobile, issue en cours). |
| D9 | Frontends | 🟡 PARTIEL | API mobile prête (événements idempotents, offline replay = rejeu idempotent testé) ; app Flutter livreur : DELIVERY-203 (issue en cours). Dashboard dispatcher multi-source : non couvert (hors périmètre socle). |
| D10 | Performance | 🟢 IMPLÉMENTÉ | Budgets p95 au registre MAT-014 (liste ≤ 300 ms paginée, events ≤ 200 ms, tracking public ≤ 200 ms, summary ≤ 300 ms, export ≤ 400 ms) + index requis déclarés (dont `delivery_stops (route_id, sort_order)`) ; zéro `->get()` non paginé dans les contrôleurs (curseur/limit) ; garde `check-performance-budgets.sh` exit 0 (BC-26-D10, #6296). |
| D11 | Exploitation | 🟢 IMPLÉMENTÉ | Runbook `docs/ops/RUNBOOK_DELIVERY.md` (activation, endpoints × RBAC, invariants, jobs/DLQ/replay, budgets, **incidents symptôme → diagnostic → action → rollback**, RGPD) ; registre MAT-015 BC-26 couvert (garde exit 0) ; alertes failed_jobs + DLQ. |
| D12 | Produit | 🟢 IMPLÉMENTÉ (partiel) | Golden journey agence E2E (test complet §7.1, déterminisme clôture) + seed pilote synthétique `DeliveryPilotSeeder` (MAT-012) + registre MAT-013 (GJ-DELIVERY-01, garde exit 0) (BC-26-D12, #6297). Parcours restaurant/e-commerce : dépendent de DELIVERY-208 (contrats sources, issue en cours). |

## Sortie exigée par le backlog (BC-26)

- [x] Deux recalculs produisent le même résultat (clôture de tournée — test golden `DeliveryGoldenJourneyTest`)
- [x] Les dashboards n'utilisent pas de jointures profondes transactionnelles (read model agrégats SQL scopés, `DeliveryReportService`)
- [x] Un livreur ne voit que les livraisons/tournées qui lui sont affectés (RBAC + isolation — `DeliveryRoutePolicy`/`DeliveryEventPolicy` + tests cross-employé)
- [x] Un événement rejoué ne duplique pas le suivi (idempotence — unique + idempotency_key, testé)
- [x] Une tournée ne peut pas être affectée à deux livreurs le même jour (index unique + verrou, testé)
- [x] POD obligatoire pour passer à `delivered` (invariant workflow — 409 PROOF_REQUIRED, testé)
- [x] **Une commande source (restaurant/retail/e-commerce) ne crée jamais deux livraisons** (unique `(tenant, source, source_reference)`, testé repository)

## Prochaines étapes (issues)

1. **DELIVERY-203** — App mobile livreur (tournée du jour, POD upload BC-20, offline replay) — en cours (équipe).
2. **DELIVERY-205** — Règlement COD & commissions (posting idempotent BC-08, réconciliation) — en cours (équipe).
3. **DELIVERY-206** — Notifications destinataire (contrat BC-13 COMMS, opt-out) — en cours (équipe).
4. **DELIVERY-208** — Contrats sources BC-25/17/14/11 (création idempotente par source) — en cours (équipe).
5. **BC-26-D01** — Glossaire unifié `DELIVERY_GLOSSARY.md` — en cours (équipe).
6. **BC-26-D03** — Matrice de tests cross-tenant multi-types — en cours (équipe).
7. **Merge & release** — Consolidation `pm/merge-delivery-socle` → `main` (protocole batch PM) puis statut registre `pilot` → `operations` au recette pilote.

## Non-régression

Socle + API + D05/D07/D10/D12 intégrés sur `pm/merge-delivery-socle` ; CI 5
checks requis sur chaque PR (PHPStan strict level 8, Pint, tests, gardes
architecture). Registres MAT-013/014/015 à jour (gardes exit 0 vérifiés
localement). Aucun secret ni PII dans les seeds/fixtures (100 % synthétique).
