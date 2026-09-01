# Feature Specification: Vague QA Complète 2026-08-15 — Vitrine, Web, Admin, Mobile, API, Onboarding

**Feature Branch**: `qa-wave-2026-08-15`

**Created**: 2026-08-15

**Status**: Draft

**Input**: Mission de test exhaustive de la plateforme (session 2026-08-15) — vitrine
web (live), console admin (live), API (live + statique), apps mobiles (cross-check
statique), workflows, onboarding, logique métier et cohérence docs ↔ code. 18 constats
confirmés, chacun tracé par une issue GitHub (#2628–#2645).

## User Scenarios & Testing

### User Story 1 — Le parcours d'achat/essai vitrine est honnête et fonctionnel (Priority: P1)

Un prospect clique « Démarrer l'essai » sur la vitrine, choisit un plan payant, saisit
ses coordonnées de paiement… et doit soit réellement être débité (Stripe live), soit
recevoir une erreur explicite. Aujourd'hui, en production, le checkout répond
`sandbox:true` (« Paiement simulé — aucune carte débitée »), la page succès annonce
« Simulation réussie — Mode sandbox » et aucun compte n'est provisionné
(`provisioned:false`) : le visiteur croit avoir souscrit, il n'a rien.

**Pourquoi P1** : monétisation et acquisition (source principale d'onboarding
self-service) cassées en production ; un faux succès de paiement est pire qu'une erreur
(Crédibilité + RGPD des données de carte collectées par une UI décorative).

**Test indépendant** : le déploiement production ne retourne jamais `sandbox:true`
(sauf flag explicite `SANDBOX_CHECKOUT=true`) ; l'UI n'affiche la carte test que si le
mode sandbox est actif ; le parcours sandbox de dev reste fonctionnel.

**Acceptance Scenarios**:

1. **Given** un déploiement sans `STRIPE_SECRET_KEY` live, **When** un prospect POST
   `/api/billing/checkout`, **Then** réponse 503 `CHECKOUT_UNAVAILABLE` explicite (pas
   de faux succès), et la page affiche une erreur claire.
2. **Given** `STRIPE_SECRET_KEY` live, **When** checkout, **Then** session Stripe réelle
   créée via `/api/v1/billing/checkout` (backend) et redirection vers Stripe.
3. **Given** le mode sandbox explicitement activé (dev/staging), **When** checkout,
   **Then** le flux simulé fonctionne mais la page succès l'annonce clairement et le
   provisioning trial réel s'exécute.

### User Story 2 — L'essai guidé (guided trial) provisionne un tenant utilisable (Priority: P1)

Un prospect s'inscrit via `POST /trial/signup` (workflow `guided_trial`), le tenant est
provisionné en arrière-plan, et `GET /trial/status` finit par dire `ready` avec un
login **utilisable**. Aujourd'hui le manager est créé avec un mot de passe aléatoire
jamais communiqué, `ProvisionDemoTenantJob::issueDemoAccess()` (magic link) n'est jamais
appelée (TODO dans `handle()`), donc « ready » ne mène nulle part.

**Pourquoi P1** : l'onboarding self-service est le parcours d'acquisition principal ;
un essai « prêt » inutilisable est un tunnel de conversion cassé.

**Acceptance Scenarios**:

1. **Given** un signup `guided_trial`, **When** le job de provisioning s'exécute,
   **Then** un email de magic link (ou credentials provisoires) est réellement envoyé au
   manager.
2. **Given** un trial non provisionné, **When** `GET /trial/status`, **Then** jamais
   `ready` tant que les credentials ne sont pas communicables.
3. **Given** la dead code `issueDemoAccess()`, **When** revue, **Then** supprimée ou
   câblée — plus de TODO.

### User Story 3 — Un compte suspendu ne peut plus se connecter nulle part (Priority: P1)

Un administrateur suspend un compte (employé `users.status`, ou `super_admins.status`).
Le compte ne doit plus pouvoir obtenir de token via aucun chemin (user, super-admin,
Google), et ses tokens existants doivent être révoqués. Aujourd'hui `UserAuthService`
ignore `status`, le login super-admin ignore `super_admins.status`, les callbacks Google
bypassent totalement les gardes, et la suspension ne révoque pas les tokens Sanctum.

**Pourquoi P1** : sécurité/RGPD — un compte suspendu (départ, incident) conserve un
accès complet à la paie et aux données RH.

**Acceptance Scenarios**:

1. **Given** un user `status=suspended`, **When** `POST /user/login`, **Then** 403
   `ACCOUNT_SUSPENDED`.
2. **Given** un super-admin désactivé, **When** `POST /platform/auth/login`, **Then**
   403.
3. **Given** un compte suspendu avec un token existant, **When** `GET /auth/me`,
   **Then** 401 (tokens révoqués à la suspension).
4. **Given** un employé suspendu, **When** callback/token Google, **Then** 403.

### User Story 4 — L'onboarding mobile fonctionne (employee/HR/manager) (Priority: P1)

Un employé ou un RH complète/skippe une étape d'onboarding depuis l'app mobile. Les apps
employee et HR envoient `POST /onboarding-setup/{id}/complete|skip` alors que le backend
ne déclare que `PATCH` → 405 : les étapes ne peuvent jamais être validées. Par ailleurs
l'organigramme HR/Manager appelle `GET /departments/{departmentId}/hierarchy`, route
inexistante → 404.

**Pourquoi P1** : fonctionnalité annoncée (onboarding guidé mobile, organigramme) qui
échoue systématiquement dans les apps les plus utilisées.

**Acceptance Scenarios**:

1. **Given** l'app employee (ou HR) connectée, **When** compléter une étape, **Then**
   2xx (le client envoie PATCH avec le stepKey string).
2. **Given** un manager RH, **When** ouvrir l'organigramme d'un département, **Then**
   l'arbre (enfants + effectif) est affiché (nouvelle route backend scopée tenant).

### User Story 5 — La console admin plateforme est complète et honnête (Priority: P2)

Le super-admin doit pouvoir gérer trainings et webhooks depuis la console, sans handlers
factices ni impasses. Aujourd'hui `GET /v1/training/sessions|enrollments` et tout le CRUD
`/v1/webhooks*` (+ test) n'existent qu'en scope tenant → 401 super-admin (3 des 8 gaps
QA-8 restants) ; `EditUserModal` (4 actions) et la maintenance `SystemAlertsOverlay`
sont des faux handlers (setTimeout + toast) ; la command palette pointe `/vehicles`
(404) et 7 entrées tenant redirigent vers `/` ; 2 clés i18n manquantes et des clés
brutes dans `document.title`.

**Pourquoi P2** : le cockpit admin est une surface de vente (démo) ; des actions
factices ou des 401 silencieux détruisent la crédibilité (règle cockpit : jamais de
données/actions fabriquées).

**Acceptance Scenarios**:

1. **Given** un super-admin connecté, **When** ouvrir TrainingView/WebhooksView, **Then**
   données réelles (routes `/admin/*` dédiées, policy admin).
2. **Given** la console, **When** modifier un user / reset password, **Then** appel API
   réel (réseau) et erreurs backend affichées.
3. **Given** Ctrl+K, **When** chercher « vehicles », **Then** route `/fleet` existante.

### User Story 6 — Le référentiel API et les docs sont cohérents avec le code (Priority: P3)

L'OpenAPI (contrat public, 424 chemins) doit couvrir l'implémentation (543 chemins) ;
119 chemins de code sont absents de la spec (drift). Les routes robots annoncent un
sitemap mort ; des pages vitrine sont FR-only malgré le sélecteur EN/TR/AR ; le
`updateCountry` est dupliqué (artefact de merge).

**Pourquoi P3** : dette de cohérence — impacts SEO, intégrations tierces (OpenAPI) et
maintenabilité.

**Acceptance Scenarios**:

1. **Given** le script `check-openapi-route-coverage.py`, **When** exécuté, **Then** 0
   route de code absente de la spec (ou blocs marqués hors contrat).
2. **Given** `GET /api/robots`, **When** parsé, **Then** plus de référence `/api/sitemap`.
3. **Given** la page `/contact` en EN, **When** sélecteur de langue, **Then** contenu EN
   (ou fallback assumé).

## Requirements

### Functional Requirements

- **FR-001**: Le checkout production ne doit jamais simuler un paiement sans flag explicite (`SANDBOX_CHECKOUT`).
- **FR-002**: Le workflow `guided_trial` doit envoyer un magic link / credentials au manager provisionné.
- **FR-003**: Tous les chemins d'authentification doivent rejeter les comptes non-actifs (403) et révoquer les tokens à la suspension.
- **FR-004**: Les apps mobile employee/HR doivent utiliser `PATCH /onboarding-setup/{stepKey}/complete|skip`.
- **FR-005**: `GET /departments/{department}/hierarchy` doit exister (scopé tenant, RBAC manager).
- **FR-006**: La console admin doit disposer de routes `/admin/training/*` et `/admin/webhooks*` (policy admin).
- **FR-007**: Les groupes `/ai/*` et `/growth/partner/*` doivent porter le middleware tenant complet (statut société/employé).
- **FR-008**: `/auth/register` ne doit plus créer de compte activable sans `company_id`.
- **FR-009**: `UserInvitationService::accept()` doit rejeter les invitations de sociétés non actives.
- **FR-010**: Les actions admin doivent appeler des endpoints réels (pas de setTimeout+toast factice).
- **FR-011**: La command palette et les titres d'onglets admin doivent être cohérents (routes existantes, clés i18n traduites).
- **FR-012**: L'OpenAPI doit couvrir les routes de code (drift → 0).
- **FR-013**: Les routes robots ne doivent pas référencer de sitemap inexistant.

### Key Entities

- **Trial/Onboarding**: `trial_signups`, `ProvisionDemoTenantJob`, `ProvisionGuidedTrial`, `user_lookups`, invitations.
- **Auth**: `users.status`, `super_admins.status`, tokens Sanctum, `UserAuthService`, `AuthService`, callbacks Google.
- **Admin**: routes `/admin/*`, policies, views Vue (`TrainingView`, `WebhooksView`, `EditUserModal`, `SystemAlertsOverlay`, `CommandPalette`).
- **Mobile**: `smart_attendance_repository`, `onboarding_repository`, `organigramme_repository` (apps employee/hr/manager).

## Success Criteria

### Measurable Outcomes

- **SC-001**: `curl /api/billing/checkout` en production → jamais `sandbox:true`.
- **SC-002**: Parcours trial guidé → l'utilisateur reçoit un email avec un login utilisable (test d'intégration).
- **SC-003**: 4 tests de login (user/super-admin/Google) avec compte suspendu → 403 ; suspension → tokens révoqués.
- **SC-004**: Cross-check mobile (407 appels Dart) → 0 appel vers une route/méthode inexistante.
- **SC-005**: Console admin Training/Webhooks → données réelles (tests super-admin 200).
- **SC-006**: `check-openapi-route-coverage.py` → 0 route manquante.
- **SC-007**: PHPStan strict level 8 + Pint + ESLint/tsc + tests ciblés verts sur chaque PR.

## Assumptions

- Le produit n'a pas de clé Stripe live en production aujourd'hui (constaté) — la garde sandbox est donc la priorité ; la clé elle-même est un sujet ops.
- Les changements backend PHP seront validés par CI (tests + PHPStan) ; les changements front par lint/build local.
- Le mobile est audité statiquement (pas de run Flutter dans cette session) ; les correctifs Dart sont minimalistes et vérifiés par analyse.
