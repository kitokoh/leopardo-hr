# Matrice des permissions TravelAgency (BC-24 TRAVEL) — TRAVEL-322 (#6052)

> Source : spec `docs/specifications/SOLUTION_TRAVEL_AGENCY.md` (§9 Sécurité & RGPD, §10.2
> Activation) et décision D9. Les permissions sont déclarées dans
> `TravelAgencyManifest::permissions()` et verrouillées par les Policies du module.

## Permissions déclarées

| Permission | Intention | Implémentation (v1, épic 3xx) |
|---|---|---|
| `travel.manage` | Administration de la verticale (référentiel, réseau, trajets, réservations, tarifs, locations, hôtels, billets) | `hasManagerRole('principal', 'rh', 'manager')` **ou** grant `travel.manage` |
| `travel.agent` | Vente guichet / caisse (lecture + création de réservation) | Lecture ouverte à tout employé du tenant (`viewAny` → true) ; **écriture via le grant `travel.agent`** (plus de rôle manager requis) |
| `travel.checkin` | Contrôle embarquement / check-in | `TravelTicketPolicy::checkIn()` — rôle manager **ou** grant `travel.checkin` |
| `travel.reports` | Rapports & exports (épic 5xx) | `TravelReportPolicy` — rôle manager **ou** grant `travel.reports` |

## Matrice par surface API (qui peut faire quoi)

| Surface | Employé simple | Employé avec grant | Manager / RH / Principal |
|---|---|---|---|
| `GET /travel/countries`, `/cities` | ✅ lecture | ✅ | ✅ |
| `GET /travel/stations|offices|carriers|classes|vehicles|routes|trips|bookings|rental-vehicles|rental-bookings|hotels` | ✅ lecture | ✅ | ✅ |
| `POST/PUT/DELETE` référentiel & réseau (stations, offices, carriers, classes, vehicles, routes+étapes, trips, prices) | ❌ 403 | ✅ si `travel.manage` | ✅ |
| `POST /travel/trips/{trip}/publish\|cancel` | ❌ 403 | ✅ si `travel.manage` | ✅ |
| `POST /travel/bookings` (guichet) | ❌ 403 | ✅ si `travel.agent` | ✅ |
| `POST /travel/bookings/{id}/confirm\|cancel\|refund\|issue-ticket` | ❌ 403 | ✅ si `travel.agent` | ✅ |
| `POST /travel/tickets/{id}/check-in` | ❌ 403 | ✅ si `travel.checkin` | ✅ |
| `GET /travel/trips/{id}/manifest` | ✅ lecture (sans PII) | ✅ lecture (sans PII) | ✅ avec PII (`principal`, `rh`) |
| `POST /travel/rental-bookings` + cancel | ❌ 403 | ✅ si `travel.manage` | ✅ |
| `POST /travel/hotels` + rooms | ❌ 403 | ✅ si `travel.manage` | ✅ |
| `GET /travel/reports/*` (sales, occupancy, revenue, cancellations, dashboard, export) | ❌ 403 | ✅ si `travel.reports` | ✅ |

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

- ~~Épic 5xx : câbler `travel.reports` sur les endpoints rapports.~~ **Fait** (grant honoré).
- ~~App mobile agent (TRAVEL-701) : `travel.agent` devra autoriser la vente guichet
  sans rôle manager~~ **Fait** : le grant `travel.agent` suffit (vérifié par
  `TravelEvalRegressionTest`).

## Délégation des permissions verticales (interface RH)

Les permissions d'un manifest ne sont **pas** des `ModuleKey` (registre fermé et
horizontal) : elles ne peuvent donc pas passer par
`PUT /employees/{id}/module-grants`, qui les refuse en 422. Une surface dédiée
les rend attribuables depuis l'interface :

| Endpoint | Rôle | Effet |
|---|---|---|
| `GET /v1/employees/{id}/vertical-permissions` | `principal` | `{ permission_keys, available }` — `available` = permissions des solutions **actives** du tenant |
| `PUT /v1/employees/{id}/vertical-permissions` | `principal` | Remplace le jeu **complet** (non coché = révoqué) |

Règles :

1. **Fail-closed** : une verticale inactive n'expose aucune permission ; une clé
   hors catalogue (ou un module horizontal) est refusée en **422**.
2. **Indépendance des surfaces** : `PUT /module-grants` ne touche qu'aux modules
   horizontaux, `PUT /vertical-permissions` qu'aux permissions verticales —
   enregistrer l'un ne révoque jamais l'autre.
3. **Activation** : `ActivateTravelAgencyAction` installe les 4 permissions pour
   le(s) `principal` du tenant (idempotent), qui peut ensuite les déléguer.
4. Tests : `TravelVerticalPermissionDelegationTest` (API) et
   `employees-page.test.tsx` (panneau « Permissions métier »).
