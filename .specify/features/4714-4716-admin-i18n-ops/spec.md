# Feature Specification: Admin cockpit — dates localisées, temps relatif i18n, fallback WS prod (Closes #4714, #4715, #4716)

**Feature Branch**: `fix/4714-4716-admin-i18n-ops`
**Created**: 2026-08-17 | **Status**: In progress
**Issues**: #4714 (P3, admin, i18n) · #4715 (P3, admin, ops) · #4716 (P3, admin, i18n)

## Contexte

Audit 360° 2026-08-16 (swe-qa-360). Le cockpit admin-dashboard est localisé
×4 (catalogues `src/i18n/locales/{fr,en,tr,ar}.json`, pattern #4206), mais
trois classes de littéraux FR/non-localisés subsistent :

1. **#4714** — `UserDetailModal.vue` formate les dates via
   `toLocaleDateString()` **sans locale** : le format suit la langue du
   navigateur, pas la locale active du cockpit (incohérent avec
   `UserTable.vue`, `SystemStatusCard.vue`… qui passent
   `toIntlLocale(localeStore.current)`).
2. **#4716** — le **temps relatif** est codé en dur en FR dans 4 composants :
   `Header.vue` (`À l'instant`, `${n}m`, `${n}h`), `EdgeNodesView.vue`
   (`à l'instant`, `il y a X min`, `il y a X h`, + `licenseLabel`
   `Valide`/`Invalide`), `QueueObservabilityCard.vue` /
   `NotificationObservabilityCard.vue` (`Jamais`, `À l'instant`, `${n}m`).
   S'ajoutent les libellés `DataTable.vue` (`Chargement des données...`,
   `Export CSV`) et les défauts FR de `ConfirmDialog.vue`
   (`Confirmer`/`Annuler`/`En cours…`) — visibles dans les 4 locales.
3. **#4715** — `stores/realtime.js` garde un fallback `ws://localhost:6001`
   dans le bundle de prod quand `VITE_API_URL` est absent/invalide : le
   navigateur du visiteur tente une connexion vers SA propre machine. La
   base API par défaut (`api.js` → `apiBaseURL`) pointe déjà la prod ;
   le fallback WS doit en dériver la même origine `wss://`.

## User Stories & Testing

### User Story 1 — Dates du détail utilisateur dans la locale active (P3)

En tant qu'admin ayant choisi l'arabe ou l'anglais, je veux que les dates du
modal utilisateur suivent la locale du cockpit.

**Acceptance Scenarios**:
1. Given locale cockpit = `ar`, When le modal affiche `created_at`, Then le
   format est arabe (et non `en-US` du navigateur).
2. Given `date` nulle, Then `-` inchangé.

### User Story 2 — Temps relatif localisé ×4 (P3)

En tant qu'utilisateur EN/TR/AR, je veux « just now / şimdi / الآن » et non
« À l'instant » dans l'en-tête, les vues Edge et les cartes d'observabilité.

**Acceptance Scenarios**:
1. Given locale = `en`, When un événement date de 5 min, Then « 5 min ago »
   (et non « il y a 5 min »).
2. Given locale = `ar`, When aucune valeur, Then « أبدًا » (et non « Jamais »).

### User Story 3 — Aucun fallback localhost dans le bundle prod (P3)

En tant qu'opérateur, je veux que le bundle de prod ne contienne jamais
`ws://localhost` : le repli doit viser l'origine API de prod.

**Acceptance Scenarios**:
1. Given build sans `VITE_API_URL`, When `connect()` calcule l'URL WS par
   défaut, Then `wss://gestionemployerbackend.onrender.com` (grep du bundle :
   zéro `localhost:6001`).
2. Given dev local avec `VITE_API_URL=http://localhost:8000/api/v1`, When le
   défaut est calculé, Then `ws://localhost:8000` (inchangé).

## Requirements

- **FR-001**: `UserDetailModal.formatDate` → `toLocaleDateString(toIntlLocale(localeStore.current))`.
- **FR-002**: namespace `time.*` ajouté aux 4 catalogues admin (justNow,
  minutesAgo/hoursAgo avec `{count}`, minutesShort/hoursShort, never,
  loadingData, confirm/cancel/inProgress, valid/invalid, statusActive/
  statusInactive/statusSuspended/statusPending, exportCsv).
- **FR-003**: les 4 sites de temps relatif + `licenseLabel` + libellés
  `DataTable`/`ConfirmDialog` passent par `translate()`/`$t()` avec fallback FR.
- **FR-004**: `ConfirmDialog` garde ses props surchargeables (défaut `''` →
  libellé localisé via `$t`).
- **FR-005**: `realtime.js` dérive le fallback WS de `api.defaults.baseURL` ;
  aucun `localhost` résiduel dans le chemin par défaut.
- **FR-006**: la garde `check-i18n-diff.js` (nouvelles chaînes hors catalogue)
  reste verte sur le diff ; CHANGELOG.md mis à jour.

## Success Criteria

- **SC-001**: `rg "toLocaleDateString()" front/admin-dashboard/src` → 0 sur
  les composants d'utilisateur (hors cas volontaires).
- **SC-002**: `rg "À l'instant|il y a|Jamais" front/admin-dashboard/src` → 0
  sur les 4 composants ciblés.
- **SC-003**: `rg "localhost:6001" front/admin-dashboard/src` → 0.
- **SC-004**: eslint admin vert ; `check-i18n-diff.js base..head` vert.
