# Rapport de maturité — BC-26 DELIVERY

> **DEP-BC26 (issue #6281) — Deep maturity, BC-26 DELIVERY (DeliveryAgency).**
> Conception v0.1 — 2026-08-30. Agent propriétaire : 26 (DELIVERY).
> Cadre : `docs/architecture/BOUNDED-CONTEXT-DEEP-MATURITY-BACKLOG.md` (12 dimensions).
> Spec : `docs/specifications/SOLUTION_DELIVERY_AGENCY.md`.
> Registre : `dev-hub/governance/bounded-context-registry.json` (BC-26, statut `planned`).

## Statut

**DISCOVERED → MAPPED** — le contexte est conçu (domaine, invariants, API,
12 dimensions auditées en conception) mais **aucun code n'existe encore** :
les issues DELIVERY-101.. et BC-26-Dnn décrivent l'implémentation à venir.

## Périmètre

Colis, tournées, arrêts, livreurs, véhicules (par contrat BC-18), preuves de
livraison (POD), événements de tracking, règlement COD et rapports — pour
qu'une **agence de livraison** utilise Leopardo par-dessus le socle existant
(tenant, identité, HR, pointage, CRM, comptabilité, documents,
notifications, analytics).

## Verdict par dimension (conception)

| # | Dimension | Verdict | Preuves / constats |
|---|---|---|---|
| D1 | Domaine | 🔵 CONÇU | Glossaire + agrégats + invariants dans la spec (colis à état terminal unique, tournée 1 livreur/véhicule/jour, POD requis pour `delivered`, clôture idempotente). |
| D2 | Données | 🔵 CONÇU | Migrations tenant `delivery_*` à écrire (colis, tournées, stops, événements, POD, règlements) — index tenant-first, réentrantes (conventions MIGRATIONS_CONVENTIONS). |
| D3 | Tenant | 🔵 CONÇU | Toutes les entités scopées `company_id` (fail-closed #3727) ; tests cross-tenant obligatoires dès le schéma (issue DELIVERY-102). |
| D4 | API | 🔵 CONÇU | Routes `/api/v1/deliveries/*` versionnées, Requests strictes, Resources allowlistées, OpenAPI — contrat défini dans la spec §4. |
| D5 | Autorisation | 🔵 CONÇU | Matrice RBAC `delivery.dispatcher/rider/manager/admin` + middleware `module.deliveryagency` (kill switch). |
| D6 | Transactions | 🔵 CONÇU | Idempotence des événements (clé `(company_id, package_id, type, event_at)` / `idempotency_key`) et de la clôture ; verrouillage statut colis. |
| D7 | Asynchronisme | 🔵 CONÇU | Jobs de clôture/export/notifications : retry borné, DLQ, replay (pattern Integration Runtime BC-14). |
| D8 | Sécurité | 🔵 CONÇU | POD = données personnelles (RGPD, rétention BC-20), URLs temporaires, logs redacted, rate limits. |
| D9 | Frontends | 🔵 CONÇU | App mobile livreur (offline + replay, pattern EdgeSync) + dashboard dispatcher — états loading/error/empty, permissions UI non autoritaires. |
| D10 | Performance | 🔵 CONÇU | Budgets p95 au registre MAT-014, index `(company_id, statut, date)`, pagination, pas de N+1. |
| D11 | Exploitation | 🔵 CONÇU | Runbook livraison à livrer (échecs, retards, COD manquants), logs corrélés, alertes. |
| D12 | Produit | 🔵 CONÇU | Golden journey colis → tournée → livraison → POD → règlement + seed pilote synthétique (spec §7). |

## Sortie exigée par le backlog (BC-26)

- [ ] Deux recalculs produisent le même résultat (clôture de tournée — test golden)
- [ ] Les dashboards n'utilisent pas de jointures profondes transactionnelles
- [ ] Un livreur ne voit que les colis/tournées qui lui sont affectés (RBAC + isolation)
- [ ] Un événement rejoué ne duplique pas le suivi (idempotence)
- [ ] Une tournée ne peut pas être affectée à deux livreurs le même jour
- [ ] POD obligatoire pour passer à `delivered` (invariant workflow)

## Prochaines étapes (issues)

1. **DELIVERY-101** — Fondations : registre BC-26 actif, squelette module DDD,
   manifest, middleware module, health endpoint (pattern restaurant/travel).
2. **DELIVERY-102** — Migrations & schéma tenant `delivery_*` + index + tests cross-tenant.
3. **DELIVERY-103** — Domaine : enums, ValueObjects, invariants testés.
4. **DELIVERY-201..207** — API colis/tournées/stops/events/POD/COD/rapports.
5. **BC-26-D01/D03/D05/D07/D10/D12** — Glossaire, isolation, RBAC, asynchronisme,
   budgets p95, golden journey.

## Non-régression

Aucun code de production. Conception (spec + rapport + registre + issues) uniquement.
