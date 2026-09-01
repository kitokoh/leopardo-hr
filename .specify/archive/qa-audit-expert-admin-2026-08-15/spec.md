# Feature Specification: Audit expert Admin — 2026-08-15

**Feature Branch**: `qa-audit-expert-admin-2026-08-15`

**Created**: 2026-08-15

**Status**: Draft

**Input**: Mission de test expert de la plateforme (session 2026-08-15) — audit du cockpit super-admin `front/admin-dashboard` (Vue 3, Vite) : vues, boutons, liens, données simulées, chemins API, i18n, logique. Base : `main` (`8a57dbf8`).

## User Stories & Testing

### User Story B1 — L'impersonation super-admin fonctionne réellement (Priority: P1)

`UsersView.vue:435` appelle `POST /admin/impersonations` — le backend n'expose l'impersonation que sous `POST /api/v1/platform/impersonations` (groupe `prefix('platform')`, `api/routes/api.php:284`) ; le groupe `/admin` (api.php:301) n'a **aucune** route impersonation → bouton « Imiter » → **404** systématique.

**Pourquoi P1** : feature de sécurité livrée (#2518/#2547) mais inutilisable dans la console super-admin.

**Test indépendant** : smoke API `POST /admin/impersonations` → 200 (ou route déléguée) ; Playwright : clic « Imiter » → écran de session d'impersonation (pas d'erreur).

**Acceptance Scenarios**:
1. **Given** le cockpit admin, **When** on clique « Imiter » sur un utilisateur, **Then** la requête part vers un endpoint existant (200) et la session d'impersonation s'affiche.
2. **Given** la route, **When** le token est super-admin valide, **Then** l'endpoint est le même pour tous les clients (constante API partagée pour éviter la dérive).

### User Story B2 — Aucune action simulée dans la console super-admin (Priority: P1)

- `SystemAlertsOverlay.vue:206-210` : « Désactiver la maintenance » = `setTimeout` 1000 ms + toast de succès, **aucun appel API** ; `setMaintenanceMode` n'a aucun appelant → l'état de maintenance n'est jamais réel.
- `EditUserModal.vue:304-355` : save / reset-password / welcome-email / force-logout **simulés** (`setTimeout` + toasts), sociétés codées en dur (« Acme Corp », « TechStart Inc ») — composant mort mais dangereux s'il est réactivé.
- `MiniGlobe.vue:51-73` : points « Activité en temps réel » **fabriqués** (Paris/Istanbul/Casablanca) quand le socket n'a pas de données ; « Actualiser » re-randomise des points fictifs.

**Pourquoi P1** : Constitution §V — des actions/états fabriqués dans une console ops donnent une fausse image de la plateforme.

**Test indépendant** : aucun `setTimeout(...)` simulant une action dans les composants atteignables ; le bouton maintenance appelle un endpoint réel ou est retiré ; le globe affiche un état vide honnête.

