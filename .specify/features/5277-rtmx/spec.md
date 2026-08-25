# Feature Specification: RTMX — socle plateforme temps réel / réseau faible (#5277)

**Feature Branch**: `mod/platform/5277-rtmx`

**Created**: 2026-08-24

**Status**: Implementation

**Input**: Issue #5277 — [P1][mobile][perf] RTMX — pointage mobile temps réel (gap Phase 1).
Références : `ROADMAP.md` Phase 1 « ~80 % — reste RTMX » ; `docs/plan/PLAN_100PCT.md` §6.3 (ligne #5277) ;
ADR-0016 (`docs/architecture/adr/0016-attendance-smartattendance-fusion.md`) pour le contrat API `/attendance/*`.

## Problème

La Phase 1 du ROADMAP (~80 %) a un trou : l'expérience mobile temps réel du pointage
(pointage depuis le téléphone, synchronisation) n'a pas de socle plateforme côté serveur.

Constat dans le code au 2026-08-24 :

- Les apps mobiles (`leopardo_employee`/`leopardo_hr`/`leopardo_manager`) appellent déjà
  `/attendance/*` (check-in, check-out, today, config, geo-events) et maintiennent une file
  hors-ligne locale (`offline_punches`, règle F-21 « 1er pointage gagne ») rejouée par
  `OfflineSyncService` (`leopardo_core`).
- Côté serveur, il n'existe **aucun support HTTP conditionnel** (ni `ETag`, ni 304) et
  **aucune idempotence** pour les écritures : un rejeu réseau (timeout après commit serveur,
  retry, double POST de la file hors-ligne) peut créer un doublon ou forcer un re-téléchargement
  complet d'une réponse pourtant inchangée.
- Le `api` group ne connaît que `CompressResponse` (gzip) — utile mais insuffisant pour
  « pointer en < 3 s depuis le téléphone » (DoD #5277).

## Décision

Livrer le socle **plateforme** (périmètre anti-collision #5277 : `api/app/Core/**` +
`bootstrap/**` + lang) sous forme de **deux middleware transverses** du groupe `api` :

1. **`HttpCacheMiddleware`** (`App\Core\Http\Middleware`) — GET conditionnels pour les
   réponses JSON `2xx` : `ETag` fort (sha1 du corps), `Cache-Control: private, max-age=0,
   must-revalidate`, `Vary: Accept-Encoding`, et `304 Not Modified` quand le client envoie
   `If-None-Match` correspondant. Les lectures répétées (`/attendance/today`, `/attendance/config`,
   `/me/*`, rapports) deviennent quasi-instantanées en réseau faible : le corps n'est plus
   retransmis tant qu'il n'a pas changé.
2. **`IdempotencyMiddleware`** (`App\Core\Http\Middleware`) — rejeu sûr des écritures
   (`POST`/`PUT`/`PATCH`) : quand le client envoie `Idempotency-Key` + `Authorization`,
   la première réponse `2xx` est mémorisée 24 h et rejouée à l'identique pour toute retentative
   avec la même clé et le même corps (header `Idempotent-Replayed: true`). Clé de cache scopée
   par token (`sha1` du header Authorization) + clé client + signature méthode/URI/corps →
   aucune fuite inter-utilisateur possible. Verrou anti-course (409 `IDEMPOTENCY_IN_PROGRESS`).

**Pourquoi ce choix** : le pointage mobile se résume à « lire l'état → écrire un pointage →
relire ». Le cache conditionnel réduit la latence des lectures, l'idempotence rend les
écritures sûres en réseau faible ; les deux vivent dans le groupe `api` sans toucher au
domaine Attendance (verrouillé par les agents #5355/#5265) ni aux apps mobiles (convergence
PWA vs app → #2601).

## Périmètre

### Dans le périmètre

- `api/app/Core/Http/Middleware/HttpCacheMiddleware.php` (nouveau)
- `api/app/Core/Http/Middleware/IdempotencyMiddleware.php` (nouveau)
- `api/bootstrap/app.php` — enregistrement `api(append: [...])`
- `api/lang/{fr,en,tr,ar}/errors.php` — 2 codes : `INVALID_IDEMPOTENCY_KEY` (422),
  `IDEMPOTENCY_IN_PROGRESS` (409) — parité ×4 conservée (`LangCatalogParityTest`)
- `api/tests/Feature/Platform/HttpCacheMiddlewareTest.php` + `IdempotencyMiddlewareTest.php`
- `CHANGELOG.md` (1 ligne en tête d'`[Unreleased]`) + tracker `docs/plan/PLAN_100PCT.md` (ligne #5277)

### Hors périmètre

- Module `Attendance` / `SmartAttendance` (agents #5355, #5265) — aucun changement de route,
  contrôleur ou service métier.
- Apps mobiles `front/mobile_apps/**` (agents #2601, #2755, #5279) — la bascule `If-None-Match`
  et l'envoi d'`Idempotency-Key` côté client font l'objet d'une issue de suite (convergence #2601).
- Décision « PWA ou app native » (#5277, sous-arbitrage #2601) — non bloquante pour ce socle.

## User Scenarios & Testing

### US1 — L'employé rouvre l'écran de pointage : la lecture est instantanée (Priority: P1)

`GET /api/v1/attendance/today` est rejouée sans retransmission tant que rien n'a changé.

**Independent Test**: deux `GET` successifs sur une route JSON `2xx` du groupe `api` —
le premier renvoie `200` + `ETag`, le second avec `If-None-Match: <etag>` renvoie
`304 Not Modified` (corps vide).

**Acceptance Scenarios**:

1. **Given** une réponse JSON `2xx`, **When** le client la rejoue avec `If-None-Match`,
   **Then** `304` sans corps (bande passante quasi nulle).
2. **Given** une réponse JSON `2xx` inchangée, **When** le client relit sans header,
   **Then** `200` avec le même `ETag`.
3. **Given** une réponse modifiée, **When** le client envoie l'ancien `ETag`,
   **Then** `200` avec le nouveau corps (jamais de 304 mensonger).
4. **Given** une requête non-GET, **When** elle passe par l'API, **Then** aucun header de
   cache n'est ajouté (les écritures ne sont jamais cachées).

### US2 — Le pointage hors-ligne est rejoué sans doublon (Priority: P1)

Le client envoie `Idempotency-Key` (générée par l'app à la création du pointage) ;
une retentative réseau reçoit la réponse de la première exécution, pas un second pointage.

**Independent Test**: deux `POST` identiques (même clé, même corps, même token) sur une route
JSON `2xx` du groupe `api` — la seconde réponse est identique à la première
(status + corps) et porte `Idempotent-Replayed: true`.

**Acceptance Scenarios**:

1. **Given** une écriture réussie (`2xx`) avec clé + token, **When** le client retente à
   l'identique, **Then** réponse rejouée à l'identique (pas de double exécution).
2. **Given** la même clé mais un **corps différent**, **When** le client envoie,
   **Then** la requête est traitée normalement (signature corps dans la clé de cache —
   pas de fausse relecture).
3. **Given** une requête sans `Authorization` (anonyme), **When** elle porte `Idempotency-Key`,
   **Then** elle est traitée normalement (pas de déduplication non authentifiée).
4. **Given** une écriture qui échoue (non-`2xx`), **When** le client retente à l'identique,
   **Then** la requête est ré-exécutée (rien n'est mémorisé).
5. **Given** une clé mal formée (< 8 caractères), **When** le client envoie,
   **Then** `422 INVALID_IDEMPOTENCY_KEY` (message localisé ×4).
6. **Given** deux requêtes strictement identiques simultanées, **When** la première est en
   cours, **Then** la seconde reçoit `409 IDEMPOTENCY_IN_PROGRESS` + `Retry-After: 1`.

### US3 — Aucune fuite inter-utilisateur (Priority: P1)

**Independent Test**: deux tokens différents avec la même `Idempotency-Key` produisent
deux exécutions distinctes (clé de cache scopée par token).

**Acceptance Scenarios**:

1. **Given** deux utilisateurs, **When** ils utilisent la même clé avec le même corps,
   **Then** chacun reçoit sa propre réponse (jamais la réponse de l'autre).

## Critères d'acceptation (DoD #5277 — socle plateforme)

- [ ] Feature tests verts : `HttpCacheMiddlewareTest`, `IdempotencyMiddlewareTest`
      (scénarios US1/US2/US3)
- [ ] CI verte : `tests.yml` (suite backend), PHPStan, parité lang `LangCatalogParityTest`,
      OpenAPI (aucune route nouvelle → aucune opération à documenter)
- [ ] 0 comportement existant modifié : les deux middleware sont pass-through sans
      `Idempotency-Key`/`If-None-Match` (aucun test existant ne dépend de l'absence
      d'`ETag`/`Cache-Control`)
- [ ] CHANGELOG : 1 ligne en tête d'`[Unreleased]` ; tracker `PLAN_100PCT.md` ligne #5277
- [ ] Procédure de recette pilote « < 3 s » documentée dans la spec (l'évaluation terrain
      reste un test pilote, hors CI)

## Procédure de recette pilote (DoD #5277 — « pointer en < 3 s »)

1. Sur un réseau 4G dégradé (throttle), ouvrir l'écran de pointage employé.
2. Premier chargement : `GET /attendance/today` (200 + ETag) — mesurer.
3. Rechargements suivants : `If-None-Match` → 304 — le rendu doit être < 3 s
   (corps non retransmis).
4. Passer en mode avion : pointer → pointage en file `offline_punches`.
5. Revenir en ligne : `OfflineSyncService` rejoue le pointage avec `Idempotency-Key` ;
   vérifier l'absence de doublon dans `GET /attendance/today`.
