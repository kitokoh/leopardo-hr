# Feature Specification: Renderer global d'exceptions — fin des messages bruts (issue #3810)

**Feature Branch**: `fix/3810-exception-renderer`

**Created**: 2026-08-15

**Status**: Draft → Implemented

**Input**: QA 360° 2026-08-15 — `bootstrap/app.php:187-196` renvoie
`$exception->getMessage()` verbatim pour toute HttpException non-404 ;
combiné aux `abort(422, $e->getMessage())` restants (4 sites Payroll), des
détails internes (SQL, chemins serveur) fuient dans les réponses JSON.

## Problème

- `TaxSlabController:164`, `SocialContributionController:157`,
  `RateValidationAdminController:69,94` : `abort(422, $e->getMessage())` sur
  `DomainException` — un message interne part au client.
- Renderer : `error`/`message` = `getMessage()` brut pour toute HttpException
  non-404 (hors 404), sans code stable ni `localized_message`.

## User Scenarios & Testing

### User Story 1 — Les réponses d'erreur ne fuient plus de détail interne (P1)

Un client API reçoit un code stable + message générique localisé, jamais un
SQLSTATE, un chemin serveur ou une trace.

**Independent Test**: `php artisan test --filter=ExceptionRendererSanitizationTest` → 5/5 verts.

**Acceptance Scenarios**:

1. **Given** une HttpException 422 dont le message contient `SQLSTATE[…]/var/www`,
   **When** le client appelle l'API, **Then** réponse 422 `{error: VALIDATION_FAILED,
   message: VALIDATION_FAILED, localized_message: <traduit>}` sans `SQLSTATE` ni `/var/www`.
2. **Given** une HttpException 500 avec trace (`#0 /var/www/…`), **When** appel API,
   **Then** `SERVER_ERROR` générique, aucune trace dans le corps.
3. **Given** un message d'abort volontaire (`RATE_EDIT_LOCKED`), **When** appel API,
   **Then** le message est conservé (contrat existant intact).

### User Story 2 — Les 4 sites Payroll ne propagent plus getMessage() (P1)

**Independent Test**: `rg "abort\\(4\\d\\d,.*getMessage" app/` → 0 résultat ;
tests Payroll existants verts.

**Acceptance Scenarios**:

1. **Given** un `DomainException` à la soumission d'un barème, **When** l'API répond,
   **Then** 422 avec message localisé `rate_submit_failed` (4 locales) et détail en log.
2. **Given** un échec d'approbation/rejet admin, **When** l'API répond, **Then** message
   localisé (`rate_approve_failed` / `rate_reject_failed`), détail en log.

## Edge Cases

- `abort(422, '')` (message vide) → code stable VALIDATION_FAILED.
- Headers de throttling (Retry-After) préservés dans les deux branches.
- Les messages statiques localisés (`__('payroll.rate_overlap_conflict')`) restent exposés.

## Requirements

### Functional Requirements

- **FR-001**: Le renderer HttpException ne renvoie jamais de message à signature
  interne (SQLSTATE, /var/www, vendor/laravel, getMessage(), traces, .php:NN).
- **FR-002**: Les messages sanitizés exposent `error`/`message` = code stable et
  `localized_message` traduit (6 codes × 4 locales).
- **FR-003**: Le détail du message original est loggé côté serveur.
- **FR-004**: Aucun `abort(4xx, getMessage())` ne subsiste dans `app/`.

### Key Entities

- `api/lang/{fr,en,tr,ar}/errors.php` : codes `BAD_REQUEST`, `CONFLICT`,
  `VALIDATION_FAILED`, `TOO_MANY_REQUESTS`, `SERVICE_UNAVAILABLE`, `HTTP_ERROR`.
- `api/lang/{fr,en,tr,ar}/payroll.php` : `rate_submit_failed`,
  `rate_approve_failed`, `rate_reject_failed`.

## Success Criteria

### Measurable Outcomes

- **SC-001**: `ExceptionRendererSanitizationTest` 5/5 vert.
- **SC-002**: 0 occurrence `abort(4xx, getMessage())` dans `api/app`.
- **SC-003**: PHPStan strict + Backend Coverage verts (CI).
