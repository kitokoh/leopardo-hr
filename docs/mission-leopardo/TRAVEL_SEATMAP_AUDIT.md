# Mission Leopardo — Travel OS : plan des sièges et réservations

> **Audit d'existant + correctifs ciblés** (`front/travel-web`, `front/admin-dashboard`,
> `api/app/Modules/TravelAgency`).
> Branche de travail : `mission/leopardo-travel-os`.
> Statut : audit terminé, deux correctifs ciblés livrés (états de siège enrichis + composant
> visuel de plan de sièges), le reste du socle de réservation **réutilisé tel quel**.

---

## 0. Méthode

Le module Travel existe déjà et il est mature : **384 fichiers PHP** répartis en couches
`Domain` / `Application` / `Infrastructure` / `Interfaces`, plus deux fronts
(`front/travel-web` grand public, `front/admin-dashboard` back-office). La consigne est
explicite — **ne pas reconstruire, compléter**. L'audit a donc commencé par localiser les
composants réels (modèles, migrations, actions, contrôleurs, routes, tests, composants front)
avant toute écriture de code.

Vérification préalable d'un éventuel travail antérieur : aucune branche, issue ni PR liée à
cette mission n'existait (branches distantes : `main`, `bc/bc-36-trade-os`, `bc/bc-37-farm-os`,
`dependabot/...`). Aucun doublon à reprendre.

---

## 1. Ce qui existe déjà et fonctionne (réutilisé, non reécrit)

### 1.1 Modèle de données — le socle « stock » est solide

| Composant | Emplacement | Constat |
|---|---|---|
| `travel_trip_seats` (inventaire par siège) | `api/database/migrations/tenant/2026_08_29_000907_6021_create_travel_trip_seats_table.php` | **Contrainte d'unicité** `(company_id, trip_id, seat_number)` + index `(company_id, trip_id, status)` + CHECK `status IN ('free','reserved','sold')` |
| `TravelTripSeat` | `api/app/Modules/TravelAgency/Domain/Models/TravelTripSeat.php` | `booking_id`, `passenger_id`, `reserved_until` : le rattachement siège↔réservation existe |
| `TravelTrip` | `.../Domain/Models/TravelTrip.php` | `total_seats` pilote la génération ; `vehicle_id` / `carrier_id` nullables |
| `TravelVehicle` | `.../Domain/Models/TravelVehicle.php` | Flotte propre, `seat_capacity` (CHECK `> 0`) |
| `GenerateTripSeatsAction` | `.../Application/Actions/GenerateTripSeatsAction.php` | Génération **transactionnelle** et **idempotente** (rejeu sans doublon) |

### 1.2 Anti-double-réservation — déjà en place, côté serveur

- **Verrou d'inventaire** : `CreateBookingAction` verrouille le trajet puis **toutes les lignes
  sièges** du trajet en `SELECT … FOR UPDATE` avant sélection
  (`api/app/Modules/TravelAgency/Application/Actions/CreateBookingAction.php`). Deux
  réservations concurrentes sont donc **sérialisées** — pas de course sur les places libres.
- **Idempotence** : clé `idempotency_key` + contrainte unique `(company_id, idempotency_key)` ;
  le rejeu est délégué au socle mutualisé `IdempotentGuestWrite`, avec gestion de course
  (`23505` → relecture de la réservation du gagnant au lieu d'une erreur). Un double clic / retry
  réseau ne crée jamais deux réservations.
- **Expiration des réservations temporaires** : `ExpirePendingBookingsJob` passe les `pending`
  dont `expires_at` est dépassé en `cancelled`, **libère les sièges** (`free`,
  `reserved_until = null`) et publie `travel.booking.expired.v1` **après commit**, avec clé
  d'idempotence `booking-expired-{id}`. Ordonnancé (`travel:expire-pending-bookings`, 5 min,
  `routes/console.php`).
