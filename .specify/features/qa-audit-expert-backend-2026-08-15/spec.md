# Feature Specification: Audit Expert Backend — Hardening Sécurité & Onboarding — 2026-08-15

**Feature Branch**: `qa-audit-expert-backend-2026-08-15`

**Created**: 2026-08-15

**Status**: In progress

**Input**: Mission propriétaire — audit expert complet de la plateforme (vitrine, web, admin, mobiles, workflows, API, logiques, onboarding, cohérence) ; tout manquement devient spec + tasks + incidents (méthode Spec Kit) puis est implémenté. Ce feature couvre le **backend API** (sécurité, onboarding, cohérence API). Audit : 5 workstreams parallèles (routes, sécurité/tenancy, logique/mort/onboarding, vitrine, admin, mobile) + vérifications manuelles sur `main` @ `80c034ff`.

## Contexte

L'audit expert du 2026-08-15 a confirmé 4 régressions de sécurité toujours ouvertes (héritées de la session QA 2026-08-14, jamais traitées), 1 feature d'onboarding morte (magic link), 1 bug de throttling qui casse le polling du trial, et plusieurs incohérences API (middleware tenant manquant, doublon de route, endpoint manquant pour la SPA admin).

## User Scenarios & Testing

### User Story 1 — Webhooks entrantes fail-closed (Priority: P1)

Un fournisseur (Stripe, Chargily, provider email) envoie un webhook signé. Si le secret n'est **pas configuré** sur l'environnement, l'API **refuse** le payload (500/503) au lieu de l'accepter sans vérification — aucune action métier (facturation, changement de statut) ne peut être déclenchée par un payload non authentifié.

**Why this priority**: Un webhook non vérifié est un vecteur d'attaque direct sur la facturation et les comptes (fraude, déni). C'est le P0 de l'audit.

**Independent Test**: `php artisan test --filter=Webhook*` + tests unitaires `StripeServiceTest` / `ChargilyServiceTest` — secret absent → `verifyWebhookSignature` retourne null (ou lève) ; secret présent → signature valide acceptée, invalide rejetée. `EmailBounceWebhookControllerTest` — secret absent → 503 ; présent → 200/400.

**Acceptance Scenarios**:

1. **Given** `services.stripe.webhook_secret` vide, **When** Stripe appelle `POST /api/v1/webhooks/stripe`, **Then** réponse 500/503, aucun traitement (actuellement : payload parsé et traité, `StripeService.php:122-125`).
2. **Given** `services.chargily.webhook_secret` vide, **When** Chargily appelle le webhook, **Then** refus sans traitement (`ChargilyService.php:33-35`).
3. **Given** `services.mail_bounce_webhook.secret` vide, **When** le webhook email-bounce est appelé, **Then** 503 (`EmailBounceWebhookController.php:32-43`).
4. **Given** le secret configuré, **When** la signature est invalide, **Then** 400 (inchangé — comportement strict existant conservé).

### User Story 2 — Durcissement auth : register sans tenant, login suspended, OAuth state (Priority: P1)

Un visiteur ne peut **pas** créer un compte employé sans contexte d'entreprise (ni invitation, ni trial) — `POST /auth/register` renvoie une erreur claire. Un compte **suspendu** ne peut plus obtenir de token (login email **et** Google). Le flux OAuth Google utilise un paramètre `state` (anti-CSRF).

**Why this priority**: Le register public crée des comptes inutilisables (sans `company_id`) — pollue la base et permet l'énumération ; le login suspended est une faille de contrôle d'accès ; l'OAuth sans state permet le login CSRF/l'account linking forcé. **Décision produit** : le register public est conservé (parcours self-onboarding « compte ordinary → company request », flux réel utilisé par les apps mobiles et testé) ; c'est le login de ces comptes qui est réparé (CompanyNotFoundException → login possible, token confiné aux routes pré-tenant).

**Independent Test**: Tests Feature `AuthFlowTest` — register → login de nouveau possible pour un compte ordinary sans company (201 + 200) ; `AuthService::login()`/`UserAuthService::login()` avec `status=suspended` → 403 (EmployeeNotActiveException / AccountSuspendedException) ; `handleGoogleCallback` sans `state` valide → 400. Test unitaire `UserAuthServiceTest` pour les deux chemins.

**Acceptance Scenarios**:

