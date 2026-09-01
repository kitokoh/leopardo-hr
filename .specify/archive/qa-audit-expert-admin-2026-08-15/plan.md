# Plan: Audit expert Admin — 2026-08-15

**Input**: spec.md (US B1-B7) + Constitution + audit 2026-08-15

## Architecture / Décisions techniques

- **B1 Impersonation** : corriger le chemin côté front → constante API (ex. `API.impersonate`) pointant sur `POST /platform/impersonations` (endpoint réel existant, cf. `api/routes/api.php:284`). Alternative rejetée : ajouter une route alias `/admin/impersonations` (doublon de surface). Vérifier `normalizeApiPath` dans `api.js` (préfixe `/api/v1`).
- **B2 Suppression des simulations** :
  - `SystemAlertsOverlay` : le bouton « Désactiver la maintenance » est retiré (aucun endpoint admin maintenance n'existe — état « non disponible » documenté) ; `isMaintenanceMode` alimenté par une vraie source si disponible, sinon retiré.
  - `EditUserModal` / `CreateUserModal` : composants **non atteignables** (aucun `@edit` branché) → supprimer les fichiers morts + leurs imports (`UsersView` `showEditModal`, `UserTable` émission `edit`) ; l'édition utilisateur plateforme passe par les endpoints réels `PATCH /platform/users/{id}` si on la branche (hors périmètre — retirer le bouton).
  - `MiniGlobe` : sans données socket → état vide honnête ; « Actualiser » ne re-randomise plus.
- **B3 Identifiants démo** : retirer les identifiants en dur du bundle (`LoginView`) ; bouton démo supprimé (le backend n'expose pas d'endpoint démo admin).
- **B4 UsersView** : pagination réelle (`page`/`per_page` passés à l'API, slicing server-side), filtre statut mappé ou retiré, mapping rôle/entreprise depuis le payload (`role`, `company`), CSV exporte les champs mappés (`createdAt`/`lastLoginAt` formatés) + échappement anti-injection, bouton Éditer retiré (pas de modale réelle).
- **B5 Interactions globales** : recherche header → navigation `/users?search=` (ou retrait) ; `CommandPalette` filtrée par routes accessibles (même source que sidebar) + route `/vehicles` corrigée/retirée ; carte dashboard → `/system` ou retrait.
- **B6 Notifications** : `_skipAuthRedirect: true` sur les deux appels + avaler les 401 (pattern `pollNotifications`) ; propager l'id serveur depuis le payload socket (ne plus générer d'id synthétique).
- **B7 Hygiène** : `MetricCard` — `trendValue` numérique + slot label ; titres de routes traduits dans le garde ; suppression des composants morts (vérifier `rg` imports) ; `ExportsView` état réel + erreurs affichées ; toasts à la place des `alert()` ; try/catch + état de chargement `runSimulate` ; delete user → `POST /platform/users/{id}/deactivate` + confirmation explicite ; `money()` → `toIntlLocale(localeStore.current)` ; écouter l'état du store (watch) au lieu de `$subscribe` ; ajouter un sélecteur de langue minimal (ou documenter) + router les chaînes via le catalogue i18n.

## Phases

### Phase 1 — P1 (B1, B2, B3)
- Impersonation → `/platform/impersonations` (constante API).
- Suppression/état-honnête des simulations (maintenance, modales mortes, globe).
- Retrait des identifiants démo de LoginView.

### Phase 2 — P2 (B4, B5, B6)
- UsersView (pagination, filtre, mapping, CSV, bouton éditer).
- Recherche header, palette, carte dashboard.
- Notifications (skipAuthRedirect + ids serveur).

### Phase 3 — P3 (B7)
- MetricCard, titres, composants morts, exports, alertes, runSimulate, deactivate, money(), subscribe, i18n.

## Fichiers touchés (référence)

- `front/admin-dashboard/src/views/users/UsersView.vue`, `components/users/{UserTable,EditUserModal,CreateUserModal}.vue`
- `front/admin-dashboard/src/components/alerts/SystemAlertsOverlay.vue`, `components/globe/MiniGlobe.vue`
- `front/admin-dashboard/src/views/auth/LoginView.vue`
- `front/admin-dashboard/src/components/layout/Header.vue`, `components/common/CommandPalette.vue`
- `front/admin-dashboard/src/views/DashboardView.vue`, `components/analytics/MetricCard.vue`, `views/analytics/AnalyticsView.vue`
- `front/admin-dashboard/src/stores/realtime.js`, `src/services/api.js`
- `front/admin-dashboard/src/router/index.js`
- `front/admin-dashboard/src/views/{exports,settings,growth}/**`
- Suppressions : `components/system/**`, `components/analytics/*Widget.vue` morts (vérifier imports)

## Contraintes

- Garde-fous existants : garde `requiresTenant` du routeur et filtre sidebar = source de vérité unique — ne pas casser la cohérence.
- API layer `api.js` ne pas modifier (sauf si nécessaire pour `_skipAuthRedirect`).
- Ne pas réintroduire de mocks (Constitution §V) — état vide honnête si pas de backend.
- Vérifier `rg` avant suppression de composants ; ESLint + build Vite verts.