- **Confirmation** : `ConfirmBookingAction` fait `pending → confirmed`, passe les sièges en
  `sold` (plus jamais libérés par l'expiration) et trace l'encaissement comptant dans
  `travel_payments` de façon **idempotente** (clé `cash-confirm:{bookingId}`).
- **Cohérence paiement/confirmation** : une réservation payante n'est confirmée que par le
  chemin serveur (callback/cash), jamais parce que le client a cliqué.

### 1.3 Multi-tenant et permissions

- Scope `BelongsToCompany` + marqueur fail-closed `tenant_scope_required`, résolution du tenant
  **par trajet / par référence** côté marketplace (`TravelMarketplaceController::withinTenantScoped()`).
- Convention « **404 sur, jamais 403** » sur la ressource cross-tenant (contrôleurs du module).
- Policies par ressource (`Policies/Travel*Policy.php`) et middleware dédiés
  (`EnsureTravelAgencyModuleMiddleware`, `TravelPartnerAuthMiddleware`, …).

### 1.4 Fronts

| Surface | Emplacement | Constat |
|---|---|---|
| Grand public (Next.js) | `front/travel-web` | recherche, détail, réservation, confirmation, suivi, compte client, VTC |
| Back-office (Vue) | `front/admin-dashboard/src/views/travel/*.vue` (15 vues) | réservations, check-in, billets, réseau, référentiel, rapports… |

### 1.5 Tests

**120+ fichiers** `api/tests/Feature/Travel/*` couvrent déjà — entre autres —
`TravelSeatAssignmentTest`, `TravelSeatAutoAssignmentTest`, `TravelTripSeatGenerationTest`,
`TravelBookingExpirationTest`, `TravelExpirePendingBookingsTest`, `TravelOsQrGpsConcurrencyTest`
(concurrence du check-in), `TravelIsolationTest`, `TravelRbacMatrixTest`, `TravelPublicShopApiTest`.
Les scénarios de concurrence critiques **sont déjà testés**.

---

## 2. Lacunes réelles identifiées (les seules chose à construire)

### Lacune A — Modèle d'états de siège trop étroit 🔴

`SeatStatus` ne connaît que **3 états** : `free`, `reserved`, `sold`.

La mission exige de distinguer : *disponible*, *sélectionné par l'utilisateur*,
*réservation temporairement en cours*, *réservé et confirmé*, *bloqué par l'opérateur*,
*paiement en attente*, *indisponible pour raison documentée*.

**Corrigé** : l'enum est étendu (voir §3) avec `selected`, `held`, `blocked`, `pending_payment`,
`unavailable`, chacun porteur d'un **libellé lisible** — sans casser les trois valeurs
canoniques persistées en base (`free` / `reserved` / `sold` restent la source de vérité du
stock).

### Lacune B — Aucun composant visuel de plan de sièges 🔴

Recherche `seat_map` / `seatMap` / `seat_layout` / `seatLayout` dans tout le dépôt : **zéro
résultat**. Aujourd'hui les sièges ne sont visibles que sous forme de :

- une **grille de boutons plate** dans `front/travel-web/src/components/booking-flow.tsx`
  (`grid-cols-6/8/10`, aucune rangée, aucun couloir, aucune distinction au-delà de
  libre/occupé/sélectionné) ;
- une **liste textuelle de numéros** dans les vues Vue back-office
  (`TravelBookingsView.vue`, `TravelCheckinView.vue`, `TravelTicketsView.vue`).

Aucune surface ne représente la **disposition réelle** du bus (rangées, couloir, sièges
simples/doubles), et la légende repose uniquement sur la couleur.

**Corrigé** : un composant visuel data-driven est livré pour les **deux surfaces** (voir §3).

### Points volontairement NON traités (hors périmètre de ce lot)

- L'exécution **complète** de la suite de tests backend (lourde : schéma reconstruit par test,
  timeout CI de 300 min) — un test ciblé suffit comme preuve de non-régression.
- La diffusion **temps réel** des disponibilités : le socle dispose déjà de l'outbox/événements ;
  brancher un canal temps réel est une évolution distincte. Le plan reste rafraîchi par
  rechargement serveur (comportement réel, pas simulé).

---

## 3. Correctifs livrés

### 3.1 `SeatStatus` enrichi

`api/app/Modules/TravelAgency/Domain/Enums/SeatStatus.php` — nouveaux cas + libellés FR :

| Valeur | Sens métier | Persisté ? |
|---|---|---|
| `free` | disponible | ✅ (canonique stock) |
| `selected` | sélectionné par l'utilisateur (local, pré-confirmation) | non (vue) |
| `held` | réservation temporairement en cours | via `reserved` + `reserved_until` |
| `reserved` | réservé (en attente de paiement/confirmation) | ✅ (canonique stock) |
| `pending_payment` | paiement en attente | dérivé du statut de paiement |
| `sold` | réservé et confirmé / vendu | ✅ (canonique stock) |
| `blocked` | bloqué par l'opérateur | ✅ |
| `unavailable` | indisponible pour raison documentée | ✅ |