1. **Given** un compte ordinary créé par register (sans company), **When** il appelle `POST /auth/login` après déconnexion, **Then** 200 avec token (avant : `CompanyNotFoundException` → login impossible, `AuthService.php`) — le `TenantMiddleware` confine déjà ces comptes aux routes pré-tenant.
2. **Given** un `User`/`Employee` avec `status=suspended`, **When** il appelle `POST /auth/login` ou Google Sign-In, **Then** 403 (actuellement ignoré, `UserAuthService.php:34-52,72-108`).
3. **Given** `GET /auth/google`, **When** on initialise le redirect, **Then** un paramètre `state` (session) est généré ; **When** le callback revient sans état valide, **Then** 400 (`AuthController.php:160,166`). Un email Google inconnu n'est **plus** auto-créé sans company (401).

### User Story 3 — Onboarding trial réparé : magic link + statut pollable (Priority: P2)

Un prospect qui fait un trial guidé reçoit un **email avec magic link** une fois le sandbox provisionné, et l'UI vitrine peut **poller** `GET /trial/status` sans se faire throttler en 429 (actuellement : partage du bucket `throttle:5,15` avec signup/verify → 5 requêtes max / 15 min → l'UI « timed out »).

**Why this priority**: Le magic link est le seul canal documenté d'accès immédiat au sandbox ; le polling cassé rend le parcours de vente non fonctionnel au-delà de ~25 s de provisioning.

**Independent Test**: `php artisan test --filter=ProvisionDemoTenantJob` (vert, y compris `demo_access_token_hash` + mail `/demo-login/`) ; test Feature `TrialStatusThrottleTest` — 20 appels `GET /trial/status` sur 1 min sans 429.

**Acceptance Scenarios**:

1. **Given** un signup trial guidé, **When** le job `ProvisionDemoTenantJob` s'exécute avec succès, **Then** `issueDemoAccess()` est appelé (email + hash token persisté) — actuellement jamais appelé, `ProvisionDemoTenantJob.php:51` TODO + `:76` mort.
2. **Given** un `provisioning_token` valide, **When** l'UI poll `GET /trial/status` toutes les 5 s, **Then** pas de 429 avant ~1 min (limiteur dédié, `api/routes/api.php:98-101`).
3. **Given** le sandbox prêt, **Then** `login_url` exposé ET l'email magic link envoyé (canaux complémentaires, #2437).

### User Story 4 — Cohérence API : tenant, scoping OAuth calendrier, impersonations admin, doublon de route (Priority: P2)

Les endpoints partenaires (`/growth/partner/*`) sont isolés par tenant comme tous les autres modules ; les tokens OAuth calendrier sont scopés `company_id` ; la SPA admin peut impersonner via un endpoint qui existe ; plus aucun doublon de route webhook-test.

**Why this priority**: Le groupe growth écrit en cross-tenant sans middleware ; les tokens OAuth calendrier (CalendarConnection) ne sont pas scopés entreprise ; `POST /admin/impersonations` (SPA) n'existe pas (404) ; le doublon de route masque un risque de drift.

**Independent Test**: Test Feature `GrowthTenantIsolationTest` (404 cross-tenant) ; `CalendarConnectionTest` (création scopée) ; `AdminImpersonationTest` (POST `/admin/impersonations` → 201 + session) ; `RouteListTest` — une seule occurrence `POST /webhooks/{webhookEndpoint}/test`.

**Acceptance Scenarios**:

