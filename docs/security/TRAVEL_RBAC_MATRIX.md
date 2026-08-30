# Matrice des permissions TravelAgency (BC-24 TRAVEL) — TRAVEL-322 (#6052)

> Source : spec `docs/specifications/SOLUTION_TRAVEL_AGENCY.md` (§9 Sécurité & RGPD, §10.2
> Activation) et décision D9. Les permissions sont déclarées dans
> `TravelAgencyManifest::permissions()` et verrouillées par les Policies du module.

## Permissions déclarées

| Permission | Intention | Implémentation (v1, épic 3xx) |
|---|---|---|
| `travel.manage` | Administration de la verticale (référentiel, réseau, trajets, réservations, tarifs, locations, hôtels, billets) | `hasManagerRole('principal', 'rh', 'manager')` sur toutes les Policies d'écriture |
| `travel.agent` | Vente guichet / caisse (lecture + création de réservation) | Lecture ouverte à tout employé du tenant (`viewAny` → true) ; écriture via `travel.manage` |
| `travel.checkin` | Contrôle embarquement / check-in | `TravelTicketPolicy::checkIn()` — rôle manager |
| `travel.reports` | Rapports & exports (épic 5xx) | `hasManagerRole('principal', 'rh', 'manager')` (TravelReportPolicy) |

## Matrice par surface API (qui peut faire quoi)

| Surface | Employé simple | Manager / RH / Principal |
|---|---|---|
| `GET /travel/countries`, `/cities` | ✅ lecture | ✅ |
| `GET /travel/stations|offices|carriers|classes|vehicles|routes|trips|bookings|rental-vehicles|rental-bookings|hotels` | ✅ lecture | ✅ |
| `POST/PUT/DELETE` référentiel & réseau (stations, offices, carriers, classes, vehicles, routes+étapes, trips, prices) | ❌ 403 | ✅ |
| `POST /travel/trips/{trip}/publish\|cancel` | ❌ 403 | ✅ |
| `POST /travel/bookings` (guichet) | ❌ 403 | ✅ |
| `POST /travel/bookings/{id}/confirm\|cancel\|refund\|issue-ticket` | ❌ 403 | ✅ |
| `POST /travel/tickets/{id}/check-in` | ❌ 403 | ✅ (`travel.checkin`) |
| `GET /travel/trips/{id}/manifest` | ✅ lecture (sans PII) | ✅ |
| `POST /travel/rental-bookings` + cancel | ❌ 403 | ✅ |
| `POST /travel/hotels` + rooms | ❌ 403 | ✅ |
| `GET /travel/reports/*` (sales, occupancy, revenue, cancellations, dashboard, export) | ❌ 403 | ✅ (`travel.reports`) |

## Règles transversales (fail-closed)

1. **Contexte tenant absent ou flag `travelagency` inactif → 403** (middleware `module.travelagency`).
2. **Ressource d'un autre tenant → 404** (jamais 403 : ne pas révéler l'existence).
3. **Référence cross-tenant dans une création (ville, route, carrier, classe, véhicule) → 422**
   (règles `Rule::exists` scopées `company_id`).
4. **Non authentifié → 401** (Sanctum).
5. La matrice est verrouillée par des tests RBAC globaux :
   `api/tests/Feature/Travel/TravelRbacMatrixTest.php` — chaque surface est testée
   avec un rôle autorisé (200/201) et un rôle refusé (403), en plus des tests
   cross-tenant (404) présents dans chaque CRUD.

## Évolutions prévues

- Épic 5xx : câbler `travel.reports` sur les endpoints rapports.
- App mobile agent (TRAVEL-701) : `travel.agent` devra autoriser la vente guichet
  sans rôle manager — décision à trancher avec le portail client (TRAVEL-702).
