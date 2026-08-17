# Feature Specification: Cluster admin i18n/UX — dates, statuts, temps relatifs, fallback socket (issues #4714, #4715, #4716)

**Feature Branch**: `fix/4714-4716-admin-i18n-ux`

**Created**: 2026-08-17

**Status**: Draft → Implemented

**Input**: Audit 360° 2026-08-16/17 (admin-dashboard) — trois résidus i18n/ops :
- #4714 : `UserDetailModal.vue` — `toLocaleDateString()` sans locale (incohérent avec `toIntlLocale` partout ailleurs) ;
- #4715 : `stores/realtime.js` — fallback silencieux `ws://localhost:6001` dans le bundle de prod quand `VITE_API_URL` est absent au build (le socket vise le localhost du VISITEUR) ;
- #4716 : libellés FR codés en dur — temps relatifs Header (« À l'instant », `${n}m`, `${n}h`), « Aucun resultat trouve. » (DataTable), défauts ConfirmDialog (Confirmer/Annuler/En cours…), statuts UserDetailModal (Actif/Inactif/Suspendu/Attente), en-têtes CSV UsersView, « Aucune alerte critique » + titres mode clair/sombre Header.

## Décision

1. Toutes les dates/statuts/libellés passent par le catalogue admin (`$t` / `translate(localeStore.current, …)`) avec fallback FR identique à l'existant — 27 nouvelles clés ×4 locales (parité 987 clés/locale, garde `check-i18n-diff`).
2. Temps relatifs : `Intl.RelativeTimeFormat(toIntlLocale(localeStore.current), { numeric: 'auto' })` (pattern standard, zéro dépendance).
3. `realtime.js` : plus de `ws://localhost:6001` — VITE_API_URL absent/invalide → socket dérivé du host servi (`location.host`, même origine que l'API) ; hors navigateur → URL vide (same-origin).
4. Aucun changement de contrat API, aucune dépendance ajoutée.

## User Scenarios & Testing

### US1 — Les dates/statuts du modal utilisateur suivent la locale active (P3, #4714)
**Independent Test**: review + garde i18n CI ; pas de test unitaire Vue existant sur ce composant.
1. **Given** locale `en`, **When** le modal affiche `created_at`, **Then** format anglais (`MM/DD/YYYY`).
2. **Given** locale `ar`, **When** un utilisateur est `suspended`, **Then** libellé arabe.

### US2 — Le bundle de prod ne contient plus `ws://localhost` (P3, #4715)
1. **Given** build sans `VITE_API_URL`, **When** le bundle est inspecté, **Then** aucun `ws://localhost:6001` ; à l'exécution, le socket cible `location.host`.
2. **Given** `VITE_WEBSOCKET_URL` défini, **When** connexion, **Then** priorité conservée à la variable explicite.

### US3 — Les libellés communs sont localisés ×4 (P3, #4716)
1. **Given** locale `tr`, **When** une table est vide, **Then** « Sonuç bulunamadı. ».
2. **Given** locale `en`, **When** une notification date de 5 min, **Then** « 5 minutes ago » (Intl).
3. **Given** export CSV en locale `ar`, **Then** en-têtes en arabe.

## Edge Cases

- `DataTable.emptyMessage` reste surchargeable par les callers (prop '' → fallback `$t`).
- `ConfirmDialog` garde ses props de label (surcharge possible) ; les défauts passent par `$t`.
- `Intl.RelativeTimeFormat` est supporté partout (Node 16+/browsers modernes) — pas de polyfill nécessaire.
- `location` indisponible (SSR/build) → URL de socket vide (comportement même-origin, jamais localhost).
