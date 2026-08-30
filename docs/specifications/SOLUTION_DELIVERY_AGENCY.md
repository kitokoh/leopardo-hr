# SOLUTION_DELIVERY_AGENCY — Spécification BC-26 DELIVERY

> **BC-26 — DELIVERY (DeliveryAgency)** — statut `planned`.
> **Owner :** Agent 26 — BC-DELIVERY.
> **Registre :** `dev-hub/governance/bounded-context-registry.json` (BC-26).
> **Cadre :** `docs/architecture/BOUNDED-CONTEXT-DEEP-MATURITY-BACKLOG.md` (12 dimensions).
> **Version :** v0.1 (conception) — 2026-08-30.

## 1. Pourquoi une agence de livraison a besoin de Leopardo

Une agence de livraison (coursiers, dernière-mille, distribution urbaine ou
inter-ville) gère : des **colis** (ramassage → transport → remise), des
**livreurs** (chauffeurs/coursiers), des **véhicules**, des **tournées**
journalières, des **preuves de livraison** (POD) et des **encaissements**
(contre-remboursement / COD). Leopardo fournit déjà le socle (tenant,
identité, HR, pointage, CRM, comptabilité, documents, notifications,
analytics) ; **BC-26 DELIVERY** apporte la verticale métier de la livraison
par-dessus ces contrats — sans dupliquer employés, véhicules, contacts ou
écritures.

## 2. Périmètre du contexte

| Possède (owned) | Consomme par contrat (ne possède pas) |
|---|---|
| Colis / paquets (référence, poids, valeur, COD, adresses) | Employés → livreurs (BC-04 HR, contrat `EmployeeHired/Changed/Departed`) |
| Tournées (routes) + affectation livreur/véhicule/jour | Pointage & disponibilité (BC-05 WORKFORCE) |
| Arrêts (stops) : ordre, ETA/ETD, statut, POD | Véhicules & flotte (BC-18 FLEET) |
| Preuves de livraison (photo/signature) | Contacts destinataires (BC-11 CRM) |
| Événements de suivi temps réel (tracking) | Notifications destinataire (BC-13 COMMS) |
| Règlement COD & commissions livreurs | Encaissements & écritures (BC-08 ACCOUNTING) |
| Rapports & KPIs livraison | Documents & preuves (BC-20 DOCUMENTS) |
| | Read models & KPIs (BC-22 ANALYTICS) |

## 3. Modèle de domaine

```text
DeliveryAgency (tenant) ── 1..n ── DeliveryRoute (tournée)
   │                                  │ 1..n
   │                                  DeliveryStop (arrêt)
   │                                  │ 1..1
   │                                  DeliveryPackage (colis)
   │                                  │ 1..n
   │                                  DeliveryEvent (tracking)
   │                                  DeliveryProof (POD photo/signature → BC-20)
   │
   ├── DeliveryDriver (profil livreur ↔ Employee BC-04)
   ├── DeliveryVehicle (↔ véhicule BC-18)
   └── DeliveryCodSettlement (règlement COD ↔ BC-08)
```

### Agrégats & invariants

- **DeliveryPackage** (agrégat racine du colis) : référence unique tenant
  (`DLV-2026-000123`), type (standard / fragile / réfrigéré / documents),
  poids & volume, valeur déclarée, montant COD (nullable), adresses
  ramassage/destination (structurées ou référence CRM BC-11), fenêtre de
  livraison, statut.
  - **Invariant** : un colis ne peut atteindre un état terminal
    (`delivered` / `returned` / `cancelled`) qu'une seule fois — transitions
    versionnées, aucune réouverture après clôture.
  - **Invariant** : `cod_amount > 0` ⇒ encaissement attendu à la remise ;
    `delivered` sans POD = incohérence bloquée par le workflow.
- **DeliveryRoute** (tournée) : date de tournée, livreur, véhicule, zone,
  liste ordonnée de stops, état (`draft → assigned → in_progress → completed`),
  clôture (totaux colis, COD, échecs).
  - **Invariant** : une tournée a **un seul livreur + un seul véhicule par
    date** (pas de chevauchement d'affectation).
  - **Invariant** : la clôture est idempotente — deux clôtures produisent le
    même résultat (exigence BC-22 « deux recalculs produisent le même résultat »).
- **DeliveryStop** (arrêt) : ordre de passage, adresse, fenêtre, ETA/ETD,
  statut (`pending / en_route / arrived / delivered / failed / skipped`),
  POD associée.
- **DeliveryEvent** (tracking) : `picked_up / out_for_delivery / arrived /
  delivered / failed / returned`, horodaté, géolocalisé (lat/lng), émis par
  l'app livreur (offline inclus) — **idempotent** (clé
  `(company_id, package_id, type, event_at)` ou `idempotency_key` fournie par
  le client).
- **DeliveryProof** (POD) : photo (upload BC-20, URLs temporaires) et/ou
  signature, horodatée, rattachée au stop — requis pour `delivered`.
- **DeliveryCodSettlement** : montant COD collecté par tournée/livreur, remise
  caisse, commissions, contrat de posting idempotent vers BC-08.

### Cycle de vie du colis

```text
created → assigned (tournée) → picked_up → out_for_delivery → arrived
   → delivered (POD obligatoire) | failed → retour (returned) | cancelled
```

## 4. API (v1, versionnée)

