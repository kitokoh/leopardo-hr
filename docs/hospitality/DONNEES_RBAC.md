# HospitalityManager (BC-32) — Données & RBAC

> Doc annexe HOSP-008 (#7950). Source de vérité fonctionnelle :
> `docs/specifications/SOLUTION_HOSPITALITY.md` (gel fondateur 2026-09-20).
> Ce document décrit l'état LIVRÉ du modèle de données et du contrôle d'accès ;
> les lots encore en vol (HOSP-005 locatif, HOSP-006 API publique, HOSP-007 web
> gestion) sont signalés.

## 1. Modèle de données (schéma tenant)

Migrations : `api/database/migrations/tenant/2026_09_20_0000{14,15,16}_79{44,45,46}_*.php`.
Toutes les tables portent `company_id` (isolation tenant, cross-tenant → 404) et
des CHECK d'intégrité en base.

| Table | Rôle | Points structurants |
|---|---|---|
| `hospitality_properties` | Établissements (hôtel, résidence, immeuble, maison d'hôtes) | unique `(company_id, code)` ; `public_slug` **unique global**, généré au passage `is_public=true` (`POST /hospitality/properties/{id}/publish`) ; `amenities` json ; suppression refusée si réservation active (`HOSPITALITY_PROPERTY_IN_USE`) |
| `hospitality_room_types` | Types de chambres d'un établissement | prix `base_price_minor` + `currency` ISO 4217 ; statut `active|inactive` ; suppression refusée si en usage (`HOSPITALITY_ROOM_TYPE_IN_USE`) |
| `hospitality_units` | Unités physiques (chambres, appartements) | rattachées à un `room_type` du MÊME établissement ; suppression refusée si réservation active (`HOSPITALITY_UNIT_IN_USE`, durcissement #8019) |
| `hospitality_property_staff` | Affectations staff par établissement | pivot employé ↔ établissement (soft-delete + restauration) ; unicité rattrapée en 409 `EMPLOYEE_ALREADY_ASSIGNED` sous transaction (#8019) |
| `hospitality_reservations` | Réservations (guichet + en ligne) | statuts `pending → confirmed → checked_in → checked_out` + `cancelled`, `no_show` ; CHECK `check_out > check_in` ; idempotence par `idempotency_key` ; `expires_at` posé sur les pending EN LIGNE (+30 min, commande `hospitality:expire-pending-reservations`) ; transitions verrouillées `lockForUpdate` (#8019) |
| Baux & loyers (HOSP-005) | Gestion locative | **en vol** sur le lot BC-32 parallèle (#7947) — non couvert ici |

Disponibilité (HOSP-004) : `available = capacité opérationnelle des unités du
type − réservations tenant l'inventaire sur [from, to)` (pending non expirées
comprises) — même calcul servi par l'endpoint interne et l'endpoint public.

## 2. RBAC — ressource-scopé progressif `hospitality_property`

Implémentation : `Domain/Policies/*` + trait
`Concerns/ChecksHospitalityPropertyAccess` (pattern restaurant #7598/#7599,
spec §4). Tout est **deny-by-default** derrière le feature flag tenant
`hospitality` (fail-closed : solution inactive → 403
`HOSPITALITY_SOLUTION_INACTIVE`).

Niveaux d'assignation (`EmployeeResourceAssignment`, resource_type
`hospitality_property`) :

| Niveau | Ouvre |
|---|---|
| `view` | lecture du référentiel, des réservations et des KPIs de CET établissement |
| `operate` | gestes de guichet : transitions de réservations (confirm, check-in/out, cancel, no-show) |
| `manage` | référentiel, inventaire, équipe, publication, réservations (création/édition) |

Règle de **progressivité** : tant qu'AUCUNE assignation
`hospitality_property` n'existe dans le tenant, fallback direction historique
(`hasManagerRole('principal','rh')`). Dès la première assignation, le scoping
devient actif et fail-closed : un non-assigné ne lit plus ni n'écrit ;
`principal` passe toujours, `rh` retombe en lecture seule. Les KPIs dashboard
sont bornés aux établissements accessibles à l'acteur (#8019).

## 3. Surface publique (HOSP-006/008)

- API `/public/hospitality/*` (sans auth, throttle dédié, spec §6) : fiche par
  `public_slug` (fail-closed si `is_public=false`), disponibilités,
  réservation en ligne idempotente (`pending` + `expires_at=+30min`,
  `reference` non énumérable + `tracking_code` montré une seule fois — seul
  son hash est stocké), suivi et annulation par référence + code (404
  uniforme, anti-énumération). Contrat documenté dans `api/openapi.yaml`
  (**contract-first** — routes livrées par HOSP-006 #7948).
- Vitrine `front/web/src/app/stay/[slug]/` (HOSP-008 #7950) : page SSR
  indexable (metadata + JSON-LD `LodgingBusiness`/`Hotel`), fiche + parcours
  de réservation (`StayBookingPanel`, appels via le proxy same-origin
  `/api/v1`, aucun jeton). `/stay` reste HORS `PROTECTED_PREFIXES` (public par
  nature) ; `/hospitality` (gestion) y est. i18n ×4 (`stay.public.*`,
  catalogues `shared/i18n/locales/`).