1. **Given** un employé tenant A, **When** il POST `/growth/partner/apply`, **Then** son `company_id` est appliqué et les données d'un autre tenant sont inaccessibles (`routes/modules/growth.php:10-16`).
2. **Given** une `CalendarConnection`, **When** créée, **Then** `company_id` renseigné (modèles `CalendarConnection.php:12-35`, `CalendarEvent.php:21-25`).
3. **Given** la SPA admin (guard `super_admin_api`), **When** elle POST `/admin/impersonations`, **Then** 201 (route absente aujourd'hui — seul `/platform/impersonations` existe, `api.php:284-286`).
4. **Given** `POST /webhooks/{webhookEndpoint}/test`, **Then** une seule route (`hr_extended.php:168` ou `:176`).

### User Story 5 — Password reset (Priority: P3)

Un employé qui a oublié son mot de passe peut demander un email de réinitialisation et définir un nouveau mot de passe via un token à durée limitée.

**Why this priority**: Flux absent de toute l'API (aucun controller ni route forgot/reset) ; les apps mobiles n'ont pas encore d'UI dédiée, mais l'API doit exister pour le parcours produit.

**Independent Test**: `php artisan test --filter=PasswordResetTest` — demande → email avec token ; reset avec token valide → nouveau hash + token invalide une fois utilisé ; token expiré → 422.

**Acceptance Scenarios**:

1. **Given** un email existant, **When** `POST /auth/forgot-password`, **Then** email envoyé (token 60 min) et réponse générique 200 (anti-énumération).
2. **Given** un token valide, **When** `POST /auth/reset-password`, **Then** mot de passe remplacé, anciens tokens révoqués.
3. **Given** un token expiré/déjà utilisé, **Then** 422.

### Edge Cases

- Webhook avec secret non configuré en environnement de dev local (tous les devs n'ont pas de clé Stripe) — le fail-closed doit être **configurable** (ex. `APP_ENV !== 'production'` → log warning mais refus quand même ? décision : refus systématique, `MAIL_MAILER=array`/`QUEUE_CONNECTION=sync` n'ont pas d'impact).
- OAuth Google state en contexte mobile natif (pas de session HTTP) : le flux `/auth/google/token` (access_token direct) n'est **pas** concerné par le state ; seule la redirection navigateur l'est.
- Register : le flux mobile `leopardo_employee` propose `/register` — l'écran reste, mais l'appel renvoie 422 avec message ; l'UI mobile devra orienter vers invitation/trial (task séparée, feature mobile).
- Suspended : ne pas casser le lockout existant (`locked_until`) — les deux mécanismes coexistent.

## Requirements

### Functional Requirements

- **FR-001**: Le système DOIT refuser (500/503) tout webhook entrant dont le secret de signature n'est pas configuré, pour Stripe, Chargily et email-bounce.
- **FR-002**: `POST /auth/register` reste public (parcours self-onboarding company-request) ; le login DOIT fonctionner pour ces comptes ordinary sans company (token confiné pré-tenant) ; aucun compte suspended ne DOIT obtenir de token.
- **FR-003**: Le login (email + Google) DOIT rejeter les comptes `status != active` avec `AccountSuspendedException`.
- **FR-004**: Le flux OAuth Google navigateur DOIT émettre et valider un paramètre `state` lié à la session.
- **FR-005**: `ProvisionDemoTenantJob` DOIT appeler `issueDemoAccess()` après provisioning réussi (email magic link + hash token persisté).
- **FR-006**: `GET /trial/status` DOIT avoir un limiteur dédié (au moins 60 req/min par IP/token) distinct de `throttle:5,15`.
- **FR-007**: Le groupe `growth/partner/*` DOIT être soumis au middleware `tenant`.
- **FR-008**: `CalendarConnection` et `CalendarEvent` DOIVENT porter `company_id` (migration tenant + modèle).
- **FR-009**: La SPA admin DOIT disposer de `POST /admin/impersonations` (alias du flux platform, mêmes contrôles).
- **FR-010**: `POST /webhooks/{webhookEndpoint}/test` DOIT être déclaré une seule fois.
- **FR-011**: Le système DOIT offrir `POST /auth/forgot-password` et `POST /auth/reset-password` (token 60 min, usage unique).

### Key Entities

- **WebhookSecret**: secret de signature par fournisseur (`config/services.php`) — comportement fail-closed si absent.
- **Employee/User**: `status` (`active|suspended|...`) — gate de login ; `company_id` — contexte tenant obligatoire.
- **trial_provisionings**: statut `pending|ready|failed` + `provisioning_token` + `login_url`.
- **CalendarConnection**: tokens OAuth calendrier — scope `company_id` ajouté.
- **ImpersonationSession**: session d'impersonation super-admin.

## Success Criteria

### Measurable Outcomes

- **SC-001**: 0 endpoint webhook acceptant un payload non vérifié (3 services, tests de non-régression).
- **SC-002**: 100 % des logins suspended rejetés (email + Google) ; register→login re-fonctionnel pour le parcours company-request.
- **SC-003**: 100 % des trials guidés aboutissent à un email magic link + `trial_provisionings.status=ready` pollable.
- **SC-004**: Suite Feature/Unit verte sur la branche (aucune régression), PHPStan strict level 8 : 0 erreur, Pint : 0 fichier.
- **SC-005**: Aucun doublon de route ; endpoint impersonation admin 201 testé.

## Assumptions

- Les flux mobile natifs restent hors scope de ce feature (tasks documentées dans la feature mobile).
- La vérification d'email (`MustVerifyEmail`) est hors scope : tâche P3 documentée, impact transversal trop large pour ce run (décision : issue dédiée).
- Le register reste accessible pour l'activation d'invitation (flux `/onboarding/invitation/{token}/activate` inchangé).
- En local/dev, les tests n'exigent pas de secret réel : les tests webhooks configurent le secret dans le setup.
