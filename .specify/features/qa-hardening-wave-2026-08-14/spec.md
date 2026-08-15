# Feature Specification: Vague QA Hardening 2026-08-14

**Feature Branch**: `qa-hardening-wave-2026-08-14`

**Created**: 2026-08-14

**Status**: Draft

**Input**: Mission de test de la plateforme (session 2026-08-14) — audit fonctionnel des workflows API, vues, boutons et logiques sur `main` (`0feb18ad`) :
- Audit routes API vs contrôleurs vs OpenAPI (632 routes, 0 classe/méthode manquante — OK).
- Audit endpoints consommés par les frontends (admin-dashboard, web, mobile) vs `php artisan route:list` (724 routes réelles).
- Audit des vues admin-dashboard (36 vues) — 7 workflows cassés (endpoints 404), 3 pages mock, ~10 boutons/liens morts.
- Audit mobile employee/manager/hr — 2 endpoints appelés inexistants (`/me/training-enrollments`, `/me/vehicles`).
- Outils repo : `.env.example` en décalage avec `config/` (BIOMETRIC_RETENTION_MONTHS, MAIL_URL — issue #1487).

## User Scenarios & Testing

### User Story 1 — Les fonctionnalités mobiles employé appellent des endpoints réels (Priority: P1)

L'employé qui ouvre l'écran Formation voit ses inscriptions (titre du cours, date de session, progression, statut) et l'écran Véhicules affiche les véhicules qui lui sont assignés avec leur dernière position. Aujourd'hui les deux écrans cassent : `GET /me/training-enrollments` et `GET /me/vehicles` n'existent pas (404).

**Pourquoi P1** : features mobiles livrées mais inutilisables — 404 silencieux → écrans vides/erreur pour les testeurs terrain.

**Test indépendant** : `php artisan test --filter=MeTrainingEnrollments` et `--filter=MeVehicles` + smoke curl des 2 endpoints avec un token employee demo ; l'app employee n'affiche plus d'état d'erreur.

**Acceptance Scenarios**:

1. **Given** un employé avec des inscriptions formation, **When** il appelle `GET /api/v1/me/training-enrollments`, **Then** la réponse liste ses inscriptions avec `id`, `course_title`, `session_date`, `progress`, `status` (shape compatible `TrainingEnrollment.fromJson`), isolée au tenant.
2. **Given** un employé assigné à un véhicule, **When** il appelle `GET /api/v1/me/vehicles`, **Then** la réponse liste les véhicules dont `assigned_driver_id` = lui-même avec `vehicle_id`/`id`, `plate_number`, `brand`, `model`, `latitude`, `longitude`, `speed`, `updated_at` (shape `VehiclePosition.fromJson`), sans fuite cross-tenant (404/403 si assigné à un autre tenant).
3. **Given** un employé sans véhicule assigné, **When** il appelle `/me/vehicles`, **Then** `data=[]` (pas de 500).

### User Story 2 — Les vues du cockpit admin appellent les endpoints réels du backend (Priority: P1)

Les vues Chat, Exports, Fleet, Marketing OAuth, Training et Webhooks du cockpit admin appellent des chemins inexistants (`/v1/...` au lieu de `/admin/...` ou d'endpoints à créer) — chaque action concernée renvoie 404 et les onglets sont vides.

**Pourquoi P1** : le cockpit super-admin est la surface ops du SaaS ; des onglets qui échouent en silence donnent une fausse image de l'état plateforme.

**Test indépendant** : Playwright admin + smoke API : chaque action des 6 vues aboutit à une réponse JSON 2xx (pas de 404).

**Acceptance Scenarios**:

1. **Given** le cockpit admin, **When** on ouvre l'onglet Chat, **Then** la liste des conversations et les messages viennent de `/admin/ai/conversations` (+ `/{id}/messages`) et s'affichent.
2. **Given** la vue Exports, **When** on génère un rapport HR, **Then** l'appel passe par `/admin/hr-reports` et le résultat s'affiche (ou une erreur explicite).
3. **Given** la vue Fleet, **When** on ouvre l'onglet Alertes, **Then** les alertes viennent de `/admin/fleet/alerts`.
4. **Given** la vue Marketing OAuth, **When** on sauvegarde une config LinkedIn/Facebook/X, **Then** l'appel passe par `/admin/platform/marketing/oauth-config` (PUT) et un feedback s'affiche.
5. **Given** la vue Training, **When** on ouvre les onglets Sessions/Inscriptions, **Then** les données viennent de `GET /training/sessions` et `GET /training/enrollments` (endpoints tenant à créer, couverts par tests + OpenAPI).
6. **Given** la vue Webhooks, **When** on clique « Tester », **Then** un événement de test est dispatché via `POST /webhooks/{webhookEndpoint}/test` (endpoint à créer) et le résultat s'affiche.

### User Story 3 — Le cockpit admin n'affiche plus de données fictives (Priority: P1)

Les pages Users, Analytics et System du cockpit admin simulent aujourd'hui des données/actions avec `setTimeout` et des générateurs fake (`generateMockUsers`, 150 utilisateurs fabriqués, cohortes/funnels inventés, health check simulé, maintenance togglée en local). Elles doivent consommer les endpoints réels ou afficher un état vide honnête.

**Pourquoi P1** : des chiffres fabriqués dans une console super-admin sont dangereux pour les décisions (et contraires à la Constitution §V).

**Test indépendant** : aucune séquence `setTimeout(...)`/`generateMock*` restante dans les 3 vues ; les données affichées proviennent d'appels API documentés.

**Acceptance Scenarios**:

1. **Given** la vue Users, **When** elle charge, **Then** la liste provient d'`/invitations` (+ `/platform/impersonations` pour l'action d'impersonation réelle) ; plus aucun utilisateur généré en local ; l'export utilise les données réelles chargées.
2. **Given** la vue Analytics, **When** elle charge, **Then** les KPI viennent d'`/admin/dashboard/stats`, l'activité de `/admin/dashboard/activities`, les alertes de `/admin/dashboard/alerts` ; plus de cohortes/funnels fabriqués — sinon état vide documenté.
3. **Given** la vue System, **When** on lance le Health Check, **Then** l'appel passe par `/health/live` + `/health/ready` ; les actions sans backend (maintenance, backups) affichent un état « non disponible » explicite au lieu de simuler un succès.

### User Story 4 — Hygiène : env, boutons morts et petits défauts logiques (Priority: P2)

Le fichier `.env.example` est aligné sur `config/` ; les boutons/liens morts du cockpit sont supprimés ou branchés ; les petits bugs logiques (champ `legal_reference` perdu, `openRequest(id)` ignoré, fallback utilisateur trompeur, classe CSS `glass-bg0/50` inexistante) sont corrigés.

**Pourquoi P2** : la Constitution §VII exige une CI verte et un repo propre ; les boutons morts nuisent à la crédibilité de la démo.

**Test indépendant** : `check-env-example-parity.sh` vert ; audit visuel Playwright des boutons corrigés.

**Acceptance Scenarios**:

1. **Given** `.env.example`, **When** on exécute `dev-hub/tools/check-env-example-parity.sh`, **Then** aucune clé `config/` manquante (BIOMETRIC_RETENTION_MONTHS, MAIL_URL présentes).
2. **Given** la vue Growth, **When** on clique « Gérer » un partenaire, **Then** l'action ouvre la fiche partenaire ou le bouton est retiré (jamais inerte).
3. **Given** `TaxRatesView`, **When** on crée un taux avec une référence légale, **Then** `legal_reference` est envoyé dans le payload.
4. **Given** les vues concernées, **When** on inspecte le DOM, **Then** plus de classe `glass-bg0/50` inexistante (remplacée par un token réel).

### Edge Cases

- Endpoint `/me/vehicles` : employé sans `assigned_driver_id` → `data=[]` ; véhicule avec `traccar_device_id` absent → position `null` (pas de 500) ; requête cross-tenant → 404.
- `/me/training-enrollments` : aucun endpoint antérieur — garder `/me/trainings` intact (rétro-compat) ; la Resource enrichie ne casse pas les consommateurs existants (champs additifs).
- Webhooks test : endpoint sans secret d'événement → 422 explicite ; delivery de test tracée dans `webhook_deliveries`.
- Vues admin : les erreurs API doivent afficher un feedback utilisateur (pas de `catch {}` silencieux).
