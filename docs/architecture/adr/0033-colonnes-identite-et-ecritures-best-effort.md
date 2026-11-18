# ADR-0033 — Colonnes d'identité assignables et écritures best-effort dans une transaction

- **Date** : 2026-11-18
- **Statut** : accepté
- **Portée** : API Laravel (`api/`), modules métier, tables tenant PostgreSQL
- **Tranche** : #9098 (dette de tests/API Travel)

## Contexte

Deux tranches de la campagne #9098 ont révélé, dans le même module, deux défauts que
le même style de test ne pouvait pas voir : un test qui vérifie l'agrégat *total*
reste vert alors que les colonnes *d'identité* de l'agrégat sont vides, et un
`catch` autour d'un `INSERT` « best-effort » ne protège rien du tout quand il
s'exécute dans une transaction déjà ouverte.

### Constat 1 — `Model::create()` filtre en silence

`CorporateBookingService::createQuote()` construisait le devis ainsi :

```php
TravelQuote::query()->create([
    'company_id'           => $account->company_id,
    'corporate_account_id' => $account->id,
    'trip_id'              => $trip->id,
    'class_id'             => $classId,
    'passengers_count'     => $passengersCount,
    'total_amount_minor'   => $total,
    // …
]);
```

`TravelQuote::$fillable` ne contenait que `reference`, `trip_id`, `status`,
`customer_contact_id`, `passenger_count`, `passengers_json`, `total_amount_minor`,
`currency`, `expires_at`, `booking_id`, `idempotency_key`, `created_by_user_id`.
Eloquent **ignore** tout attribut absent de `$fillable` : `corporate_account_id`,
`class_id` et `passengers_count` ont donc été perdus, sans exception, sans log,
sans `QueryException`.

Conséquence observable : `POST /travel/corporate-quotes` répondait bien **201**
(le total, lui, était assignable), le test assertait `total_amount_minor = 60000`
et passait, mais `POST /travel/bookings` adossé à ce devis répondait **422**
`« Devis incohérent avec le compte corporate »` — l'invariant « devis respecté »
(`CorporateBookingService::createGroupBooking()`) compare trois colonnes qui
valaient toutes `null`. Le contrôle ne pouvait donc **jamais** passer, et le
message d'erreur désignait le client alors que la cause était dans le service.

### Constat 2 — un `catch` sans savepoint laisse la transaction avortée

`TravelNotificationConsumer::log()` journalise chaque tentative de notification
(`sent` / `skipped` / `failed`) dans `travel_notification_logs`, dont la colonne
`company_id` est `uuid NOT NULL`. L'insertion ne la fournissait pas : le trait
`BelongsToCompany` ne l'alimente que s'il existe une compagnie courante, ce qui
n'est pas le cas quand le consommateur est appelé hors requête HTTP (job, console,
test). L'insertion échouait donc en `SQLSTATE[23502]` (not-null violation).

L'échec était **avalé** par le `try/catch` qui entoure l'insertion. Or ce `catch`
n'isole rien : sous PostgreSQL, toute erreur dans une transaction la marque
`aborted`, et toutes les instructions suivantes répondent
`SQLSTATE[25P02]: current transaction is aborted, commands ignored until end of
transaction block`. Le journal n'était jamais écrit **et** les requêtes suivantes
du même test — consentements WhatsApp, `app_notifications`, etc. — tombaient en
25P02. Le message JUnit nommait la *conséquence* (une `select exists(...)` sur
`travel_notification_consents`), jamais la cause ; seule la lecture du canal de log
`structured` (`travel.notification.log-failed`) donnait le 23502 d'origine.

## Décision

### R1 — Toute colonne qui définit l'identité d'une agrégation est `fillable` **et** castée

Une colonne écrite par un service (`create()` / `update()`) est une donnée
d'entrée du modèle : si elle fait partie de l'identité métier de l'agrégat (à qui
il appartient, sur quoi il porte, combien il engage), elle doit être déclarée dans
`$fillable` **et** dans `$casts` (entiers, enums, dates). Une agrégation dont une
colonne d'identité est nullable en base doit distinguer explicitement les deux cas
d'usage (`passenger_count` historique nullable vs `passengers_count` du devis
corporate) plutôt que fusionner les deux noms.

### R2 — Un test qui n'assert que l'agrégat ne prouve pas l'identité de l'agrégat

Un test de création doit asserter au moins une colonne d'identité relue **depuis la
base** (pas seulement le total, ni le code HTTP). `assertStatus(201)` +
`total_amount_minor` laissait passer un devis sans compte : c'est le test de
réservation qui a payé, avec un 422 trompeur.

### R3 — Une écriture best-effort dans une transaction passe par un savepoint

« Best-effort » n'est vrai que si l'écriture est isolée : `DB::transaction($fn, 1)`
(transaction imbriquée = `SAVEPOINT`) autour de l'insertion, puis `catch`. Un
`catch` sans savepoint ne rend pas la main à l'appelant : il la rend à une
transaction avortée. Corollaire : toute valeur `NOT NULL` alimentée par un contexte
ambiant (tenant courant, utilisateur courant) doit être passée **explicitement**
par l'appelant — l'ambiante n'existe pas hors requête HTTP.

## Conséquences

- `TravelQuote::$fillable` expose `corporate_account_id`, `class_id`,
  `passengers_count`, avec casts `integer`.
- `TravelNotificationConsumer::log()` reçoit `company_id` en paramètre **requis**
  (un oubli d'appel devient une `ArgumentCountError`/erreur PHPStan, jamais une
  colonne `null` silencieuse) et écrit dans un savepoint.
- Le diagnostic d'un 25P02 ne passe plus par le code appelant mais par le canal de
  log `structured` : c'est la seule trace d'une exception avalée.

## Anti-règles

- Ne jamais ajouter une colonne à `$fillable` sans vérifier qu'elle existe dans la
  migration (une colonne inexistante mais `fillable` transformerait l'insertion
  silencieuse en `42703` bruyant — la garde « une table, une migration » reste la
  référence).
- Ne jamais « corriger » un 25P02 en rouvrant une connexion, en vidant le cache de
  transaction ou en déplaçant l'assertion : la cause est l'instruction qui a échoué
  avant, et elle est dans le log.
- Ne jamais relâcher une assertion pour absorber une identité manquante : le 422
  venait bien d'un défaut d'API, pas d'un test trop strict.

## Références

- `api/app/Modules/TravelAgency/Domain/Models/TravelQuote.php`
- `api/app/Modules/TravelAgency/Infrastructure/Services/CorporateBookingService.php`
- `api/app/Modules/TravelAgency/Infrastructure/Services/TravelNotificationConsumer.php`
- `api/database/migrations/tenant/2026_08_30_000007_6067_create_travel_notification_tables.php`
- ADR-0032 (autorisation avant validation, encodage de query, statuts et types JSON)