> Les trois valeurs **canoniques** (`free`/`reserved`/`sold`) restent celles écrites en base ;
> les états de *présentation* (`selected`, `pending_payment`) sont projetés par le composant.
> Les valeurs persistables supplémentaires (`blocked`, `unavailable`) sont ajoutées au CHECK
> via une migration dédiée.

### 3.2 Composant visuel de plan de sièges (data-driven)

| Surface | Fichier | Rend |
|---|---|---|
| Grand public | `front/travel-web/src/components/seat-map.tsx` | React — grille par rangées, couloir central, sièges simples/doubles, légende **icône + texte** |
| Back-office | `front/admin-dashboard/src/components/travel/TravelSeatMap.vue` | Vue — même grille, mêmes états, mêmes règles |

Les deux composants :
- dérivent la disposition de `total_seats` (+ option de configuration `seats_per_row` /
  `aisle_after`) — donc de **données réelles**, pas d'une illustration statique ;
- consomment l'état réel des sièges renvoyé par l'API ;
- affichent une **légende non exclusivement colorée** (symbole graphique + libellé texte), lisible
  mobile et desktop ;
- ne sont **jamais** la source de vérité : la validation d'attribution reste serveur
  (`SELECT … FOR UPDATE`), le plan ne fait que refléter l'état métier.

### 3.3 Migration d'alignement du CHECK de statut

`api/database/migrations/tenant/*_extend_travel_trip_seats_status_check.php` — élargit le CHECK
`travel_trip_seats.status` aux valeurs persistables ajoutées, **sans** toucher aux données
existantes (les valeurs legacy restent valides).

---

## 4. Matrices demandées

### 4.1 Règles de réservation

| # | Règle |
|---|---|
| R1 | Un siège ne peut appartenir qu'à **une** réservation `pending`/`confirmed` par trajet |
| R2 | Sièges explicites : refus **409** si l'un d'eux n'est pas `free` au moment du verrou |
| R3 | Sans siège précisé : assignation auto (plus petit bloc contigu, fenêtre avant) |
| R4 | Le montant total est calculé **serveur** depuis les tarifs du trajet, jamais accepté du client |
| R5 | Une réservation n'est créée que sur un trajet `published` |
| R6 | `pending` expire (15 min) → `cancelled` + sièges libérés |
| R7 | `confirm` : `pending → confirmed`, sièges `sold`, encaissement tracé (idempotent) |
| R8 | `cancel`/`refund` libère la place selon la politique d'annulation |

### 4.2 Transitions de statut

| Objet | De | Vers | Déclencheur |
|---|---|---|---|
| Booking | `pending` | `confirmed` | `ConfirmBookingAction` |
| Booking | `pending` | `cancelled` | expiration / annulation |
| Booking | `confirmed` | `completed` | fin de voyage |
| Booking | `confirmed` | `cancelled` / `refunded` | annulation / remboursement |
| Seat | `free` | `reserved` | création de réservation (verrou) |
| Seat | `reserved` | `sold` | confirmation |
| Seat | `reserved` | `free` | expiration / annulation |
| Seat | `free` | `blocked` | blocage opérateur |
| Seat | `free` | `unavailable` | indisponibilité documentée |

### 4.3 Cas de concurrence

| Cas | Protections en place |
|---|---|
| Deux clients sur le même siège, simultanément | `FOR UPDATE` sur les lignes sièges → sérialisation |
| Double clic / rejeu réseau | `idempotency_key` unique + relecture |
| Course à l'insert | `23505` → relecture du gagnant (`IdempotentGuestWrite`) |
| Callback de paiement tardif | idempotence par `idempotency_key` du paiement |
| Expiration d'une réservation temporaire | job scopé tenant, idempotent (`booking-expired-{id}`) |
| Changement de siège / annulation | libération explicite, jamais silencieuse |
| Fuite inter-tenant | scope `BelongsToCompany` + `tenant_scope_required` (fail-closed) |

---

## 5. Tests

### 5.1 Résultats d'exécution réels (verbatim)

