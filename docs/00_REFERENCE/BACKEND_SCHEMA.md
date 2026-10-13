# BACKEND_SCHEMA — Schéma logique du backend

**Statut :** canonique · **Base :** `main` au 2026-10-13 · **Issue :** #8444

> Modèle logique (pas un dump SQL). Les ERD détaillés vivent dans
> `docs/dossierdeConception/04_architecture_erd/` et `18_schemas_sql/`.
> Les entités citées sont les modèles Eloquent réels des modules DDD.

## 1. Trois bases logiques

| Base | Contenu | Exemples |
|---|---|---|
| `public` | plateforme : tenants, abonnements, catalogue de solutions, support | tenants, subscriptions, `PlatformSupportTicket` |
| `tenant` | données métier d'un tenant (431 migrations au 2026-10-13) | employés, ventes, stock, planning |
| `edge` | bridge local on-prem (offline) | files de sync, pointages en attente |

Règles : `company_id` obligatoire sur toute donnée métier ; scopes globaux ;
migrations via `php artisan leopardo:migrate` ; sync et événements via tables
outbox (`PlatformOutboxEvent`, `RestaurantOutboxEvent`) — pattern qui garantit
la livraison et la déduplication.

## 2. Noyau tenant & identité (`Core/Tenant`, `Core/Auth`)

```
Company (tenant) 1───n Site (établissement/station)
Company 1───n CompanySetting
Company 1───n Employee ───n EmployeeModuleGrant (accès modules/apps)
                       └───n EmployeeResourceAssignment (affectations/rotations)
CompanyRequest (demande de création, pré-provisioning)
SuperAdmin (plateforme)
```

- Email employé globalement unique (contrainte base).
- Rôles : enums plateforme (`PlatformRole`, `PlatformPermission`) + rôles
  tenant installés par solution.

## 3. RH transversale (`HR`, `Attendance`, `Payroll`, `Planning`)

```
Employee ── Department ── Position
Employee ── Contract ── ContractAmendment ; CareerEvent ; EmployeeDocument ;
            EmployeeDeparture ; Evaluation
Employee ── OnboardingProgress ── OnboardingStep (parcours d'arrivée)
AttendanceLog ── AttendanceKiosk (ZKTeco) ; AttendanceCorrectionRequest ;
ApprovalWorkflow/Request/Decision ; AttendanceDayClosure ; AttendanceModeSettings
PaySlip ── PaySlipLine ; LedgerEntry ; Commission ; EmployeeLoan/LoanRepayment ;
BankExport ; IslamicCalendar
```

## 4. Verticale Restaurant (`RestaurantManager`) — le fil rouge

```
RestaurantBranch (site) 1───n RestaurantZone 1───n RestaurantTable ─── RestaurantTableSession
RestaurantBranch 1───n RestaurantBranchStaff (affectation employés ↔ sites, base des rotations)
RestaurantMenu 1───n RestaurantMenuItem
RestaurantCategory 1───n RestaurantProduct ─── RestaurantProductIngredient ─── RestaurantIngredient
RestaurantOrder 1───n RestaurantOrderItem ; RestaurantOrder 1───n RestaurantOrderPayment
RestaurantPosSession (caisse) ; RestaurantRefund ; RestaurantTaxRate ; RestaurantUnit
RestaurantReservation ; RestaurantHour
RestaurantDelivery ─── RestaurantDeliveryZone / Rider / AppConfig
Stock : RestaurantStockLevel, InventoryMovement, InventoryCount(+Item),
        Supplier, PurchaseOrder(+Item), Receiving
CRM local : LoyaltyProgram / LoyaltyCustomer / LoyaltyPointsMovement, Review,
            Promotion, PublicShopToken (boutique en ligne), MarketplaceEvent
Sync  : RestaurantOutboxEvent
```

Points de conception :

- **BranchStaff** porte les affectations employé ↔ branche : les rotations
  multi-restaurants (#8450) sont une planification par-dessus ce lien.
- **PosSession + OrderPayment + file offline** : le POS hors-ligne (#8449)
  écrit localement puis rejoue via outbox dédupliqué.
- **Order → cuisine** : le KDS (#8448) consomme les `RestaurantOrderItem`
  par statut (reçu/en préparation/prêt/servi).

## 5. Verticale Station-service (`FuelStation`)

```
Station(Site) ── FuelCashSession ── FuelCashSessionMovement
FuelCustomer ; FuelDelivery ; FuelImport ; FuelAlert/Log ;
FuelIncident(+Attachment) ; FuelMaintenanceTask ; FuelMeterInterval ; FuelAccountVisit
```

Même patron que Restaurant : caisses, ventes, incidents, stock — sur les
modules transversaux RH/paie/CRM.

## 6. Catalogue & CRM transversaux

```
CatalogCategory 1───n CatalogProduct ; CatalogInquiry
CrmAccount ── CrmActivity ; CrmCampaign ── CrmCampaignSend ; CrmChannel ;
CrmAutomation ── Run/State
```

Le CRM **commercial Leopardo** reste côté plateforme (`Platform`/`Marketing`) ;
le CRM **client** du tenant vit dans `CRM` avec `company_id`.

## 7. Conventions

- Noms : tables `snake_case` plurielles ; modèles `PascalCase` préfixés par
  solution (`Restaurant*`, `Fuel*`…).
- Clés : UUID ou bigint selon le module ; timestamps + soft deletes sur les
  entités métier majeures.
- Idempotence : clés de déduplication sur toute écriture rejouable (sync,
  webhooks `WebhookEvent`, outbox).
- Audit : traçabilité des actions sensibles, correlation ID, pas de secrets.
- Purge tenant : tables classifiées (publiques vs tenant) — #8426.

## 8. Évolutions liées à la mission

| Besoin | Impact schéma |
|---|---|
| KDS offline (#8448) | statuts de préparation sur `RestaurantOrderItem` + file Edge |
| POS offline (#8449) | écriture locale + rejeu outbox (existant) |
| Rotations généralisées (#8450) | extraire le socle affectation/planning de `BranchStaff` vers `Planning` |
| Mon Logiciel (#8447) | aucune table nouvelle côté tenant : lecture du catalogue de solutions + `EmployeeModuleGrant` |
| Checklists onboarding (#8451) | progression par tenant (pattern `OnboardingProgress` généralisé) |