| Méthode | Route | Rôle | Description |
|---|---|---|---|
| GET/POST | `/api/v1/deliveries/packages` | dispatcher | CRUD colis (filtres statut/date/zone, pagination) |
| GET/POST/PATCH | `/api/v1/deliveries/routes` | dispatcher | Tournées : création, affectation livreur/véhicule, ordre des stops |
| POST | `/api/v1/deliveries/routes/{route}/assign` | dispatcher | Affectation (idempotente, garde chevauchement) |
| POST | `/api/v1/deliveries/routes/{route}/close` | dispatcher | Clôture (idempotente, totaux) |
| GET | `/api/v1/deliveries/routes/today` | livreur (mobile) | Tournée du jour du livreur authentifié |
| POST | `/api/v1/deliveries/stops/{stop}/status` | livreur (mobile) | Changement de statut + POD (photo/signature) |
| POST | `/api/v1/deliveries/events` | livreur/edge | Événement de tracking (idempotent, offline replay) |
| GET | `/api/v1/deliveries/packages/{package}/tracking` | destinataire/public (lien borné) | Suivi sans authentification (token court, BC-20) |
| GET | `/api/v1/deliveries/reports/summary` | manager | KPIs (livrées/jour, taux de succès, délais, COD) |

RBAC : `delivery.dispatcher` (gestion tournées/colis), `delivery.rider`
(mobile livreur), `delivery.manager` (rapports), `delivery.admin`
(paramétrage agence). Middleware `module.deliveryagency` (kill switch, pattern
cameras/travel/restaurant).

## 5. Les douze dimensions (audit de conception)

| # | Dimension | Statut conception | Exigence |
|---|---|---|---|
| D1 | Domaine | 🔵 CONÇU | Glossaire, agrégats, invariants (ci-dessus) — issue BC-26-D01 |
| D2 | Données | 🔵 CONÇU | Migrations tenant `delivery_*` : colis, tournées, stops, événements, POD, règlements — index `(company_id, statut, date)`, uniques tenant-first, réentrantes |
| D3 | Tenant | 🔵 CONÇU | Tout scopé `company_id` (fail-closed #3727), tests cross-tenant |
| D4 | API | 🔵 CONÇU | Routes v1, Requests strictes, Resources allowlistées, OpenAPI |
| D5 | Autorisation | 🔵 CONÇU | Matrice RBAC livreur/dispatcher/manager/admin + tests 401/403 |
| D6 | Transactions | 🔵 CONÇU | Idempotence événements + clôture, verrouillage statut colis (`SELECT FOR UPDATE`) |
| D7 | Asynchronisme | 🔵 CONÇU | Jobs de clôture/export/notifications, retry borné, DLQ, replay |
| D8 | Sécurité | 🔵 CONÇU | POD = données personnelles (RGPD), URLs temporaires, redaction logs, rate limits |
| D9 | Frontends | 🔵 CONÇU | App mobile livreur (offline + replay, pattern EdgeSync), dashboard dispatcher |
| D10 | Performance | 🔵 CONÇU | Budgets p95 (registre MAT-014), index, pagination, pas de N+1 |
| D11 | Exploitation | 🔵 CONÇU | Runbook livraison, logs corrélés, alertes (échecs, retards, COD manquants) |
| D12 | Produit | 🔵 CONÇU | Golden journey colis → tournée → livraison → POD → règlement, seed pilote synthétique |

## 6. Dépendances inter-contextes

BC-02 TENANT, BC-03 IDENTITY, BC-04 HR, BC-05 WORKFORCE, BC-08 ACCOUNTING,
BC-11 CRM, BC-13 COMMS, BC-18 FLEET, BC-20 DOCUMENTS, BC-22 ANALYTICS.

Contrats à ratifier avant implémentation :
- **BC-04** : profil livreur = Employee avec rôle métier `delivery_rider`
  (événements `EmployeeHired/Changed/Departed`).
- **BC-08** : posting idempotent des encaissements COD + commissions
  (contrat `DocumentWorkflowService` / écritures source-référencées).
- **BC-20** : upload des preuves (POD) via le contrat documents (MIME
  allowlist, URLs temporaires, rétention).
- **BC-13** : notifications destinataire (SMS/WhatsApp) avec opt-out.
- **BC-22** : read models KPIs livraison (déterminisme, budgets p95).

## 7. Golden journey (sortie exigée)

1. Le dispatcher crée 3 colis (dont 1 COD) → tournée du jour affectée à un
   livreur + véhicule.
2. Le livreur charge la tournée sur mobile (offline) → `picked_up` →
   `out_for_delivery` → `arrived` → photo POD → `delivered`.
3. Le 3e colis échoue (destinataire absent) → `failed` → `returned` (retour).
4. Clôture de tournée : totaux (2 livrés, 1 retour, COD collecté 12 000 DZD).
5. Le manager consulte le rapport : taux de succès 66 %, délai moyen, COD.
6. Le destinataire suit le colis via le lien borné (sans auth).
7. Réconciliation : COD = écriture BC-08 (posting idempotent).

## 8. Hors périmètre (à ne PAS faire ici)

- Le CRM commercial / client reste dans BC-11 (contacts destinataires consommés par contrat, pas dupliqués).
- Les véhicules restent dans BC-18 (BC-26 référence, ne possède pas la flotte).
- Les employés/pointage restent dans BC-04/05 (le livreur est un employé).
- Le paiement en ligne n'est pas un prérequis : le COD est le flux initial ;
  les paiements digitaux viendront par contrat BC-21/BC-08.
