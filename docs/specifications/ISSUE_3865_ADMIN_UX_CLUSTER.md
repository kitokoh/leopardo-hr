# Mini-spécification — Issue #3865

## Objectif

Corriger trois défauts UX de la console admin super-admin : export groupé qui ignore la sélection, vue Exports mélangeant contrats tenant (401) et admin, double-binding Ctrl+K.

## Correction

1. **UsersView** : `exportUsers(rows = null)` accepte une liste cible ; `exportSelectedUsers()` filtre `users` par `selectedUsers` avant export. Le bouton haut de page exporte toute la page courante (inchangé), le bouton du panneau groupé exporte la sélection.
2. **ExportsView** : les 6 types d'export (`/v1/export/*`) sont marqués `clientSpace: true` — cartes désactivées avec mention « Disponible dans l'espace client » (via `t()`). `fetchHistory` : 401 → message honnête « Historique disponible dans l'espace client ». Le générateur `/admin/hr-reports` (vrai contrat super-admin) reste actif.
3. **Ctrl+K** : `case 'k'` retiré de `useKeyboardShortcuts.js` — `CommandPalette` (toujours montée dans DashboardLayout) possède le toggle.

## Critères d'acceptation

1. Export groupé = sélection uniquement (CSV).
2. Aucune carte d'export tenant actionnable dans le cockpit super-admin ; mention « espace client » visible.
3. Ctrl+K ouvre/ferme la palette une seule fois par frappe.
4. Lint, build Vite et `check-i18n-diff.js` verts.

## Trace Spec Kit

Issue : #3865
Branche : `fix/3865-admin-ux-cluster`
Date : 2026-08-15