**Acceptance Scenarios**:
1. **Given** l'overlay d'alertes système, **When** on clique « Désactiver la maintenance », **Then** un appel API réel est effectué (ou l'action est retirée avec un état « non disponible »).
2. **Given** `EditUserModal`, **When** on l'ouvre, **Then** soit il est branché sur `PATCH /admin/users/{id}` + endpoints réels, soit il est supprimé (jamais simulé).
3. **Given** le globe, **When** aucun événement socket, **Then** état vide explicite (« aucune activité temps réel ») — jamais de points fictifs.

### User Story B3 — Plus d'identifiants de démonstration en dur sur la page de connexion (Priority: P1)

`LoginView.vue:207` embarque les identifiants super-admin `admin@leopardo-rh.com` / `password123` et les poste vers le **vrai** `POST /platform/auth/login` — n'importe qui peut les lire dans le source de la page et tenter l'authentification.

**Test indépendant** : aucune chaîne `password123` / `@leopardo-rh.com` dans le bundle admin ; le bouton démo est soit retiré, soit gaté `DEMO_MODE_ENABLED` avec identifiants fournis par le backend.

**Acceptance Scenarios**:
1. **Given** la page de connexion admin, **When** on inspecte le source, **Then** aucun identifiant réel en dur.
2. **Given** un environnement de démo, **When** le mode démo est actif, **Then** les identifiants viennent d'un endpoint dédié (jamais du bundle).

### User Story B4 — La liste des utilisateurs dit la vérité (Priority: P2)

`UsersView.vue` : pagination **décorative** (tous les utilisateurs affichés, `per_page` codé à 100, compteur « X à Y sur 100 » faux), filtre statut « En attente » **silencieusement ignoré**, rôle codé `'admin'` et `company: null` pour chaque ligne, export CSV qui lit `user.created_at` alors que le mapping produit `createdAt` (colonnes dates **toujours vides**) + aucun échappement CSV (injection de formule), bouton « Éditer » sans listener (`UserTable.vue:135` → `@edit` jamais branché).

**Test indépendant** : Playwright — pagination réelle (server-side), filtre pending appliqué, dates exportées, rôle/entreprise affichés depuis l'API, clic Éditer → modale.

**Acceptance Scenarios**:
1. **Given** la vue Users, **When** on change de page, **Then** les données changent (paramètres `page`/`per_page` réels).
2. **Given** le filtre « En attente », **When** on le sélectionne, **Then** seuls les utilisateurs en attente sont listés (ou l'option est retirée).
3. **Given** l'export CSV, **When** on exporte, **Then** les colonnes dates contiennent les valeurs réelles et les cellules sont échappées.
4. **Given** la liste, **When** on clique le crayon, **Then** une modale d'édition réelle s'ouvre (ou le bouton est retiré).

### User Story B5 — Les interactions globales ne sont pas mortes (Priority: P2)

- Recherche globale du header (`Header.vue:237-241`) : `console.log('Searching for:')` + TODO — aucune recherche.
- `CommandPalette.vue:105-123` : liste des routes gatées tenant (payroll/leaves/recruitment/training/exports/webhooks/chat) que le garde du routeur redirige + `route: '/vehicles'` inexistant (404).
- `DashboardView.vue:154-160` : carte « Préparer intégrations partenaires » → `/webhooks` (requiresTenant) → toast + redirection : carte morte sur la page d'accueil.

**Test indépendant** : saisie dans la recherche → navigation ou retrait ; palette filtrée par routes réellement accessibles ; carte dashboard → cible réelle.

**Acceptance Scenarios**:
1. **Given** le header, **When** on tape une recherche, **Then** on navigue vers une liste filtrée (ou le champ est retiré).
2. **Given** la palette, **When** on liste les entrées, **Then** aucune route inexistante ni route tenant-gatée.

### User Story B6 — Les notifications ne détruisent plus la session (Priority: P2)

`stores/realtime.js:330-357` : `markNotificationAsRead` / `markAllNotificationsAsRead` appellent les routes **tenant** `/v1/notifications/...` **sans** `_skipAuthRedirect` → en super-admin, un 401 déclenche l'intercepteur (suppression du token + redirection `/login`) : un clic sur « Tout marquer comme lu » peut détruire la session. De plus les notifications push reçoivent des ids **synthétiques** (`Date.now()+Math.random()` à :306) → `PATCH /v1/notifications/{id}/read` 404 systématique.

**Test indépendant** : clic « Tout marquer comme lu » en super-admin → aucun wipe de session ; notification push → le `PATCH .../read` utilise l'id serveur réel.

**Acceptance Scenarios**:
1. **Given** le store realtime, **When** une notification arrive par socket, **Then** elle porte l'id serveur réel (pas d'id synthétique).
2. **Given** un 401 sur une route tenant, **When** on marque comme lu, **Then** l'erreur est avalée comme dans `pollNotifications` (pas de redirect).

### User Story B7 — Petits défauts de logique et d'i18n (Priority: P2/P3)

- `MetricCard.vue:116` + `AnalyticsView.vue:225-231` : la prop `trend` (validée `up|down|stable`) reçoit des **libellés** (« +5 aujourd'hui », « €1 234 », « Health: good ») → le chip affiche toujours « 0% ».
- Titres d'onglets : `meta.title` = clés i18n brutes (`marketing.oauth.nav_title`, `holidays.nav.title`) → l'onglet du navigateur affiche la clé littérale.
- 18 composants morts (system/analytics) dont `RevenueForecastWidget` (génère des données `Math.random()`), `BackupManagement`, `ApiTesterModal`, `ImportConfigModal`… — jamais importés : supprimer ou brancher.
- `ExportsView.vue:170,189` : état de téléchargement simulé + `fetchHistory` avale les erreurs → un échec backend ressemble à « aucun export ».
- `GrowthDashboardView.vue:168,177` : `alert()` natif pour les erreurs API.
- `SocialContributionsView.vue:314-326` : `runSimulate` sans try/catch ni état de chargement.
- `UsersView.vue:463-469` : `deleteUser` = `DELETE /platform/users/{id}` (destruction physique) alors que l'UI/toast dit « désactivation ».
- `money()` codé `fr-FR` (`TaxSlabsView:242`, `SocialContributionsView:346`) → ignorer la locale active.
- `NotificationPanel.vue:94` / `SystemAlertsOverlay.vue:151` : filtre `$subscribe` sur `mutation.events` — les mutations directes Pinia émettent `events: null` → les listeners realtime ne se déclenchent jamais.
- Pas de sélecteur de langue ; dictionnaires ar/tr/en (55k clés) morts ; vues en français codé en dur.

**Test indépendant** : titres d'onglets traduits ; `rg "RevenueForecastWidget|BackupManagement"` → aucune référence morte ou composants supprimés ; erreurs API → toasts ; `money()` suit la locale ; delete user → endpoint de désactivation.

**Acceptance Scenarios**:
1. **Given** le cockpit, **When** on navigue, **Then** l'onglet affiche un titre traduit.
2. **Given** une erreur API sur Export/Growth/SocialContributions, **When** elle survient, **Then** un feedback utilisateur s'affiche (jamais `alert()` ni silence).
3. **Given** la suppression d'un utilisateur, **When** on clique, **Then** l'intention destructive est explicite ou l'appel passe par `/platform/users/{id}/deactivate`.

## Edge Cases

- Impersonation : garder le motif obligatoire (≥5 chars), le token à usage unique, l'horodatage serveur — ne changer que le chemin.
- Notifications : les routes tenant ne doivent jamais déclencher le wipe de session super-admin (même pattern `_skipAuthRedirect` que `pollNotifications`).
- CSV : échapper les cellules commençant par `=`, `+`, `-`, `@` (injection de formule).
- Suppression de composants morts : vérifier les imports avant suppression (`rg`).
