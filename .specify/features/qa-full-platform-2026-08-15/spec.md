# Feature Specification: QA Full Platform — 2026-08-15

**Feature Branch**: `qa-full-platform-2026-08-15`

**Created**: 2026-08-15

**Status**: In progress

**Input**: Constitution `.specify/constitution.md` + AGENTS.md + campagne de test complète (vitrine, web, admin, mobile, workflows, APIs, logiques, onboarding, cohérence) — smoke live sur `gestionemployerbackend.onrender.com`, `gestionemployer-backend.vercel.app`, `leo-admin.pages.dev` + audit statique des 5 surfaces + contrat OpenAPI.

## Contexte

Campagne de test « dans tous les sens du terme » demandée par le propriétaire : chaque manquement constaté devient une issue GitHub + un lot de tâches (méthode Spec Kit), puis est implémenté **à la fin** de la campagne. 11 manquements classés P0→P2 (issues #2652–#2662).

## User Scenarios & Testing

### User Story 1 — Login API ne renvoie plus 500 (Priority: P1)

Un employé existant (compte tenant) se connecte avec un email/mot de passe juste ou faux : il reçoit une réponse du contrat d'erreur API (401 INVALID_CREDENTIALS / 403 / 5xx conforme), jamais un `500 {"message":"Server Error"}` brut.

**Independent Test**: `php artisan test --filter=AuthServiceTest` (ou LoginFlowTest) vert ; smoke live `POST /api/v1/auth/login` avec un compte tenant → 401 propre.

**Acceptance Scenarios**:

1. **Given** un compte tenant existant avec `password_hash` valide, **When** login avec mauvais password, **Then** 401 INVALID_CREDENTIALS (shape contrat) — aujourd'hui 500.
2. **Given** un compte tenant avec `password_hash` null, **When** login, **Then** 401 INVALID_CREDENTIALS (pas de TypeError).
3. **Given** un schéma tenant pointé par `user_lookups` inexistant/table absente, **When** login, **Then** erreur conforme (401/5xx shape), jamais HTML ni exception non mappée.
4. **Given** `locked_until` mal formé (non-Carbon), **When** login, **Then** pas de crash `->isFuture()`.

### User Story 2 — Contrat d'erreur API unifié (Priority: P1)

Tout endpoint `/api/v1/*` répond en JSON avec la shape `{error, message, localized_message}` (401/403/404/422/429/500), quelle que soit l'exception et le client (avec ou sans `Accept: application/json`).

**Independent Test**: smoke live : `GET /api/v1/employees` sans `Accept` → 401 JSON (pas de redirect HTML) ; `GET /api/v1/i18n/catalog/fr` → pas de page HTML 500 ; tests Feature du renderer.

**Acceptance Scenarios**:

1. **Given** une requête non authentifiée sur `/api/v1/*` sans header `Accept`, **When** la route exige auth, **Then** 401 JSON shape contrat (aujourd'hui redirect HTML `/login`).
2. **Given** une exception non mappée sur `/api/v1/*`, **When** elle est levée, **Then** 500 JSON `{error: "INTERNAL_ERROR", ...}` (aujourd'hui `{"message":"Server Error"}`).
3. **Given** une 404 sur `/api/v1/*`, **When** ressource absente, **Then** `localized_message` cohérent (aujourd'hui « Resource not found. » vs « Ressource introuvable. »).
4. **Given** un token d'invitation onboarding invalide, **When** `GET /onboarding/invitation/{token}`, **Then** code d'erreur plat (aujourd'hui `error` objet imbriqué).

### User Story 3 — Routes runtime réparées (Priority: P1)

Les routes déclarées répondent : `POST /edge/{nodeId}/sync` fonctionne (méthode existe), `POST /webhooks/{endpoint}/test` n'est déclaré qu'une fois, `/i18n/catalog/*` ne 500 jamais (erreur conforme si catalogue indisponible).

**Independent Test**: `php artisan route:list --path=edge` montre `sync` ; tests Feature `EdgeNodeSyncTest` + `WebhookControllerTest` ; smoke `/i18n/catalog/fr`.

**Acceptance Scenarios**:

1. **Given** un nœud edge authentifié, **When** `POST /edge/{nodeId}/sync`, **Then** 200/202 (aujourd'hui `BadMethodCallException` 500).
2. **Given** la liste des routes, **When** `php artisan route:list`, **Then** `webhooks/{webhookEndpoint}/test` apparaît une seule fois.
3. **Given** le catalogue i18n absent du conteneur, **When** `GET /i18n/catalog/fr`, **Then** erreur conforme (503/404 shape), pas de HTML 500.

### User Story 4 — Dashboard web : plus d'URLs mortes (Priority: P1)

Les actions rapides et cartes du dashboard client pointent vers des routes existantes ; le service worker ne précache pas de 404 ; le test e2e ne valide plus l'URL morte.

**Independent Test**: `npx tsc --noEmit` + lint verts ; `rg '/dashboard/(reports|employees|absences|attendance)'` = 0 dans `page.tsx`/`sw.js` ; e2e dashboard-quick-actions vert.

**Acceptance Scenarios**:

1. **Given** un utilisateur connecté sur `/dashboard`, **When** il clique « Employés », **Then** il arrive sur `/employees` (aujourd'hui 404 `/dashboard/employees`).
2. **Given** le service worker, **When** il précache, **Then** les URLs précachées existent.
3. **Given** le spec e2e, **When** il clique une action rapide, **Then** il asserte le contenu de la page cible (pas seulement l'URL).

### User Story 5 — SEO : canonicals et metadata cohérents (Priority: P1)

Aucune page n'émet de canonical vers un domaine tiers ou `localhost` ; la metadata pricing reflète les plans réels ; le sitemap ne liste pas le blog quand le flag est off.

**Independent Test**: `rg 'gestionemployer-backend.vercel.app|localhost:3000' front/web/src` = 0 (sauf commentaire #1775) ; build vert ; sitemap sans `/blog/*` quand `NEXT_PUBLIC_ENABLE_BLOG=false`.

**Acceptance Scenarios**:

1. **Given** un build sans `NEXT_PUBLIC_SITE_URL`, **When** une page émet son canonical, **Then** il utilise le domaine de marque (jamais localhost/domaine tiers).
2. **Given** la page `/pricing`, **When** Google indexe la description, **Then** elle cite les vrais plans (Free/Pilot 29€/Operations 99€).
3. **Given** `NEXT_PUBLIC_ENABLE_BLOG=false`, **When** le sitemap est généré, **Then** aucune URL `/blog/*`.

### User Story 6 — Web i18n & hygiène (Priority: P2)

`<html lang>` reflète la locale SSR ; plus de redirect placeholder ; dates blog cohérentes.

**Independent Test**: lint/build verts ; `curl -sI <deploy>/blog` → lang correct en SSR ; `vercel.json` sans `/old-page`.

**Acceptance Scenarios**:

1. **Given** une requête SSR avec locale en, **When** le HTML est rendu, **Then** `lang="en"` (aujourd'hui `lang="fr"`).
2. **Given** le déploiement Vercel, **When** on accède `/old-page`, **Then** 404 normal (pas de redirect placeholder).

### User Story 7 — Admin : zéro composant mort, erreurs visibles, e2e verts (Priority: P1)

Aucun composant orphelin avec fausse logique dans l'arbre ; EdgeNodesView affiche une erreur en cas d'échec ; les specs e2e passent ; les specs tenant ne tapent pas un vrai backend sans garde.

**Independent Test**: lint admin 0 erreur ; `rg 'Simulate API' src` = 0 ; `npx playwright test e2e/dashboard-kpi.spec.js e2e/platform-auth-smoke.spec.js` verts avec mocks.

**Acceptance Scenarios**:

1. **Given** l'arbre des composants, **When** on cherche les imports, **Then** `EditUserModal`/widgets analytics morts sont supprimés ou intégrés.
2. **Given** un échec réseau sur EdgeNodesView, **When** `refresh()` échoue, **Then** un état d'erreur/toast s'affiche (aujourd'hui rejection non gérée).
3. **Given** la CI web, **When** les specs tenant tournent sans backend, **Then** elles sont mockées ou skip (aujourd'hui échec).

### User Story 8 — Admin : base URL API sûre (Priority: P2)

Aucun build (CI ou local) n'embarque localhost comme API par défaut sans `VITE_API_URL` ; la doc ne contredit plus le code.

**Independent Test**: `rg 'localhost:8000' front/admin-dashboard/src` = 0 hors dev explicit ; `deploy-admin-dashboard.yml` contient `VITE_API_URL`.

**Acceptance Scenarios**:

1. **Given** un build sans env, **When** le bundle est inspecté, **Then** l'URL API par défaut est la prod (aujourd'hui localhost:8000).
2. **Given** la CI de déploiement, **When** elle build, **Then** `VITE_API_URL` est injecté explicitement.
3. **Given** la doc, **When** on lit README/.env.example/_headers, **Then** une seule vérité sur la base URL.

### User Story 9 — Mobile : zéro mojibake (Priority: P1)

Les apps employee/manager n'affichent aucun caractère double-encodé.

**Independent Test**: `rg -n 'Ã©|Ã¨|Ãª|Ã´|Ã®|Ã§|Ã‰|Ø§Ù†|Ã ' front/mobile_apps/*/lib` = 0.

**Acceptance Scenarios**:

1. **Given** l'écran smart_attendance (employee), **When** il affiche ses libellés, **Then** « Période »/« Évaluation » corrects (aujourd'hui « PÃ©riode »/« Ã©valuation »).
2. **Given** l'écran profil, **When** le sélecteur de langue est affiché, **Then** « العربية » en Unicode (aujourd'hui mojibake `Ø§Ù„Ø¹Ø±Ø¨ÙŠØ©`).

### User Story 10 — Mobile : intégration marketing + parité CI (Priority: P2)

`leopardo_marketing` est intégré à melos/CI/README ; le script de validation couvre hr ; les pipelines distribuent les 4 apps ; flutter_lints aligné.

**Independent Test**: `melos bootstrap` liste 6 packages ; `validate-mobile-apps-split.ps1` passe ; `rg 'flutter_lints' */pubspec.yaml` → ^6.0.0 partout.

**Acceptance Scenarios**:

1. **Given** melos.yaml, **When** on liste les packages, **Then** marketing y figure.
2. **Given** le script de validation, **When** il tourne, **Then** il vérifie aussi leopardo_hr.
3. **Given** les workflows de distribution, **When** ils buildent, **Then** les mêmes apps que melos (4).

### User Story 11 — OpenAPI : surface documentée (Priority: P2)

Les routes réelles de plus forte valeur sont documentées ; la garde CI voit les routes DDD ; les doublons stale sont retirés.

**Independent Test**: `python3 dev-hub/tools/check-openapi-route-coverage.py` vert ; `rg '/admin/social-contributions' api/openapi.yaml` présent.

**Acceptance Scenarios**:

1. **Given** le CRUD admin social-contributions, **When** la spec est générée, **Then** il est documenté (aujourd'hui absent, racine seulement).
2. **Given** les routes EdgeSync/SmartAttendance, **When** la garde CI tourne, **Then** elles sont prises en compte.
3. **Given** `/tax-slabs` documenté deux fois, **When** la spec est relue, **Then** un seul chemin canonique.

### Edge Cases

- Login : utilisateur avec `password_hash` null ; `locked_until` chaîne invalide ; schéma tenant supprimé mais `user_lookups` restant ; compte désactivé vs entreprise suspendue.
- Erreurs : client sans header `Accept` ; 429 throttle (headers préservés) ; POST trop volumineux.
- Edge sync : nœud inconnu ; nœud sans licence.
- Web : locale en/ar/tr au SSR ; blog flag off (sitemap, redirects).

## Requirements

### Functional Requirements

- **FR-001**: `POST /api/v1/auth/login` MUST retourner une erreur du contrat API (401/403/5xx shape) pour tout état de données, jamais de 500 brut.
- **FR-002**: Toute route `/api/v1/*` non authentifiée MUST répondre 401 JSON shape contrat, y compris sans header `Accept`.
- **FR-003**: Toute exception non mappée sur `/api/v1/*` MUST répondre 500 `{error:"INTERNAL_ERROR"}` (log + Sentry, sans fuite).
- **FR-004**: `POST /api/v1/edge/{nodeId}/sync` MUST exister et déclencher la synchronisation du nœud.
- **FR-005**: `POST /webhooks/{webhookEndpoint}/test` MUST être déclaré une seule fois.
- **FR-006**: `GET /api/v1/i18n/catalog/{locale}` MUST ne jamais rendre de page HTML (erreur conforme si catalogue absent).
- **FR-007**: Les liens d'actions rapides du dashboard client MUST pointer vers des routes existantes.
- **FR-008**: `SITE_URL` MUST être centralisé (env prioritaire, fallback domaine de marque, jamais localhost/domaine tiers).
- **FR-009**: La metadata de `/pricing` MUST refléter les plans réels.
- **FR-010**: Le sitemap MUST conditionner les URLs blog au flag `NEXT_PUBLIC_ENABLE_BLOG`.
- **FR-011**: L'arbre admin MUST ne contenir aucun composant orphelin avec logique simulée.
- **FR-012**: `EdgeNodesView` MUST afficher un état d'erreur sur échec réseau.
- **FR-013**: Le build admin MUST embarquer l'URL API de prod par défaut (ou `VITE_API_URL` injecté en CI).
- **FR-014**: Les libellés mobile employee/manager MUST être en Unicode propre (0 mojibake).
- **FR-015**: `leopardo_marketing` MUST être intégré à melos/CI/README (ou retiré).
- **FR-016**: Les routes API réelles à forte valeur MUST être documentées dans `openapi.yaml` ; la garde CI MUST couvrir les routes DDD.

### Key Entities

- **Employee / Company / user_lookups** : chemin de login multi-schéma (AuthService).
- **EdgeNode / SyncLog** : cycle de vie edge + sync.
- **WebhookEndpoint / WebhookDelivery** : test de webhook.
- **I18nCatalog** : catalogue + versions (shared/i18n).
- **SITE_URL / pricing plans** : métadonnées web.
- **VITE_API_URL** : config admin.
- **melos packages** : 6 apps mobiles.

## Success Criteria

### Measurable Outcomes

- **SC-001**: `POST /api/v1/auth/login` ne renvoie plus jamais de `500 {"message":"Server Error"}` (smoke live + tests).
- **SC-002**: 0 redirect HTML sur `/api/v1/*` (smoke live sans header Accept).
- **SC-003**: `php artisan route:list` sans doublon webhook ; `sync` présent sur edge.
- **SC-004**: 0 occurrence `rg mojibake` sur mobile (employee/manager).
- **SC-005**: `rg 'gestionemployer-backend.vercel.app|localhost:3000'` = 0 dans les fallbacks web.
- **SC-006**: Lint + builds frontends (web/admin) verts ; e2e admin corrigés verts.
- **SC-007**: Garde OpenAPI CI verte avec les routes DDD couvertes.
- **SC-008**: `melos bootstrap` + `validate-mobile-apps-split.ps1` verts avec 6 packages.

## Assumptions

- Le retard de déploiement de la prod (health 4.23.5 vs défaut 4.24.0) est un constat ops : la campagne corrige le code ; la vérification finale live se fera après le prochain déploiement.
- Les comptes démo de prod (`karim.aouad@…` etc.) existent dans la base : utilisés comme cas de test du chemin tenant.
- Le retrait de `leopardo_marketing` est destructif → intégration minimale privilégiée.
- La migration des 13 repositories dupliqués vers `leopardo_core` est un chantier documenté (pas un prérequis de cette vague).