| Vérification | Commande | Résultat |
|---|---|---|
| Tests du plan de sièges (front) | `npx vitest run src/lib/seat-layout.test.ts` | ✅ **10/10 verts** |
| Typage TypeScript (front grand public) | `npx tsc --noEmit` | ✅ **0 erreur** |
| Lint front grand public | `npx eslint src/components/seat-map.tsx src/lib/seat-layout.ts src/lib/seat-layout.test.ts` | ✅ **0 erreur** |
| Lint back-office | `npx eslint src/components/travel/TravelSeatMap.vue` | ✅ **0 erreur** |
| Style PHP (Pint) | `php8.4 vendor/bin/pint --test <fichiers modifiés>` | ✅ **PASS** (2 fichiers) |
| Syntaxe PHP | `php8.4 -l <enum>` + `php8.4 -l <migration>` | ✅ aucune erreur |
| **Semantics de l'enum (exécuté)** | `php8.4 /tmp/seatstatus_check.php` (script autonome, sans base) | ✅ **8 états**, `persistableValues=free,reserved,sold,blocked,unavailable`, rétrocompat `free`/`reserved`/`sold` = `ok`, `tryFrom('bogus')` = `ok(null)` |

### 5.2 Test backend ciblé — **bloqué par le harnais, documenté (non contourné)**

`php8.4 artisan test --filter="TravelTripSeatGenerationTest"` ne peut pas démarrer hors
infrastructure Docker : le harnais `RefreshTenantDatabase` se connecte à l'hôte **`pgsql`**
(service du `docker-compose`), résolu depuis `.env` et **écrasant** `phpunit.xml` en local
(phpunit n'utilise `<env … />` que si la variable n'est pas déjà définie). Tentatives
successives — `DB_HOST=127.0.0.1` en ligne de commande, `.env.testing` dédié, `config:clear`,
`APP_KEY` généré — **la résolution reste `pgsql`**.

Décision (conforme à la gouvernance du dépôt `AGENTS.md` — « **no docker in the sandbox** ») :
**je ne contourne pas le harnais** (pas de binding `/etc/hosts`, pas de modification du code de
test). La preuve de non-régression est apportée par : (a) les tests du plan de sièges
exécutés, (b) l'exécution réelle des semantics de l'enum, (c) les vérifications statiques
(tsc/eslint/pint/php -l), et (d) la suite CI backend (job `API · PHPUnit`) qui s'exécute avec
l'infrastructure Docker adéquate au moment de la PR.

| Niveau | Contenu |
|---|---|
| Existant (réutilisé, **non modifié**) | 120+ tests `api/tests/Feature/Travel/*` — sièges, expiration, concurrence check-in, isolation tenant, RBAC. En particulier `TravelOsQrGpsConcurrencyTest` (concurrence) et `TravelBookingExpirationTest` / `TravelExpirePendingBookingsTest` (expiration des réservations temporaires) couvrent déjà les scénarios de double attribution exigés par la mission. |
| Ajouté | `front/travel-web/src/lib/seat-layout.test.ts` (10 tests) : disposition 2+2 avec couloir, ordre de numérotation, rangée partielle, symbole + libellé par état, non-sélectionnabilité des sièges occupés |

---

## 6. Limites restantes (honnêtes)

1. Le **temps réel** n'est pas branché : le plan est rafraîchi par rechargement serveur. Le
   socle d'événements existe, l'UI ne le consomme pas encore.
2. Les états `selected` / `pending_payment` sont des états de **présentation** ; seul ce que la
   base connaît (`free`/`reserved`/`sold`/`blocked`/`unavailable`) fait foi.
3. La **configuration de disposition** (sièges par rangée, position du couloir) est pilotée par
   paramètres avec une valeur par défaut raisonnable ; sa persistance par véhicule est une
   évolution naturelle mais distincte.
4. La suite de tests backend complète n'est pas rejouée de bout en bout en local : le harnais
   PHPUnit résout l'hôte `pgsql` (service Docker) et **ne se laisse pas reconfigurer** depuis le
   sandbox. Conformément à `AGENTS.md` (« no docker in the sandbox »), le harnais n'a pas été
   contourné ; la suite backend s'exécute dans le job CI `API · PHPUnit` au moment de la PR.
5. La fusion dans `main` est soumise aux **protections de branche** du dépôt (4 checks requis en
   contexte `AGENT_AUTHORED` : `guards`, `API · PHPUnit`, `Web builds`, `Repo hygiene`). La PR
   est ouverte et prête ; la fusion se fait une fois la CI verte.
