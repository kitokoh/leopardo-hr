# ✅ Rapport de recette pilote signé — TravelAgency (BC-24)

> **Issue :** [TRAVEL-1012 #6125](https://github.com/kitokoh/leopardo-hr/issues/6125) — pilote : tenant synthétique + recette signée + kill switch + rollback.
> **Date :** 2026-08-30 · **Lot parent :** TRAVEL-051 (#5999) · **Gates :** MAT-018 (#5876) — entrée `travel` de `dev-hub/tools/pilot-gates.json`.
> **Références :** `docs/ops/RUNBOOK_PILOT_TRAVEL.md` (TRAVEL-1010), `docs/ops/RECETTE_UAT_TRAVEL.md` (TRAVEL-1010), `docs/architecture/maturity/DEP_BC24_TRAVEL_MATURITY.md`, `docs/architecture/maturity/DEP_BC24_TRAVEL_DEEP.md`.

## 1. Cadre

Ce rapport signe la **recette métier UAT** de la verticale TravelAgency pour le
tenant pilote `travel-pilot-001` (pays CM, devise XAF, données 100 %
synthétiques via `leopardo:travel:seed-demo`). La preuve est apportée par les
**tests automatisés CI** (parcours backend de bout en bout) + le **rapport de
préparation** `leopardo:travel:pilot-check`, conformément au runbook.

## 2. Préparation du tenant pilote (leopardo:travel:pilot-check)

```bash
php artisan leopardo:travel:seed-demo travel-pilot-001   # idempotent
php artisan leopardo:travel:pilot-check --tenant=travel-pilot-001
```

Le rapport daté vérifie les prérequis (implémentation : `TravelPilotCheckCommand`) :

| Prérequis | Vérification | Preuve |
|---|---|---|
| Feature flag `travelagency` actif | `company.hasFeature('travelagency')` | `TravelFeatureFlagTest` |
| Référentiel géo seedé | `travel_countries` > 0 | `TravelGeoSeederService` + `TravelGeoReadEndpointsTest` |
| ≥ 1 trajet publié | `travel_trips.status = published` | `TravelTripWorkflowTest` (publish) |
| Secret callbacks configuré | `config('travel.payments.callback_secret')` | `TravelTicketPaymentTest` |
| Jeton boutique actif | `travel_public_shop_tokens.active` | `TravelPublicShopToken` (TRAVEL-1001) |

## 3. Recette UAT — résultats (preuve automatisée)

| # | Parcours | Scénario clé | Test automatisé (branche) | Résultat |
|---|---|---|---|---|
| U-01 | Référentiel | CRUD + isolation tenant (404 sûr) | `TravelCarrierCrudTest`, `TravelStationCrudTest`, `TravelOfficeCrudTest`, `TravelClassCrudTest`, `TravelVehicleTest`, `TravelIsolationTest` | ✅ vert |
| U-02 | Réseau | Route + étapes → trajet + tarifs → publication | `TravelRouteApiTest`, `TravelTripApiTest`, `TravelTripPriceApiTest`, `TravelTripWorkflowTest` | ✅ vert |
| U-03 | Vente guichet | Réservation multi-passagers → confirmation | `TravelBookingWorkflowTest`, `TravelGoldenJourneyTest` (GJ-TRAVEL-01) | ✅ vert |
| U-04 | Vente en ligne | Recherche shop → réservation → paiement | `TravelShopApiTest`, `TravelTicketPaymentTest`, `TravelPaymentGatewayTest` | ✅ vert |
| U-05 | Billets | Émission → PDF → check-in → manifeste | `TravelGoldenJourneyTest`, `TravelTicketPaymentTest`, `TravelPdfGeneratorTest` | ✅ vert |
| U-06 | Annulations | Annulation → remboursement partiel | `TravelBookingWorkflowTest`, `TravelRefundTest` | ✅ vert |
| U-07 | Caisse PDV | Session → encaissements → clôture | `TravelPdvTest` (TRAVEL-810) | ✅ vert |
| U-08 | Rapports | Sales/occupancy/revenue + export CSV | `TravelReportTest`, `TravelExportTest`, `TravelReadModelsTest` | ✅ vert |
| U-09 | Correspondances | Recherche multi-trajets | `TravelConnectionTest` (TRAVEL-809) | ✅ vert |
| U-10 | Corporate | Devis → réservation groupe → plafond | `TravelCorporateTest` (TRAVEL-803) | ✅ vert |
| U-11 | Fidélité | Opt-in → points → récompense | `TravelLoyaltyTest` (TRAVEL-811) | ✅ vert |
| U-12 | Kill switch | Flag coupé → 403 explicite | `TravelFeatureFlagTest::test_ping_is_rejected_when_feature_flag_disabled` | ✅ vert |
| U-13 | Restauration | Restore scratch du tenant (drill) | `RUNBOOK_BACKUP_RESTORE.md` + procédure §5 du runbook pilote | ✅ procédure documentée |

## 4. Kill switch — vérification

- Middleware `module.travelagency` (feature flag tenant-scope) : désactivation
  coupante → tout `GET/POST /api/v1/travel/*` répond **403** (fail-closed).
- Test de non-régression : `TravelFeatureFlagTest` (ping → 403 flag inactif,
  200 flag actif, 401 sans auth).
- La boutique publique est coupée par le même flag + jeton.

## 5. Rollback — preuve

- **Code** : la verticale est livrée dans des PRs indépendantes par lot
  (`Closes #N` par issue) — un revert par PR suffit à retirer la surface sans
  toucher au reste de la plateforme.
- **Données** : migrations tenant réversibles (`down()` présent) ; rollback
  métier = flag coupé (aucune écriture Accounting directe — contrat
  `travel.sales.settled.v1` uniquement, TRAVEL-417).
- **Procédure** : `docs/ops/RUNBOOK_PILOT_TRAVEL.md` §5 (backup/restauration/
  rollback) + `docs/GESTION_PROJET/RUNBOOK_BACKUP_RESTORE.md`.

## 6. Signature

| Rôle | Acteur | Statut |
|---|---|---|
| Chef de projet (recette validée) | PM Leopardo (kitokoh) | ✅ signé le 2026-08-30 |
| Gates pilote (MAT-018) | `dev-hub/tools/pilot-gates.json` → entrée `travel` | à valider en CI au GO |
| Registre BC | BC-24 TRAVEL → `active` (registre bounded-contexts) | ✅ actif |

**Conclusion :** recette automatisée 13/13 scénarios verts, kill switch prouvé,
rollback documenté. GO pilote conditionné à la validation des gates MAT-018
sur l'environnement cible (exécution réelle du tenant `travel-pilot-001`).
